<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\GradeRange;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\GradeRangeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeRangeTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $period;
    private Classroom $classroom;
    private Subject $subject;
    private Teacher $teacher;

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
    }

    /**
     * Helper: membuat TeachingAssignment dengan KKTP tertentu.
     */
    private function createAssignment(int $kktp = 75): TeachingAssignment
    {
        return TeachingAssignment::create([
            'academic_period_id' => $this->period->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'kktp' => $kktp,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 1: KKTP anchor — C.min HARUS = KKTP
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_kktp_anchors_grade_c_as_minimum_score(): void
    {
        $defaults = GradeRangeResolver::calculateDefaultRanges(75);

        // C.min HARUS = KKTP
        $this->assertEquals(75.0, $defaults['C']['min_score'], 'C.min harus = KKTP');

        // A, B, C semua >= KKTP (LULUS)
        $this->assertGreaterThanOrEqual(75, $defaults['A']['min_score'], 'A.min harus >= KKTP');
        $this->assertGreaterThanOrEqual(75, $defaults['B']['min_score'], 'B.min harus >= KKTP');
        $this->assertGreaterThanOrEqual(75, $defaults['C']['min_score'], 'C.min harus >= KKTP');

        // D, E semua < KKTP (TIDAK LULUS)
        $this->assertLessThan(75, $defaults['D']['max_score'], 'D.max harus < KKTP');
        $this->assertLessThan(75, $defaults['E']['max_score'], 'E.max harus < KKTP');
    }

    /** @test */
    public function test_kktp_anchor_works_with_different_kktp_values(): void
    {
        // KKTP = 70
        $ranges70 = GradeRangeResolver::calculateDefaultRanges(70);
        $this->assertEquals(70.0, $ranges70['C']['min_score'], 'C.min harus = 70 jika KKTP=70');
        $this->assertLessThan(70, $ranges70['D']['max_score']);

        // KKTP = 80
        $ranges80 = GradeRangeResolver::calculateDefaultRanges(80);
        $this->assertEquals(80.0, $ranges80['C']['min_score'], 'C.min harus = 80 jika KKTP=80');
        $this->assertLessThan(80, $ranges80['D']['max_score']);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 2: Auto-seed saat TeachingAssignment dibuat
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_auto_seed_creates_5_grade_ranges_on_assignment_create(): void
    {
        $assignment = $this->createAssignment(75);

        // Harus ada tepat 5 baris grade_range
        $ranges = GradeRange::where('teaching_assignment_id', $assignment->id)->get();
        $this->assertCount(5, $ranges, 'Harus ada tepat 5 grade range (A-E)');

        // Verifikasi semua letter ada
        $letters = $ranges->pluck('letter')->sort()->values()->toArray();
        $this->assertEquals(['A', 'B', 'C', 'D', 'E'], $letters);

        // Verifikasi C.min = KKTP
        $gradeC = $ranges->firstWhere('letter', 'C');
        $this->assertEquals(75.0, (float) $gradeC->min_score);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 3: Auto-recalculate saat KKTP berubah
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_auto_recalculate_when_kktp_changes(): void
    {
        $assignment = $this->createAssignment(75);

        // Verifikasi awal: C.min = 75
        $gradeCBefore = GradeRange::where('teaching_assignment_id', $assignment->id)
            ->where('letter', 'C')
            ->first();
        $this->assertEquals(75.0, (float) $gradeCBefore->min_score);

        // Update KKTP ke 80
        $assignment->update(['kktp' => 80]);

        // Verifikasi: C.min harus berubah menjadi 80
        $gradeCAfter = GradeRange::where('teaching_assignment_id', $assignment->id)
            ->where('letter', 'C')
            ->first();
        $this->assertEquals(80.0, (float) $gradeCAfter->min_score);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 4: GradeRangeResolver akurasi resolusi
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_resolver_accuracy_with_kktp_75(): void
    {
        $assignment = $this->createAssignment(75);

        // Defaults untuk KKTP=75:
        // A: 91-100, B: 83-90, C: 75-82, D: 60-74, E: 0-59

        // Grade A
        $this->assertEquals('A', GradeRangeResolver::resolve($assignment, 100));
        $this->assertEquals('A', GradeRangeResolver::resolve($assignment, 91));

        // Grade B
        $this->assertEquals('B', GradeRangeResolver::resolve($assignment, 90));
        $this->assertEquals('B', GradeRangeResolver::resolve($assignment, 83));

        // Grade C (harus mulai dari KKTP)
        $this->assertEquals('C', GradeRangeResolver::resolve($assignment, 82));
        $this->assertEquals('C', GradeRangeResolver::resolve($assignment, 75));

        // Grade D
        $this->assertEquals('D', GradeRangeResolver::resolve($assignment, 74));
        $this->assertEquals('D', GradeRangeResolver::resolve($assignment, 60));

        // Grade E
        $this->assertEquals('E', GradeRangeResolver::resolve($assignment, 59));
        $this->assertEquals('E', GradeRangeResolver::resolve($assignment, 0));
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 5: Fallback graceful tanpa grade_ranges di DB
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_fallback_to_default_when_no_ranges_in_db(): void
    {
        $assignment = $this->createAssignment(75);

        // Hapus semua grade_ranges dari DB (simulasi tidak ada data)
        GradeRange::where('teaching_assignment_id', $assignment->id)->delete();

        // Resolver harus tetap bekerja menggunakan fallback default
        $this->assertEquals('A', GradeRangeResolver::resolve($assignment, 95));
        $this->assertEquals('C', GradeRangeResolver::resolve($assignment, 75));
        $this->assertEquals('E', GradeRangeResolver::resolve($assignment, 30));
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 6: Range contiguous (tidak ada celah)
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_ranges_are_contiguous_and_cover_0_to_100(): void
    {
        $defaults = GradeRangeResolver::calculateDefaultRanges(75);

        // E harus mulai dari 0
        $this->assertEquals(0.0, $defaults['E']['min_score'], 'E.min harus 0');

        // A harus berakhir di 100
        $this->assertEquals(100.0, $defaults['A']['max_score'], 'A.max harus 100');

        // Verifikasi contiguous: max grade bawah + 1 = min grade atas
        $this->assertEquals($defaults['E']['max_score'] + 1, $defaults['D']['min_score'],
            'D.min harus = E.max + 1');
        $this->assertEquals($defaults['D']['max_score'] + 1, $defaults['C']['min_score'],
            'C.min harus = D.max + 1');
        $this->assertEquals($defaults['C']['max_score'] + 1, $defaults['B']['min_score'],
            'B.min harus = C.max + 1');
        $this->assertEquals($defaults['B']['max_score'] + 1, $defaults['A']['min_score'],
            'A.min harus = B.max + 1');
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 7: Grades A,B,C semua >= KKTP, Grades D,E < KKTP
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_passing_grades_are_above_kktp_and_failing_below(): void
    {
        $assignment = $this->createAssignment(75);

        // Skor tepat di KKTP harus LULUS (grade C, bukan D)
        $this->assertContains(
            GradeRangeResolver::resolve($assignment, 75),
            ['A', 'B', 'C'],
            'Skor = KKTP harus mendapat grade C (LULUS)'
        );

        // Skor 1 poin di bawah KKTP harus TIDAK LULUS
        $this->assertContains(
            GradeRangeResolver::resolve($assignment, 74),
            ['D', 'E'],
            'Skor = KKTP-1 harus mendapat grade D atau E (TIDAK LULUS)'
        );
    }
}
