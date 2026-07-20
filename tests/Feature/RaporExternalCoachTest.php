<?php

namespace Tests\Feature;

use App\Filament\Resources\RaporResource\Pages\ViewRapor;
use App\Models\AcademicPeriod;
use App\Models\ClassHomeroom;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi null teacher_id (audit pembina ekskul eksternal).
 *
 * SK ekskul dengan teacher_id NULL + external_instructor_name masuk ke
 * ViewRapor::getViewData() (filter akademik hanya mengecualikan kokurikuler,
 * bukan ekstrakurikuler). Sebelum patch, `$ta->teacher->name` fatal
 * ("Attempt to read property name on null"). Kini pakai instructorDisplayName().
 */
class RaporExternalCoachTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_rapor_renders_external_coach_without_fatal(): void
    {
        $period = AcademicPeriod::create([
            'start_year' => 2023, 'end_year' => 2024, 'semester' => 'odd',
            'start_date' => '2023-07-01', 'end_date' => '2023-12-31', 'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => '7', 'grade_level' => 7]);

        $waliUser = User::factory()->create(['role' => 'teacher']);
        $wali = Teacher::create(['user_id' => $waliUser->id, 'name' => 'Wali Kelas 7', 'nip' => '100', 'is_active' => true]);
        $homeroom = ClassHomeroom::create([
            'classroom_id' => $classroom->id, 'teacher_id' => $wali->id,
            'academic_period_id' => $period->id, 'is_current' => true,
        ]);

        // Siswa aktif di kelas 7.
        $sUser = User::factory()->create(['role' => 'student']);
        $student = Student::create([
            'user_id' => $sUser->id, 'name' => 'Ananda Uji', 'nisn' => '3000000001',
            'gender' => 'L', 'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id,
            'academic_period_id' => $period->id, 'status' => 'active',
        ]);

        // SK akademik (guru internal) — kontrol.
        $guruUser = User::factory()->create(['role' => 'teacher']);
        $guru = Teacher::create(['user_id' => $guruUser->id, 'name' => 'Guru Matematika', 'nip' => '200', 'is_active' => true]);
        $mtk = Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'type' => 'mandatory']);
        TeachingAssignment::create([
            'academic_period_id' => $period->id, 'teacher_id' => $guru->id,
            'subject_id' => $mtk->id, 'classroom_id' => $classroom->id,
            'grading_formula' => 'average',
        ]);

        // SK EKSKUL pembina EKSTERNAL — teacher_id NULL (pemicu bug).
        $pramuka = Subject::create(['name' => 'Pramuka', 'code' => 'PRM', 'type' => 'extracurricular']);
        TeachingAssignment::create([
            'academic_period_id' => $period->id, 'teacher_id' => null,
            'external_instructor_name' => 'Kak Pembina Eksternal',
            'subject_id' => $pramuka->id, 'classroom_id' => $classroom->id,
            'grading_formula' => 'average',
        ]);

        // Panggil getViewData() langsung — tidak boleh fatal.
        $page = new ViewRapor();
        $page->record = $homeroom;
        $data = $page->getViewData();

        $this->assertIsArray($data);

        $teachers = collect($data['progressGuruMapel'])->pluck('teacher');
        // Mandate 1: ekstrakurikuler DIKECUALIKAN dari rekap progres akademik
        // (tak ada nilai numerik). Sekaligus membuktikan tak ada fatal null teacher.
        $this->assertFalse($teachers->contains('Kak Pembina Eksternal'), 'Ekskul tidak boleh masuk rekap akademik.');
        // Guru mapel akademik internal tetap tampil normal.
        $this->assertTrue($teachers->contains('Guru Matematika'));
    }
}
