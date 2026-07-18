<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\NilaiVisualisasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage metrik pemantauan Kepsek — getKinerjaGuru() (Opsi A, berbasis siswa).
 *
 * Menutup dua bug yang ditemukan pada audit Opsi A:
 *   - Persentase kelengkapan bisa > 100% saat siswa keluar tapi masih bernilai.
 *   - Mapel muatan lokal (elective) sebelumnya terlewat dari pemantauan.
 */
class KepsekMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-19', 'is_active' => true,
        ]);
    }

    private function makeTeacherAssignment(string $subjectType, string $nip): TeachingAssignment
    {
        $classroom = Classroom::create(['name' => 'Kelas 8.' . $nip, 'grade_level' => 8]);
        $tu = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::create(['user_id' => $tu->id, 'name' => "Guru {$nip}", 'nip' => $nip, 'is_active' => true]);
        $subject = Subject::create(['name' => "Mapel {$nip}", 'code' => "M{$nip}", 'type' => $subjectType]);

        return TeachingAssignment::create([
            'academic_period_id' => $this->period->id, 'teacher_id' => $teacher->id,
            'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        ]);
    }

    /** Daftarkan N siswa dengan status tertentu; kembalikan koleksi siswa. */
    private function enroll(TeachingAssignment $ta, int $count, string $status = 'active'): array
    {
        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $s = Student::create([
                'name' => 'S' . uniqid(), 'nisn' => (string) random_int(1000000000, 9999999999),
                'gender' => 'L', 'status' => 'active',
            ]);
            Enrollment::create([
                'student_id' => $s->id, 'classroom_id' => $ta->classroom_id,
                'academic_period_id' => $this->period->id, 'status' => $status,
            ]);
            $students[] = $s;
        }
        return $students;
    }

    private function grade(TeachingAssignment $ta, Student $student, float $score = 80): void
    {
        FinalGrade::create([
            'student_id' => $student->id, 'teaching_assignment_id' => $ta->id,
            'semester' => $this->period->semester, 'final_score' => $score, 'grade_label' => 'B',
        ]);
    }

    /** @test */
    public function completeness_percentage_reflects_graded_ratio(): void
    {
        $ta = $this->makeTeacherAssignment('mandatory', '101');
        $students = $this->enroll($ta, 4); // 4 siswa aktif
        $this->grade($ta, $students[0]);
        $this->grade($ta, $students[1]); // 2 dari 4 dinilai

        $row = collect(app(NilaiVisualisasiService::class)->getKinerjaGuru())
            ->firstWhere('teacher_id', $ta->teacher_id);

        $this->assertNotNull($row);
        $this->assertEquals(4, $row['total_siswa']);
        $this->assertEquals(2, $row['nilai_terisi']);
        $this->assertEquals(50.0, $row['persen_nilai']);
        $this->assertEquals('Sebagian', $row['status']);
    }

    /** @test */
    public function completeness_is_capped_at_100_percent_when_departed_students_retain_grades(): void
    {
        $ta = $this->makeTeacherAssignment('mandatory', '102');
        $active = $this->enroll($ta, 3);              // 3 siswa aktif
        $departed = $this->enroll($ta, 2, 'dropped'); // 2 siswa keluar (non-aktif)

        // SEMUA aktif dinilai + 2 siswa keluar juga masih menyimpan nilai.
        foreach ($active as $s) {
            $this->grade($ta, $s);
        }
        foreach ($departed as $s) {
            $this->grade($ta, $s);
        }

        $row = collect(app(NilaiVisualisasiService::class)->getKinerjaGuru())
            ->firstWhere('teacher_id', $ta->teacher_id);

        // Denominator hanya siswa aktif (3); graded di-cap ke 3 → tepat 100%, BUKAN 166%.
        $this->assertEquals(3, $row['total_siswa']);
        $this->assertEquals(3, $row['nilai_terisi'], 'Nilai siswa yang sudah keluar tidak boleh menggelembungkan angka.');
        $this->assertEquals(100.0, $row['persen_nilai']);
        $this->assertLessThanOrEqual(100.0, $row['persen_nilai']);
        $this->assertEquals('Lengkap', $row['status']);
    }

    /** @test */
    public function muatan_lokal_elective_teachers_are_monitored(): void
    {
        $ta = $this->makeTeacherAssignment('elective', '103'); // Muatan Lokal
        $this->enroll($ta, 2);

        $row = collect(app(NilaiVisualisasiService::class)->getKinerjaGuru())
            ->firstWhere('teacher_id', $ta->teacher_id);

        $this->assertNotNull($row, 'Guru muatan lokal (elective) harus ikut terpantau.');
        $this->assertEquals(2, $row['total_siswa']);
    }

    /** @test */
    public function kokurikuler_teachers_are_excluded_from_grade_monitoring(): void
    {
        $ta = $this->makeTeacherAssignment('kokurikuler', '104'); // P5 - naratif
        $this->enroll($ta, 2);

        $row = collect(app(NilaiVisualisasiService::class)->getKinerjaGuru())
            ->firstWhere('teacher_id', $ta->teacher_id);

        $this->assertNull($row, 'Guru P5 (kokurikuler) tidak diukur dengan metrik nilai angka.');
    }

    /** @test */
    public function returns_empty_when_no_active_period(): void
    {
        $this->period->update(['is_active' => false]);

        $this->assertSame([], app(NilaiVisualisasiService::class)->getKinerjaGuru());
    }
}
