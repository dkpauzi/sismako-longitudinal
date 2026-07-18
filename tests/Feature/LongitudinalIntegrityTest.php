<?php

namespace Tests\Feature;

use App\Filament\Pages\StudentPromotionWizard;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Models\KokurikulerGrade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Policies\KokurikulerGradePolicy;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage proteksi Integritas Longitudinal (Step 6):
 * - Grade lock global via FinalGrade::snapshot() (Audit 3.6).
 * - Sibling check saat kelulusan wali (Audit 3.2).
 * - Gerbang jenjang Promotion Wizard (Audit 3.8).
 */
class LongitudinalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(): TeachingAssignment
    {
        $period = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-19', 'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'Kelas 8.1', 'grade_level' => 8]);
        $teacherUser = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::create(['user_id' => $teacherUser->id, 'name' => 'Guru', 'nip' => '1', 'is_active' => true]);
        $subject = Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'type' => 'mandatory']);

        return TeachingAssignment::create([
            'academic_period_id' => $period->id, 'teacher_id' => $teacher->id,
            'subject_id' => $subject->id, 'classroom_id' => $classroom->id,
        ]);
    }

    // ── AUDIT 3.6: GRADE LOCK ─────────────────────────────────────────

    /** @test */
    public function snapshot_refuses_to_overwrite_a_locked_grade(): void
    {
        $ta = $this->makeAssignment();
        $student = Student::create(['name' => 'A', 'nisn' => '111', 'gender' => 'L', 'status' => 'active']);

        FinalGrade::create([
            'student_id' => $student->id, 'teaching_assignment_id' => $ta->id,
            'semester' => 'odd', 'final_score' => 90, 'grade_label' => 'A', 'is_locked' => true,
        ]);

        // Percobaan menimpa lewat pintu tulis tunggal.
        FinalGrade::snapshot($student->id, $ta->id, 'odd', ['final_score' => 40, 'grade_label' => 'E']);

        $fresh = FinalGrade::where('student_id', $student->id)->first();
        $this->assertEquals(90.00, $fresh->final_score, 'Nilai terkunci tidak boleh berubah.');
        $this->assertEquals('A', $fresh->grade_label);
    }

    /** @test */
    public function snapshot_refuses_to_overwrite_a_manual_override_grade(): void
    {
        $ta = $this->makeAssignment();
        $student = Student::create(['name' => 'B', 'nisn' => '222', 'gender' => 'L', 'status' => 'active']);

        FinalGrade::create([
            'student_id' => $student->id, 'teaching_assignment_id' => $ta->id,
            'semester' => 'odd', 'final_score' => 88, 'grade_label' => 'A', 'is_manual_override' => true,
        ]);

        FinalGrade::snapshot($student->id, $ta->id, 'odd', ['final_score' => 55, 'grade_label' => 'C']);

        $fresh = FinalGrade::where('student_id', $student->id)->first();
        $this->assertEquals(88.00, $fresh->final_score, 'Override manual admin tidak boleh ditimpa kalkulasi.');
    }

    /** @test */
    public function snapshot_writes_normally_when_not_locked(): void
    {
        $ta = $this->makeAssignment();
        $student = Student::create(['name' => 'C', 'nisn' => '333', 'gender' => 'L', 'status' => 'active']);

        FinalGrade::snapshot($student->id, $ta->id, 'odd', ['final_score' => 75, 'grade_label' => 'B']);

        $this->assertDatabaseHas('final_grades', [
            'student_id' => $student->id, 'final_score' => 75, 'grade_label' => 'B',
        ]);
    }

    // ── AUDIT 3.2: SIBLING GUARDIAN ───────────────────────────────────

    /** @test */
    public function graduating_student_keeps_guardian_active_if_sibling_still_studies(): void
    {
        $period = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'even',
            'start_date' => '2026-01-05', 'end_date' => '2026-06-20', 'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'Kelas 9.1', 'grade_level' => 9]);

        $guardian = User::factory()->create(['role' => 'guardian', 'is_active' => true]);

        // Kakak (lulus) + adik (masih aktif), wali sama.
        $kakakUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $kakak = Student::create([
            'user_id' => $kakakUser->id, 'guardian_user_id' => $guardian->id,
            'name' => 'Kakak', 'nisn' => '900', 'gender' => 'L', 'status' => 'active',
        ]);
        Student::create([
            'guardian_user_id' => $guardian->id,
            'name' => 'Adik', 'nisn' => '901', 'gender' => 'P', 'status' => 'active',
        ]);

        $enrollment = Enrollment::create([
            'student_id' => $kakak->id, 'classroom_id' => $classroom->id,
            'academic_period_id' => $period->id, 'status' => 'active',
        ]);

        app(PromotionService::class)->processBatchPromotions(
            [['enrollment_id' => $enrollment->id, 'action' => 'graduated', 'target_classroom_id' => null]],
            null
        );

        $this->assertTrue($guardian->fresh()->is_active, 'Wali harus tetap aktif karena adik masih sekolah.');
        $this->assertFalse($kakakUser->fresh()->is_active, 'Akun siswa yang lulus dinonaktifkan.');
    }

    /** @test */
    public function graduating_last_child_deactivates_guardian(): void
    {
        $period = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'even',
            'start_date' => '2026-01-05', 'end_date' => '2026-06-20', 'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'Kelas 9.2', 'grade_level' => 9]);

        $guardian = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        $anakUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $anak = Student::create([
            'user_id' => $anakUser->id, 'guardian_user_id' => $guardian->id,
            'name' => 'Anak Tunggal', 'nisn' => '950', 'gender' => 'L', 'status' => 'active',
        ]);
        $enrollment = Enrollment::create([
            'student_id' => $anak->id, 'classroom_id' => $classroom->id,
            'academic_period_id' => $period->id, 'status' => 'active',
        ]);

        app(PromotionService::class)->processBatchPromotions(
            [['enrollment_id' => $enrollment->id, 'action' => 'graduated', 'target_classroom_id' => null]],
            null
        );

        $this->assertFalse($guardian->fresh()->is_active, 'Wali tanpa anak aktif lain dinonaktifkan.');
    }

    // ── AUDIT 3.8: GERBANG JENJANG ────────────────────────────────────

    /** @test */
    public function grade_7_cannot_graduate_and_grade_9_cannot_be_promoted(): void
    {
        $this->assertEquals(
            ['promoted', 'retained'],
            array_keys(StudentPromotionWizard::actionOptionsForGrade(7)),
            'Kelas 7 tidak boleh punya opsi Lulus.'
        );
        $this->assertEquals(
            ['retained', 'graduated'],
            array_keys(StudentPromotionWizard::actionOptionsForGrade(9)),
            'Kelas 9 tidak boleh punya opsi Naik Kelas.'
        );
    }

    /** @test */
    public function promotion_target_is_restricted_to_next_grade_only(): void
    {
        Classroom::create(['name' => '7A', 'grade_level' => 7]);
        $grade8 = Classroom::create(['name' => '8A', 'grade_level' => 8]);
        Classroom::create(['name' => '9A', 'grade_level' => 9]);

        // Siswa kelas 7 dipromosikan → hanya kelas grade 8 yang valid.
        $options = StudentPromotionWizard::targetClassroomOptions(7, 'promoted');
        $this->assertEquals([$grade8->id => '8A'], $options, 'Kelas 7 hanya boleh naik ke grade 8.');

        // Kelas 7 tinggal kelas → hanya grade 7.
        $retainOptions = StudentPromotionWizard::targetClassroomOptions(7, 'retained');
        $this->assertEquals(['7A'], array_values($retainOptions));
    }

    // ── AUDIT MED-3: KEPEMILIKAN NILAI P5 (KOKURIKULER) ───────────────

    /** @test */
    public function only_active_homeroom_teacher_may_modify_p5_grade(): void
    {
        foreach (['teacher'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $period = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-19', 'is_active' => true,
        ]);
        $classroom = Classroom::create(['name' => 'Kelas 8.1', 'grade_level' => 8]);
        $student = Student::create(['name' => 'Murid P5', 'nisn' => '5050', 'gender' => 'L', 'status' => 'active']);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id,
            'academic_period_id' => $period->id, 'status' => 'active',
        ]);
        $p5 = KokurikulerGrade::create([
            'student_id' => $student->id, 'academic_period_id' => $period->id,
            'project_title' => 'Gaya Hidup Berkelanjutan', 'narrative_description' => 'Baik.',
        ]);

        // Wali kelas AKTIF kelas 8.1
        $waliUser = User::factory()->create(['role' => 'teacher']);
        $waliUser->assignRole('teacher');
        $wali = Teacher::create(['user_id' => $waliUser->id, 'name' => 'Wali 8.1', 'nip' => '111', 'is_active' => true]);
        ClassHomeroom::create([
            'classroom_id' => $classroom->id, 'teacher_id' => $wali->id,
            'academic_period_id' => $period->id, 'is_current' => true,
        ]);

        // Guru lain (bukan wali kelas siswa ini)
        $lainUser = User::factory()->create(['role' => 'teacher']);
        $lainUser->assignRole('teacher');
        Teacher::create(['user_id' => $lainUser->id, 'name' => 'Guru Lain', 'nip' => '222', 'is_active' => true]);

        $policy = new KokurikulerGradePolicy();

        $this->assertTrue($policy->update($waliUser, $p5), 'Wali kelas aktif boleh mengubah P5.');
        $this->assertTrue($policy->delete($waliUser, $p5), 'Wali kelas aktif boleh menghapus P5.');
        $this->assertFalse($policy->update($lainUser, $p5), 'Guru non-wali TIDAK boleh mengubah P5 lintas kelas.');
        $this->assertFalse($policy->delete($lainUser, $p5), 'Guru non-wali TIDAK boleh menghapus P5 lintas kelas.');
    }
}
