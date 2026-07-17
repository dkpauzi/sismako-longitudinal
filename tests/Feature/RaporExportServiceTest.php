<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AttendanceSummary;
use App\Models\Classroom;
use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use App\Models\KokurikulerGrade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\RaporExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage untuk RaporExportService::getRaporData() (Audit 3.7).
 *
 * Memastikan rekap rapor tidak terkontaminasi data tahun ajaran lampau:
 * seorang siswa yang naik dari kelas 7 (2024/2025 Ganjil) ke kelas 8
 * (2025/2026 Ganjil) — dua-duanya semester "odd" — rapor kelas 8-nya
 * TIDAK boleh menjumlahkan absensi/P5 milik kelas 7.
 */
class RaporExportServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function rapor_data_does_not_leak_across_academic_periods(): void
    {
        Role::create(['name' => 'teacher', 'guard_name' => 'web']);

        // ── Dua tahun ajaran, keduanya semester GANJIL (paritas sama) ──
        $periodGrade7 = AcademicPeriod::create([
            'start_year' => 2024, 'end_year' => 2025, 'semester' => 'odd',
            'start_date' => '2024-07-15', 'end_date' => '2024-12-20', 'is_active' => false,
        ]);
        $periodGrade8 = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-19', 'is_active' => true,
        ]);

        $class7 = Classroom::create(['name' => 'Kelas 7.1', 'grade_level' => 7]);
        $class8 = Classroom::create(['name' => 'Kelas 8.1', 'grade_level' => 8]);

        // ── Siswa yang sama, terdaftar di kedua periode ──
        $studentUser = User::factory()->create(['name' => 'Ananda Longitudinal', 'role' => 'student']);
        $student = Student::create([
            'user_id' => $studentUser->id, 'name' => 'Ananda Longitudinal',
            'nisn' => '1010101010', 'gender' => 'P', 'status' => 'active',
        ]);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $class7->id,
            'academic_period_id' => $periodGrade7->id, 'status' => 'promoted',
        ]);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $class8->id,
            'academic_period_id' => $periodGrade8->id, 'status' => 'active',
        ]);

        // ── Wali kelas 8 (anchor getRaporData adalah ClassHomeroom) ──
        $waliUser = User::factory()->create(['name' => 'Wali Kelas 8', 'role' => 'teacher']);
        $waliUser->assignRole('teacher');
        $wali = Teacher::create(['user_id' => $waliUser->id, 'name' => 'Wali Kelas 8', 'nip' => '900', 'is_active' => true]);
        $homeroom8 = ClassHomeroom::create([
            'classroom_id' => $class8->id, 'teacher_id' => $wali->id,
            'academic_period_id' => $periodGrade8->id, 'is_current' => true,
        ]);

        // ── SK Mengajar per periode (untuk mengikat AttendanceSummary ke periode) ──
        $subject = Subject::create(['name' => 'IPA', 'code' => 'IPA', 'type' => 'mandatory']);
        $ta7 = TeachingAssignment::create([
            'academic_period_id' => $periodGrade7->id, 'teacher_id' => $wali->id,
            'subject_id' => $subject->id, 'classroom_id' => $class7->id,
        ]);
        $ta8 = TeachingAssignment::create([
            'academic_period_id' => $periodGrade8->id, 'teacher_id' => $wali->id,
            'subject_id' => $subject->id, 'classroom_id' => $class8->id,
        ]);

        // ── ABSENSI: kelas 7 (lampau) besar, kelas 8 (kini) kecil ──
        AttendanceSummary::create([
            'student_id' => $student->id, 'teaching_assignment_id' => $ta7->id,
            'semester' => 'odd', 'present' => 80, 'permit' => 3, 'sick' => 5, 'alpha' => 2,
        ]);
        AttendanceSummary::create([
            'student_id' => $student->id, 'teaching_assignment_id' => $ta8->id,
            'semester' => 'odd', 'present' => 90, 'permit' => 1, 'sick' => 1, 'alpha' => 0,
        ]);

        // ── P5/KOKURIKULER: 1 projek di kelas 7, DUA projek di kelas 8 ──
        KokurikulerGrade::create([
            'student_id' => $student->id, 'academic_period_id' => $periodGrade7->id,
            'project_title' => 'P5 Kelas 7 (LAMPAU)', 'narrative_description' => 'Tidak boleh muncul.',
        ]);
        KokurikulerGrade::create([
            'student_id' => $student->id, 'academic_period_id' => $periodGrade8->id,
            'project_title' => 'P5 Kelas 8 - Projek A', 'narrative_description' => 'Projek pertama.',
        ]);
        KokurikulerGrade::create([
            'student_id' => $student->id, 'academic_period_id' => $periodGrade8->id,
            'project_title' => 'P5 Kelas 8 - Projek B', 'narrative_description' => 'Projek kedua.',
        ]);

        // ── ACT: ambil data rapor untuk KELAS 8 ──
        $data = app(RaporExportService::class)->getRaporData($homeroom8, $student->id);

        // ── ASSERT ABSENSI: hanya angka kelas 8, BUKAN akumulasi kelas 7 ──
        $this->assertEquals(1, $data['totalSakit'], 'Sakit harus 1 (kelas 8), bukan 6 (akumulasi).');
        $this->assertEquals(1, $data['totalIzin'], 'Izin harus 1 (kelas 8), bukan 4 (akumulasi).');
        $this->assertEquals(0, $data['totalAlpha'], 'Alpha harus 0 (kelas 8), bukan 2 (akumulasi).');

        // ── ASSERT P5: hanya dua projek kelas 8, projek kelas 7 tidak bocor ──
        $this->assertCount(2, $data['kokurikulerGrades'], 'Harus 2 projek P5 kelas 8 (multi-projek didukung).');
        $titles = $data['kokurikulerGrades']->pluck('project_title');
        $this->assertTrue($titles->contains('P5 Kelas 8 - Projek A'));
        $this->assertTrue($titles->contains('P5 Kelas 8 - Projek B'));
        $this->assertFalse($titles->contains('P5 Kelas 7 (LAMPAU)'), 'Projek P5 kelas 7 tidak boleh bocor ke rapor kelas 8.');
    }
}
