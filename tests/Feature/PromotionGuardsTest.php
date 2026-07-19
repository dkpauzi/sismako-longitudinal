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
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guard transisi (Action Plan item 3):
 *  - 3.1 Lock guard: rapor periode asal wajib terkunci sebelum transisi.
 *  - 3.2 Temporal guard: Naik/Tinggal/Lulus hanya dari Genap → Ganjil tahun depan.
 *  - Lanjut Semester (Ganjil → Genap tahun sama) sebagai jalur terpisah.
 */
class PromotionGuardsTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $svc;
    private AcademicPeriod $ganjil2324;
    private AcademicPeriod $genap2324;
    private AcademicPeriod $ganjil2425;
    private Classroom $kelas7;
    private Classroom $kelas8;
    private TeachingAssignment $taGanjil;
    private TeachingAssignment $taGenap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new PromotionService();

        $this->ganjil2324 = AcademicPeriod::create(['start_year' => 2023, 'end_year' => 2024, 'semester' => 'odd', 'is_active' => false, 'start_date' => '2023-07-01', 'end_date' => '2023-12-31']);
        $this->genap2324  = AcademicPeriod::create(['start_year' => 2023, 'end_year' => 2024, 'semester' => 'even', 'is_active' => false, 'start_date' => '2024-01-01', 'end_date' => '2024-06-30']);
        $this->ganjil2425 = AcademicPeriod::create(['start_year' => 2024, 'end_year' => 2025, 'semester' => 'odd', 'is_active' => true, 'start_date' => '2024-07-01', 'end_date' => '2024-12-31']);

        $this->kelas7 = Classroom::create(['name' => '7', 'grade_level' => 7]);
        $this->kelas8 = Classroom::create(['name' => '8', 'grade_level' => 8]);

        $teacher = Teacher::create(['name' => 'Guru Uji', 'gender' => 'L']);
        $subject = Subject::create(['name' => 'Matematika Uji', 'code' => 'MTK-UJI', 'type' => 'mandatory']);

        $taAttrs = fn (AcademicPeriod $p) => [
            'academic_period_id' => $p->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'classroom_id' => $this->kelas7->id, 'grading_formula' => 'average', 'kktp' => 75,
            'booster_mode' => 'none', 'booster_value' => 0,
        ];
        $this->taGanjil = TeachingAssignment::create($taAttrs($this->ganjil2324));
        $this->taGenap  = TeachingAssignment::create($taAttrs($this->genap2324));
    }

    private function makeStudent(string $nisn, Classroom $classroom, AcademicPeriod $period): array
    {
        $u = User::factory()->create(['role' => 'student', 'username' => 's_' . $nisn]);
        $g = User::factory()->create(['role' => 'guardian', 'username' => 'w_' . $nisn]);
        $s = Student::create(['user_id' => $u->id, 'guardian_user_id' => $g->id, 'nisn' => $nisn, 'name' => 'Siswa ' . $nisn, 'gender' => 'L', 'status' => 'active']);
        $e = Enrollment::create(['student_id' => $s->id, 'classroom_id' => $classroom->id, 'academic_period_id' => $period->id, 'status' => 'active']);

        return ['student' => $s, 'enrollment' => $e];
    }

    private function finalGrade(int $studentId, TeachingAssignment $ta, string $semester, bool $locked): void
    {
        FinalGrade::create([
            'student_id' => $studentId, 'teaching_assignment_id' => $ta->id, 'semester' => $semester,
            'final_score' => 80, 'grade_label' => 'C', 'is_locked' => $locked,
        ]);
    }

    // ── 3.1 LOCK GUARD ──────────────────────────────────────────────

    public function test_promotion_blocked_when_source_report_not_locked(): void
    {
        $d = $this->makeStudent('1000000001', $this->kelas7, $this->genap2324);
        $this->finalGrade($d['student']->id, $this->taGenap, 'even', locked: false);

        $result = $this->svc->processBatchPromotions([[
            'enrollment_id' => $d['enrollment']->id, 'action' => 'promoted', 'target_classroom_id' => $this->kelas8->id,
        ]], $this->ganjil2425->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('belum dikunci', $result['message']);
        // Rollback: tidak ada enrollment baru & status lama tetap active.
        $this->assertNull(Enrollment::where('student_id', $d['student']->id)->where('academic_period_id', $this->ganjil2425->id)->first());
        $this->assertEquals('active', $d['enrollment']->refresh()->status);
    }

    public function test_promotion_allowed_when_source_report_locked(): void
    {
        $d = $this->makeStudent('1000000002', $this->kelas7, $this->genap2324);
        $this->finalGrade($d['student']->id, $this->taGenap, 'even', locked: true);

        $result = $this->svc->processBatchPromotions([[
            'enrollment_id' => $d['enrollment']->id, 'action' => 'promoted', 'target_classroom_id' => $this->kelas8->id,
        ]], $this->ganjil2425->id);

        $this->assertTrue($result['success']);
        $this->assertNotNull(Enrollment::where('student_id', $d['student']->id)->where('academic_period_id', $this->ganjil2425->id)->first());
    }

    public function test_elective_without_grade_does_not_trap_student(): void
    {
        // Siswa Genap tanpa satu pun baris final_grade (mis. mapel elektif belum dinilai)
        // TIDAK boleh terblokir — lock guard hanya memblokir final grade yang ADA & belum terkunci.
        $d = $this->makeStudent('1000000007', $this->kelas7, $this->genap2324);

        $result = $this->svc->processBatchPromotions([[
            'enrollment_id' => $d['enrollment']->id, 'action' => 'promoted', 'target_classroom_id' => $this->kelas8->id,
        ]], $this->ganjil2425->id);

        $this->assertTrue($result['success'], $result['message']);
    }

    // ── 3.2 TEMPORAL GUARD ──────────────────────────────────────────

    public function test_promotion_rejected_from_ganjil_source(): void
    {
        $d = $this->makeStudent('1000000003', $this->kelas7, $this->ganjil2324);

        $result = $this->svc->processBatchPromotions([[
            'enrollment_id' => $d['enrollment']->id, 'action' => 'promoted', 'target_classroom_id' => $this->kelas8->id,
        ]], $this->genap2324->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Lanjut Semester', $result['message']);
        $this->assertEquals('active', $d['enrollment']->refresh()->status);
    }

    public function test_promotion_rejected_when_target_not_next_ganjil(): void
    {
        $d = $this->makeStudent('1000000004', $this->kelas7, $this->genap2324);

        // Source Genap sah, tapi target = Genap tahun yang sama (bukan Ganjil tahun depan).
        $result = $this->svc->processBatchPromotions([[
            'enrollment_id' => $d['enrollment']->id, 'action' => 'promoted', 'target_classroom_id' => $this->kelas8->id,
        ]], $this->genap2324->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('GANJIL tahun', $result['message']);
    }

    // ── LANJUT SEMESTER (jalur terpisah) ────────────────────────────

    public function test_semester_continuation_creates_same_class_enrollment(): void
    {
        $d = $this->makeStudent('1000000005', $this->kelas7, $this->ganjil2324);
        $this->finalGrade($d['student']->id, $this->taGanjil, 'odd', locked: true);

        $result = $this->svc->processSemesterChunk([[
            'enrollment_id' => $d['enrollment']->id,
        ]], $this->genap2324->id);

        $this->assertTrue($result['success'], $result['message']);

        $new = Enrollment::where('student_id', $d['student']->id)->where('academic_period_id', $this->genap2324->id)->first();
        $this->assertNotNull($new);
        $this->assertEquals($this->kelas7->id, $new->classroom_id); // kelas & tingkat TETAP
        $this->assertEquals('active', $new->status);
        $this->assertEquals($d['enrollment']->id, $new->promoted_from_enrollment_id);
        // Enrollment Ganjil tetap 'active' sebagai catatan semester tersebut.
        $this->assertEquals('active', $d['enrollment']->refresh()->status);
    }

    public function test_semester_continuation_rejects_year_jump(): void
    {
        $d = $this->makeStudent('1000000006', $this->kelas7, $this->ganjil2324);
        $this->finalGrade($d['student']->id, $this->taGanjil, 'odd', locked: true);

        // Ganjil 2023/2024 → Ganjil 2024/2025 (lompat tahun) harus ditolak.
        $result = $this->svc->processSemesterChunk([[
            'enrollment_id' => $d['enrollment']->id,
        ]], $this->ganjil2425->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('GANJIL ke GENAP', $result['message']);
    }

    public function test_semester_continuation_blocked_when_ganjil_not_locked(): void
    {
        $d = $this->makeStudent('1000000008', $this->kelas7, $this->ganjil2324);
        $this->finalGrade($d['student']->id, $this->taGanjil, 'odd', locked: false);

        $result = $this->svc->processSemesterChunk([[
            'enrollment_id' => $d['enrollment']->id,
        ]], $this->genap2324->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('belum dikunci', $result['message']);
    }
}
