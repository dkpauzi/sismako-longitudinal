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

/**
 * Unit Test untuk method TeachingAssignment::calculateFinalGrade()
 *
 * Method ini menghitung Nilai Akhir Rapor seorang siswa berdasarkan:
 * - 3 rumus sumatif: average, weighting, percentage
 * - 1 fitur opsional: formative boost (pendongkrak formatif)
 *
 * Setiap test mengikuti pola Arrange → Act → Assert.
 *
 * @see \App\Models\TeachingAssignment::calculateFinalGrade()
 */
class CalculateFinalGradeTest extends TestCase
{
    use RefreshDatabase;

    // ─── SHARED FIXTURE ───────────────────────────────────────────────

    private Student $student;
    private AcademicPeriod $academicPeriod;
    private Classroom $classroom;
    private Subject $subject;
    private Teacher $teacher;

    /**
     * Menyiapkan data dasar yang dibutuhkan oleh setiap test.
     * Dipanggil otomatis sebelum setiap method test dijalankan.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Data referensi (parent tables) — dibutuhkan oleh foreign key constraints
        $this->academicPeriod = AcademicPeriod::create([
            'start_year'  => 2025,
            'end_year'    => 2026,
            'semester'    => 'odd',
            'start_date'  => '2025-07-14',
            'end_date'    => '2025-12-20',
            'is_active'   => true,
        ]);

        $this->classroom = Classroom::create([
            'name'        => 'Kelas 7.1',
            'grade_level' => 7,
        ]);

        $this->subject = Subject::create([
            'name' => 'Matematika',
            'code' => 'MTK',
            'type' => 'mandatory',
        ]);

        $this->teacher = Teacher::create([
            'name'   => 'Budi Santoso',
            'gender' => 'L',
        ]);

        $this->student = Student::create([
            'name'   => 'Andi Prasetyo',
            'gender' => 'L',
        ]);
    }

    // ─── HELPER METHODS ───────────────────────────────────────────────

    /**
     * Buat TeachingAssignment dengan formula dan konfigurasi tertentu.
     */
    private function createAssignment(
        string $formula = 'average',
        int $kktp = 75,
        string $boosterMode = 'none',
        float $boosterValue = 0,
    ): TeachingAssignment {
        return TeachingAssignment::create([
            'academic_period_id' => $this->academicPeriod->id,
            'teacher_id'         => $this->teacher->id,
            'subject_id'         => $this->subject->id,
            'classroom_id'       => $this->classroom->id,
            'grading_formula'    => $formula,
            'kktp'               => $kktp,
            'booster_mode'       => $boosterMode,
            'booster_value'      => $boosterValue,
        ]);
    }

    /**
     * Buat Assessment (ujian) dan langsung isi Grade (nilai) untuk siswa.
     *
     * GradeObserver dinonaktifkan agar tidak memicu efek samping
     * (calculateFinalGrade dipanggil dari observer — kita ingin
     * mengujinya secara terisolasi).
     */
    private function createAssessmentWithGrade(
        TeachingAssignment $assignment,
        string $category,
        int $weight,
        ?int $score,
    ): Assessment {
        $assessment = Assessment::create([
            'teaching_assignment_id' => $assignment->id,
            'name'                   => 'Assessment ' . rand(1, 999),
            'category'               => $category,
            'technique'              => 'tes_tertulis',
            'weight'                 => $weight,
            'date'                   => now(),
        ]);

        // Buat grade tanpa memicu observer
        Grade::withoutEvents(function () use ($assessment, $score) {
            Grade::create([
                'assessment_id' => $assessment->id,
                'student_id'    => $this->student->id,
                'score'         => $score,
            ]);
        });

        return $assessment;
    }

    // ═══════════════════════════════════════════════════════════════════
    // TEST CASES
    // ═══════════════════════════════════════════════════════════════════

