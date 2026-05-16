<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\GradeRange;
use App\Models\LearningObjective;
use App\Models\NarrativeTemplate;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\DescriptionGeneratorService;
use App\Services\GradeRangeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NarrativeEngineTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $period;
    private Classroom $classroom;
    private Subject $subject;
    private Teacher $teacher;
    private Student $student;
    private TeachingAssignment $assignment;
    private DescriptionGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-19', 'is_active' => true,
        ]);

        $this->classroom = Classroom::create(['name' => 'Kelas 7A', 'grade_level' => 7]);
        $this->subject = Subject::create(['name' => 'Matematika', 'code' => 'MTK']);

        $teacherUser = User::factory()->create([
            'name' => 'Guru Test', 'role' => 'teacher', 'is_active' => true,
        ]);

        $this->teacher = Teacher::create([
            'user_id' => $teacherUser->id, 'name' => 'Guru Test',
            'nip' => '198001012005011001', 'is_active' => true,
        ]);

        $studentUser = User::factory()->create([
            'name' => 'Siswa Test', 'role' => 'student', 'is_active' => true,
        ]);

        $this->student = Student::create([
            'user_id' => $studentUser->id, 'name' => 'Siswa Test',
            'nis' => '12345', 'nisn' => '0012345678',
            'classroom_id' => $this->classroom->id,
            'gender' => 'L',
            'birth_date' => '2010-01-01',
        ]);

        $this->assignment = TeachingAssignment::create([
            'academic_period_id' => $this->period->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'kktp' => 75,
        ]);

        $this->service = new DescriptionGeneratorService();
    }

    /**
     * Helper: buat TP, Assessment, dan Grade untuk skor tertentu.
     */
    private function createTpWithScore(string $code, string $attribute, int $score): LearningObjective
    {
        $tp = LearningObjective::create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'academic_period_id' => $this->period->id,
            'grade_level' => 7,
            'code' => $code,
            'content' => "Deskripsi lengkap {$attribute}",
            'attribute' => $attribute,
        ]);

        $assessment = Assessment::create([
            'teaching_assignment_id' => $this->assignment->id,
            'name' => "UH {$code}",
            'category' => 'sumatif_lingkup_materi',
            'technique' => 'tes_tertulis',
            'date' => now(),
        ]);

        $assessment->learningObjectives()->attach($tp->id);

        Grade::create([
            'assessment_id' => $assessment->id,
            'student_id' => $this->student->id,
            'score' => $score,
        ]);

        return $tp;
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 1: Fallback hierarchy (Teacher → Admin → Hardcoded)
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_template_fallback_returns_admin_default_when_no_teacher_override(): void
    {
        $template = NarrativeTemplate::getTemplate('A', $this->assignment->id);

        // Harus mengembalikan admin default (belum ada override guru)
        $this->assertStringContains('[TP]', $template);
        $this->assertStringContains('sangat baik', $template);
    }

    /** @test */
    public function test_template_fallback_prefers_teacher_override_over_admin_default(): void
    {
        // Buat override guru
        NarrativeTemplate::create([
            'grade_letter' => 'A',
            'template_text' => 'Siswa sangat mahir dalam [TP] di kelas ini.',
            'is_default' => false,
            'teaching_assignment_id' => $this->assignment->id,
        ]);

        $template = NarrativeTemplate::getTemplate('A', $this->assignment->id);

        // Harus mengembalikan template guru, bukan admin
        $this->assertStringContains('sangat mahir', $template);
        $this->assertStringNotContains('sangat baik', $template);
    }

    /** @test */
    public function test_template_fallback_to_hardcoded_when_db_empty(): void
    {
        // Hapus semua template dari DB
        NarrativeTemplate::query()->delete();

        $template = NarrativeTemplate::getTemplate('A');

        // Harus tetap mengembalikan sesuatu (hardcoded fallback)
        $this->assertNotEmpty($template);
        $this->assertStringContains('[TP]', $template);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 2: Placeholder [TP] replacement
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_placeholder_replacement_single_tp(): void
    {
        $this->createTpWithScore('TP.7.1', 'aljabar', 95);

        $narrative = $this->service->generate($this->assignment, $this->student->id);

        // [TP] harus diganti dengan "aljabar"
        $this->assertStringContains('aljabar', $narrative);
        $this->assertStringNotContains('[TP]', $narrative);
    }

    /** @test */
    public function test_placeholder_replacement_multiple_tps_same_grade(): void
    {
        // 2 TP dengan grade yang sama (keduanya A, ≥91)
        $this->createTpWithScore('TP.7.1', 'aljabar', 95);
        $this->createTpWithScore('TP.7.2', 'geometri', 93);

        $narrative = $this->service->generate($this->assignment, $this->student->id);

        // Harus mengandung kedua TP digabung dengan "dan"
        $this->assertStringContains('aljabar', $narrative);
        $this->assertStringContains('geometri', $narrative);
        $this->assertStringContains('dan', $narrative);
        $this->assertStringNotContains('[TP]', $narrative);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 3: Max/Min Conjunction Logic
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_conjunction_serta_when_min_grade_is_passing(): void
    {
        // Max=A (95), Min=C (76) — keduanya lulus
        $this->createTpWithScore('TP.7.1', 'aljabar', 95);
        $this->createTpWithScore('TP.7.2', 'statistika', 76);

        $narrative = $this->service->generate($this->assignment, $this->student->id);

        // Harus menggunakan ", serta " (kedua grade lulus)
        $this->assertStringContains(', serta ', $narrative);
    }

    /** @test */
    public function test_conjunction_namun_when_max_passing_min_failing(): void
    {
        // Max=A (95), Min=D (65) — A lulus, D gagal
        $this->createTpWithScore('TP.7.1', 'aljabar', 95);
        $this->createTpWithScore('TP.7.2', 'statistika', 65);

        $narrative = $this->service->generate($this->assignment, $this->student->id);

        // Harus menggunakan ", namun " (kontras lulus vs gagal)
        $this->assertStringContains(', namun ', $narrative);
    }

    /** @test */
    public function test_conjunction_dan_juga_when_both_failing(): void
    {
        // Max=D (65), Min=E (30) — keduanya gagal
        $this->createTpWithScore('TP.7.1', 'aljabar', 65);
        $this->createTpWithScore('TP.7.2', 'statistika', 30);

        $narrative = $this->service->generate($this->assignment, $this->student->id);

        // Harus menggunakan ", dan juga " (keduanya gagal)
        $this->assertStringContains(', dan juga ', $narrative);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 4: combineAttributes logic
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_combine_attributes_single(): void
    {
        $result = $this->service->combineAttributes(['aljabar']);
        $this->assertEquals('aljabar', $result);
    }

    /** @test */
    public function test_combine_attributes_two(): void
    {
        $result = $this->service->combineAttributes(['aljabar', 'geometri']);
        $this->assertEquals('aljabar dan geometri', $result);
    }

    /** @test */
    public function test_combine_attributes_three_or_more(): void
    {
        $result = $this->service->combineAttributes(['aljabar', 'geometri', 'statistika']);
        $this->assertEquals('aljabar, geometri, dan statistika', $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 5: resolveConjunction unit tests
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_resolve_conjunction_both_passing(): void
    {
        $this->assertEquals(', serta ', $this->service->resolveConjunction('A', 'B'));
        $this->assertEquals(', serta ', $this->service->resolveConjunction('A', 'C'));
        $this->assertEquals(', serta ', $this->service->resolveConjunction('B', 'C'));
    }

    /** @test */
    public function test_resolve_conjunction_max_passing_min_failing(): void
    {
        $this->assertEquals(', namun ', $this->service->resolveConjunction('A', 'D'));
        $this->assertEquals(', namun ', $this->service->resolveConjunction('B', 'E'));
        $this->assertEquals(', namun ', $this->service->resolveConjunction('C', 'D'));
    }

    /** @test */
    public function test_resolve_conjunction_both_failing(): void
    {
        $this->assertEquals(', dan juga ', $this->service->resolveConjunction('D', 'E'));
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 6: Default narrative when no TPs
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_default_narrative_when_no_tp_data(): void
    {
        $this->assignment->load('subject');
        $narrative = $this->service->generate($this->assignment, $this->student->id);

        $this->assertStringContains('Matematika', $narrative);
        $this->assertStringContains('mengikuti pembelajaran', $narrative);
    }

    // ═══════════════════════════════════════════════════════════════
    // CUSTOM ASSERTION HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'"
        );
    }

    private function assertStringNotContains(string $needle, string $haystack): void
    {
        $this->assertFalse(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' does NOT contain '{$needle}'"
        );
    }
}
