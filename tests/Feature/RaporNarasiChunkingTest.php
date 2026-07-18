<?php

namespace Tests\Feature;

use App\Filament\Resources\RaporResource\Pages\ViewRapor;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coverage untuk chunking "Generate Semua Deskripsi" di ViewRapor (Audit 4.5).
 *
 * Membuktikan pola Livewire event-recursion: antrian siswa diproses per
 * NARASI_CHUNK_SIZE (5) lewat round-trip berulang, menghasilkan FinalGrade
 * narasi untuk SELURUH siswa tanpa memproses semuanya dalam satu request.
 */
class RaporNarasiChunkingTest extends TestCase
{
    use RefreshDatabase;

    private ClassHomeroom $homeroom;
    private User $admin;
    private int $studentCount = 7; // > 1 chunk (5) → butuh 2 batch

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->admin->assignRole('admin');

        $period = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-19', 'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'Kelas 8.1', 'grade_level' => 8]);

        $teacherUser = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'name' => 'Wali 8.1', 'nip' => '900', 'is_active' => true]);
        $this->homeroom = ClassHomeroom::create([
            'classroom_id' => $classroom->id, 'teacher_id' => $teacher->id,
            'academic_period_id' => $period->id, 'is_current' => true,
        ]);

        // Satu mapel akademik agar generate menghasilkan 1 FinalGrade per siswa.
        $subject = Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'type' => 'mandatory']);
        TeachingAssignment::create([
            'academic_period_id' => $period->id, 'teacher_id' => $teacher->id,
            'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        ]);

        // 7 siswa aktif di kelas ini.
        foreach (range(1, $this->studentCount) as $i) {
            $u = User::factory()->create(['role' => 'student']);
            $s = Student::create([
                'user_id' => $u->id, 'name' => "Siswa {$i}",
                'nisn' => "800{$i}", 'gender' => 'L', 'status' => 'active',
            ]);
            Enrollment::create([
                'student_id' => $s->id, 'classroom_id' => $classroom->id,
                'academic_period_id' => $period->id, 'status' => 'active',
            ]);
        }
    }

    /** @test */
    public function generate_narasi_processes_all_students_via_chunked_recursion(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(ViewRapor::class, ['record' => $this->homeroom->getRouteKey()]);

        // Klik aksi → antrian terisi, batch pertama di-dispatch.
        $component->callAction('generate_narasi');
        $component
            ->assertSet('isGeneratingNarasi', true)
            ->assertSet('narasiTotal', $this->studentCount)
            ->assertDispatched('generate-next-narasi-batch');

        // Batch 1: memproses 5 siswa, masih ada sisa → dispatch lagi.
        $component->call('generateNextNarasiBatch')
            ->assertSet('narasiProcessed', 5)
            ->assertSet('isGeneratingNarasi', true)
            ->assertDispatched('generate-next-narasi-batch');

        // Batch 2: memproses 2 siswa terakhir → selesai.
        $component->call('generateNextNarasiBatch')
            ->assertSet('narasiProcessed', 7)
            ->assertSet('isGeneratingNarasi', false);

        // Seluruh 7 siswa memperoleh FinalGrade narasi (7 siswa × 1 mapel).
        $this->assertDatabaseCount('final_grades', $this->studentCount);
        $this->assertEquals(
            $this->studentCount,
            FinalGrade::whereNotNull('narrative_description')->count(),
            'Semua siswa harus punya narasi setelah chunking selesai.'
        );
    }
}
