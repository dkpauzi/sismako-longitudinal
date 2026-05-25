<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\FinalGrade;
use App\Models\Grade;
use App\Models\GradeRange;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\GradeRangeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemedialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private TeachingAssignment $assignment;
    private Student $student;
    private Assessment $assessment;

    protected function setUp(): void
    {
        parent::setUp();

        // --- Setup data dasar ---
        $teacherUser = User::factory()->create(['role' => 'teacher', 'username' => 'guru_test']);
        $studentUser = User::factory()->create(['role' => 'student', 'username' => 'siswa_test']);
        $guardianUser = User::factory()->create(['role' => 'guardian', 'username' => 'wali_test']);

        $academicPeriod = AcademicPeriod::create([
            'start_year' => 2026, 'end_year' => 2027, 'semester' => 'odd',
            'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);

        $subject = Subject::create(['name' => 'Matematika', 'code' => 'MTK']);
        $classroom = Classroom::create(['name' => '7A', 'grade_level' => 7]);

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id, 'nip' => '1234567890',
            'name' => 'Guru Test', 'phone' => '08123',
        ]);

        $this->student = Student::create([
            'user_id' => $studentUser->id, 'guardian_user_id' => $guardianUser->id,
            'nisn' => '9999888877', 'name' => 'Siswa Test', 'gender' => 'L', 'status' => 'active',
        ]);

        $this->assignment = TeachingAssignment::create([
            'academic_period_id' => $academicPeriod->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'grading_formula' => 'average',
            'kktp' => 75,
        ]);

        // Seed grade ranges agar GradeRangeResolver berfungsi
        GradeRangeResolver::seedDefaults($this->assignment);

        $this->assessment = Assessment::create([
            'teaching_assignment_id' => $this->assignment->id,
            'name' => 'UH 1', 'category' => 'sumatif_lingkup_materi',
            'technique' => 'tes_tertulis', 'date' => now(), 'weight' => 100,
        ]);
    }

    /**
     * Test: Remedial preserves original_score and updates score correctly.
     */
    public function test_remedial_preserves_original_score_and_updates_score(): void
    {
        // 1. Input nilai awal (di bawah KKTP 75)
        $grade = Grade::create([
            'assessment_id' => $this->assessment->id,
            'student_id' => $this->student->id,
            'score' => 60,
        ]);

        // Verify: original_score belum terisi, is_remedial falsy
        $grade->refresh(); // Reload from DB to get defaults
        $this->assertNull($grade->original_score);
        $this->assertFalse($grade->is_remedial);

        // 2. Simulasikan remedial: simpan nilai asli, update dengan nilai baru
        $grade->update([
            'original_score' => $grade->score,
            'score' => 80,
            'is_remedial' => true,
        ]);

        $grade->refresh();

        // 3. Assertions
        $this->assertEquals(60, $grade->original_score);  // Nilai asli tersimpan
        $this->assertEquals(80, $grade->score);            // Nilai baru terupdate
        $this->assertTrue($grade->is_remedial);            // Flag remedial aktif
    }

    /**
     * Test: Remedial update triggers GradeObserver -> recalculates FinalGrade + A-E label.
     */
    public function test_remedial_triggers_grade_recalculation_pipeline(): void
    {
        // 1. Input nilai awal 60 (D grade, di bawah KKTP 75)
        $grade = Grade::create([
            'assessment_id' => $this->assessment->id,
            'student_id' => $this->student->id,
            'score' => 60,
        ]);

        // GradeObserver should have created FinalGrade
        $finalGrade = FinalGrade::where('student_id', $this->student->id)
            ->where('teaching_assignment_id', $this->assignment->id)
            ->where('semester', 'odd')
            ->first();

        $this->assertNotNull($finalGrade);
        $this->assertEquals(60, $finalGrade->final_score);
        $this->assertEquals('D', $finalGrade->grade_label); // 60 < KKTP 75 = D

        // 2. Update dengan nilai remedial 85
        $grade->update([
            'original_score' => $grade->score,
            'score' => 85,
            'is_remedial' => true,
        ]);

        // 3. FinalGrade harus ter-recalculate oleh GradeObserver
        $finalGrade->refresh();

        $this->assertEquals(85, $finalGrade->final_score);
        // 85 >= KKTP 75 = B atau C tergantung range. Pastikan bukan D/E.
        $this->assertContains($finalGrade->grade_label, ['A', 'B', 'C']);
    }

    /**
     * Test: Double remedial does NOT overwrite the first original_score.
     */
    public function test_double_remedial_preserves_first_original_score(): void
    {
        $grade = Grade::create([
            'assessment_id' => $this->assessment->id,
            'student_id' => $this->student->id,
            'score' => 50,
        ]);

        // Remedial 1: 50 -> 65
        $grade->update([
            'original_score' => $grade->original_score ?? $grade->score,
            'score' => 65,
            'is_remedial' => true,
        ]);

        // Remedial 2: 65 -> 80 (original_score HARUS tetap 50, bukan 65)
        $grade->refresh();
        $grade->update([
            'original_score' => $grade->original_score ?? $grade->score,
            'score' => 80,
            'is_remedial' => true,
        ]);

        $grade->refresh();

        $this->assertEquals(50, $grade->original_score); // Tetap nilai pertama
        $this->assertEquals(80, $grade->score);           // Nilai terbaru
        $this->assertTrue($grade->is_remedial);
    }
}
