<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\TeachingAssignment;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\LearningObjective;
use App\Services\DescriptionGeneratorService;

/**
 * Unit Test untuk DescriptionGeneratorService::calculateScorePerTp().
 *
 * Memastikan:
 * 1. Basis skor per-TP hanya dari asesmen SUMATIF (formatif tidak mencampuri).
 * 2. Booster formatif (weight/point) diterapkan konsisten dengan calculateFinalGrade.
 * 3. Skor per-TP dibatasi maksimal 100.
 *
 * @see \App\Services\DescriptionGeneratorService::calculateScorePerTp()
 */
class CalculateScorePerTpBoosterTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private AcademicPeriod $academicPeriod;
    private Classroom $classroom;
    private Subject $subject;
    private Teacher $teacher;
    private LearningObjective $lo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academicPeriod = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-20', 'is_active' => true,
        ]);
        $this->classroom = Classroom::create(['name' => 'Kelas 7.1', 'grade_level' => 7]);
        $this->subject = Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'type' => 'mandatory']);
        $this->teacher = Teacher::create(['name' => 'Budi Santoso', 'gender' => 'L']);
        $this->student = Student::create(['name' => 'Andi Prasetyo', 'gender' => 'L']);

        $this->lo = LearningObjective::create([
            'subject_id'         => $this->subject->id,
            'academic_period_id' => $this->academicPeriod->id,
            'phase'              => 'D',
            'attribute'          => 'Aljabar',
            'code'               => 'MTK-7-1-TP1',
            'content'            => 'Memahami operasi aljabar dasar',
        ]);
    }

    // ─── HELPERS ──────────────────────────────────────────────────────

    private function createAssignment(string $boosterMode = 'none', float $boosterValue = 0): TeachingAssignment
    {
        return TeachingAssignment::create([
            'academic_period_id' => $this->academicPeriod->id,
            'teacher_id'         => $this->teacher->id,
            'subject_id'         => $this->subject->id,
            'classroom_id'       => $this->classroom->id,
            'grading_formula'    => 'average',
            'kktp'               => 75,
            'booster_mode'       => $boosterMode,
            'booster_value'      => $boosterValue,
        ]);
    }

    /**
     * Buat asesmen, tautkan ke TP ($this->lo), dan isi nilai siswa.
     */
    private function attachAssessment(TeachingAssignment $assignment, string $category, ?int $score): Assessment
    {
        $assessment = Assessment::create([
            'teaching_assignment_id' => $assignment->id,
            'name'                   => 'Asesmen ' . rand(1, 9999),
            'category'               => $category,
            'technique'              => 'tes_tertulis',
            'weight'                 => 0,
            'date'                   => now(),
        ]);

        $assessment->learningObjectives()->attach($this->lo->id);

        Grade::withoutEvents(function () use ($assessment, $score) {
            Grade::create([
                'assessment_id' => $assessment->id,
                'student_id'    => $this->student->id,
                'score'         => $score,
            ]);
        });

        return $assessment;
    }

    private function scorePerTp(TeachingAssignment $assignment): float
    {
        $service = new DescriptionGeneratorService();
        $tp = $service->calculateScorePerTp($assignment, $this->student->id, 75)
            ->firstWhere('id', $this->lo->id);

        return (float) $tp['average_score'];
    }

    // ─── TEST CASES ───────────────────────────────────────────────────

    /**
     * Basis per-TP hanya sumatif; formatif diabaikan saat booster none.
     * Sumatif 80 & 60 → 70. Formatif 100 TIDAK menurunkan (dulu jadi (80+60+100)/3 = 80).
     */
    public function test_base_excludes_formative_when_booster_none(): void
    {
        $assignment = $this->createAssignment('none');
        $this->attachAssessment($assignment, 'sumatif_lingkup_materi', 80);
        $this->attachAssessment($assignment, 'sumatif_akhir_semester', 60);
        $this->attachAssessment($assignment, 'formatif_poin', 100); // harus diabaikan

        $this->assertEquals(70.0, $this->scorePerTp($assignment));
    }

    /**
     * Mode weight: 70 + (100 × 10%) = 80.
     */
    public function test_weight_mode_adds_booster_per_tp(): void
    {
        $assignment = $this->createAssignment('weight', 10);
        $this->attachAssessment($assignment, 'sumatif_lingkup_materi', 80);
        $this->attachAssessment($assignment, 'sumatif_akhir_semester', 60);
        $this->attachAssessment($assignment, 'formatif_deskripsi', 100);

        $this->assertEquals(80.0, $this->scorePerTp($assignment));
    }

    /**
     * Mode point: 70 + (1 formatif terisi × 2) = 72.
     */
    public function test_point_mode_adds_booster_per_tp(): void
    {
        $assignment = $this->createAssignment('point', 2);
        $this->attachAssessment($assignment, 'sumatif_lingkup_materi', 80);
        $this->attachAssessment($assignment, 'sumatif_akhir_semester', 60);
        $this->attachAssessment($assignment, 'formatif_poin', 1);

        $this->assertEquals(72.0, $this->scorePerTp($assignment));
    }

    /**
     * Skor per-TP dibatasi 100. 95 + (100 × 20%) = 115 → cap 100.
     */
    public function test_score_per_tp_capped_at_100(): void
    {
        $assignment = $this->createAssignment('weight', 20);
        $this->attachAssessment($assignment, 'sumatif_lingkup_materi', 95);
        $this->attachAssessment($assignment, 'sumatif_akhir_semester', 95);
        $this->attachAssessment($assignment, 'formatif_deskripsi', 100);

        $this->assertEquals(100.0, $this->scorePerTp($assignment));
    }
}