    /*
    |--------------------------------------------------------------------------
    | 1. FORMULA: RATA-RATA (average)
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: Dua ujian sumatif, formula rata-rata.
     *
     * UH1 = 80, UAS = 90  →  Rata-rata = (80 + 90) / 2 = 85
     */
    public function test_average_formula_with_two_assessments(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(formula: 'average');

        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 90);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        $this->assertIsFloat($result);
        $this->assertEquals(85.0, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. FORMULA: PEMBOBOTAN PERSENTASE (weighting)
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: Dua ujian sumatif dengan bobot 40% dan 60%.
     *
     * UH1 = 80 (bobot 40%)  →  80 × 0.40 = 32
     * UAS = 90 (bobot 60%)  →  90 × 0.60 = 54
     * Total = 32 + 54 = 86
     */
    public function test_weighting_formula_with_percentage_weights(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(formula: 'weighting');

        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 40, 80);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 60, 90);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        $this->assertEquals(86.0, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. FORMULA: PERSENTASE KETUNTASAN KKTP (percentage)
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: KKTP = 75. Dari 3 ujian, 2 lulus (score >= 75).
     *
     * UH1 = 80  →  ≥ 75 → Tuntas
     * UH2 = 60  →  < 75 → Belum Tuntas
     * UAS = 90  →  ≥ 75 → Tuntas
     * Persentase = (2/3) × 100 = 66.67 → round = 67
     */
    public function test_percentage_formula_counts_kktp_pass_rate(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(formula: 'percentage', kktp: 75);

        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 60);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 90);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        // (2/3) × 100 = 66.666... → round() = 67
        $this->assertEquals(67.0, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. FORMULA RATA-RATA + FORMATIVE BOOST
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: Rata-rata sumatif + poin formatif sebagai booster.
     *
     * Sumatif:  UH1 = 80, UAS = 90  →  Rata-rata = 85
     * Formatif: 2 poin + 3 poin = 5 poin, booster 20%
     * Bonus    = 5 × (20/100) = 1.0
     * Total    = 85 + 1 = 86
     */
    public function test_formative_boost_adds_bonus_points(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(
            formula: 'average',
            boosterMode: 'weight',
            boosterValue: 20,
        );

        // Sumatif
        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 90);

        // Formatif poin
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 2);
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 3);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        // Sumatif rata-rata 85 + bonus 5×0.2 = 1.0 → 86
        $this->assertEquals(86.0, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. EDGE CASE: NILAI TIDAK BOLEH MELEBIHI 100
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: Nilai sumatif tinggi + boost besar → cap at 100.
     *
     * Sumatif:  99 + 99 = rata-rata 99
     * Formatif: 10 poin, booster 50% → bonus = 5
     * Total = 99 + 5 = 104 → capped to 100
     */
    public function test_final_grade_capped_at_100(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(
            formula: 'average',
            boosterMode: 'weight',
            boosterValue: 50,
        );

        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 99);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 99);
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 10);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        $this->assertEquals(100.0, $result);
        $this->assertLessThanOrEqual(100, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | 6. EDGE CASE: TIDAK ADA ASESMEN → RETURN 0
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: TeachingAssignment tanpa asesmen sama sekali.
     *
     * Harus mengembalikan 0.0 tanpa error.
     */
    public function test_returns_zero_when_no_assessments_exist(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(formula: 'average');
        // Tidak ada assessment / grade yang dibuat

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        $this->assertEquals(0.0, $result);
        $this->assertIsFloat($result);
    }

    /*
    |--------------------------------------------------------------------------
    | 7. EDGE CASE: NILAI NULL (SISWA BELUM MENGERJAKAN)
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: 2 ujian, tapi 1 ujian nilai-nya NULL (belum diisi guru).
     *
     * UH1 = 80  →  dihitung
     * UAS = NULL →  dilewati (tidak dihitung)
     * Rata-rata = 80 / 1 = 80
     */
    public function test_ignores_null_scores(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(formula: 'average');

        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, null);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        // Hanya UH1 yang dihitung (80), UAS dilewati karena NULL
        $this->assertEquals(80.0, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | 8. EDGE CASE: FORMATIF SAJA (TANPA SUMATIF)
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: Hanya ada asesmen formatif, tanpa sumatif.
     *
     * Formatif tidak masuk perhitungan utama (hanya sebagai booster).
     * Sumatif = 0, Boost = 5 × 20% = 1 → Total = 1
     */
    public function test_only_formative_assessments_without_summative(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(
            formula: 'average',
            boosterMode: 'weight',
            boosterValue: 20,
        );

        // Hanya formatif poin, tidak ada sumatif
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 5);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        // Sumatif = 0, Boost = 5 × 0.20 = 1.0 → Total = 1
        $this->assertEquals(1.0, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | 9. EDGE CASE: BOOSTER DIMATIKAN
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: Formatif poin ada nilainya, tapi fitur boost DIMATIKAN.
     *
     * Nilai formatif TIDAK boleh mempengaruhi nilai akhir.
     */
    public function test_formative_boost_disabled_ignores_formatif_scores(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(
            formula: 'average',
            boosterMode: 'none',        // ← Dimatikan
            boosterValue: 0,
        );

        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 90);
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 10);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        // Rata-rata sumatif = 85, formatif diabaikan
        $this->assertEquals(85.0, $result);
    }

    /*
    |--------------------------------------------------------------------------
    | 10. RETURN TYPE VALIDATION
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: Memastikan return type selalu float.
     */
    public function test_return_type_is_always_float(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(formula: 'average');
        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 100);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        $this->assertIsFloat($result, 'calculateFinalGrade() harus mengembalikan tipe float');
    }

    /*
    |--------------------------------------------------------------------------
    | 11. BOOSTER MODE: POIN TETAP (point)
    |--------------------------------------------------------------------------
    */

    /**
     * Skenario: Mode 'point' menambah poin tetap per formatif terisi.
     *
     * Sumatif:  UH1 = 80, UAS = 90  →  Rata-rata = 85
     * Formatif: 2 formatif terisi, poin per formatif = 2  →  Bonus = 2 × 2 = 4
     * Total    = 85 + 4 = 89
     */
    public function test_point_mode_adds_fixed_points_per_filled_formative(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(boosterMode: 'point', boosterValue: 2);

        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 90);
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 1); // terisi
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 1); // terisi

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        $this->assertEquals(89.0, $result);
    }

    /**
     * Skenario: Mode 'point' mengabaikan formatif yang tidak terisi (skor 0 / null).
     *
     * Sumatif = 85. Dua formatif: skor 0 dan null → keduanya TIDAK dihitung.
     * Total = 85 (tidak ada bonus).
     */
    public function test_point_mode_ignores_zero_and_null_formative(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(boosterMode: 'point', boosterValue: 2);

        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 90);
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 0);        // tidak terisi
        $this->createAssessmentWithGrade($assignment, 'formatif_deskripsi', 0, null); // null

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        $this->assertEquals(85.0, $result);
    }

    /**
     * Skenario: Mode 'point' tetap dibatasi maksimal 100.
     *
     * Sumatif 99 & 99 → 99. Tiga formatif terisi, poin = 2 → bonus 6 → 105 → cap 100.
     */
    public function test_point_mode_capped_at_100(): void
    {
        // ── ARRANGE ──
        $assignment = $this->createAssignment(boosterMode: 'point', boosterValue: 2);

        $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 99);
        $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 99);
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 1);
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 1);
        $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 1);

        // ── ACT ──
        $result = $assignment->calculateFinalGrade($this->student->id);

        // ── ASSERT ──
        $this->assertEquals(100.0, $result);
    }
}
