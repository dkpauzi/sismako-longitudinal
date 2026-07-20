<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\LearningObjective;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\LearningObjectiveCopyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Mandate 3 — Salin TP: core copy, idempotency, and RBAC scope.
 */
class LearningObjectiveCopyTest extends TestCase
{
    use RefreshDatabase;

    private LearningObjectiveCopyService $svc;
    private AcademicPeriod $source;
    private AcademicPeriod $target;
    private Subject $mtk;
    private Subject $ipa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new LearningObjectiveCopyService();

        foreach (['super_admin', 'admin', 'teacher'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->source = AcademicPeriod::create(['start_year' => 2023, 'end_year' => 2024, 'semester' => 'odd', 'start_date' => '2023-07-01', 'end_date' => '2023-12-31', 'is_active' => false]);
        $this->target = AcademicPeriod::create(['start_year' => 2024, 'end_year' => 2025, 'semester' => 'odd', 'start_date' => '2024-07-01', 'end_date' => '2024-12-31', 'is_active' => true]);

        $this->mtk = Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'type' => 'mandatory']);
        $this->ipa = Subject::create(['name' => 'IPA', 'code' => 'IPA', 'type' => 'mandatory']);
    }

    private function tp(Subject $subject, string $code, ?int $period = null): LearningObjective
    {
        return LearningObjective::create([
            'subject_id' => $subject->id,
            'academic_period_id' => $period ?? $this->source->id,
            'grade_level' => 7, 'phase' => 'D', 'code' => $code,
            'content' => "isi {$code}", 'attribute' => "ringkas {$code}",
        ]);
    }

    public function test_admin_scope_copies_all_subjects(): void
    {
        $this->tp($this->mtk, 'MTK-1');
        $this->tp($this->ipa, 'IPA-1');

        $result = $this->svc->copy($this->source->id, $this->target->id, null);

        $this->assertEquals(2, $result['copied']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(2, LearningObjective::where('academic_period_id', $this->target->id)->count());
    }

    public function test_copy_is_idempotent_no_duplicates(): void
    {
        $this->tp($this->mtk, 'MTK-1');

        $first = $this->svc->copy($this->source->id, $this->target->id, null);
        $second = $this->svc->copy($this->source->id, $this->target->id, null);

        $this->assertEquals(1, $first['copied']);
        $this->assertEquals(0, $second['copied']);
        $this->assertEquals(1, $second['skipped']);
        // Tidak ada duplikat di target.
        $this->assertEquals(1, LearningObjective::where('academic_period_id', $this->target->id)
            ->where('subject_id', $this->mtk->id)->where('code', 'MTK-1')->count());
    }

    public function test_teacher_scope_restricts_to_assigned_subjects(): void
    {
        $this->tp($this->mtk, 'MTK-1');
        $this->tp($this->ipa, 'IPA-1');

        // Guru hanya mengampu MTK di periode TUJUAN.
        $result = $this->svc->copy($this->source->id, $this->target->id, [$this->mtk->id]);

        $this->assertEquals(1, $result['copied']);
        $this->assertTrue(LearningObjective::where('academic_period_id', $this->target->id)->where('subject_id', $this->mtk->id)->exists());
        $this->assertFalse(LearningObjective::where('academic_period_id', $this->target->id)->where('subject_id', $this->ipa->id)->exists());
    }

    public function test_empty_allowed_scope_copies_nothing(): void
    {
        $this->tp($this->mtk, 'MTK-1');
        $result = $this->svc->copy($this->source->id, $this->target->id, []);
        $this->assertEquals(0, $result['copied']);
        $this->assertEquals(0, LearningObjective::where('academic_period_id', $this->target->id)->count());
    }

    public function test_same_period_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->copy($this->source->id, $this->source->id, null);
    }

    public function test_allowed_subject_ids_for_admin_is_null_and_teacher_is_scoped(): void
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $adminUser->assignRole('admin');
        $this->assertNull($this->svc->allowedSubjectIdsFor($adminUser, $this->target->id));

        // Guru dengan SK MTK di periode TUJUAN → hanya MTK.
        $tUser = User::factory()->create(['role' => 'teacher']);
        $tUser->assignRole('teacher');
        $teacher = Teacher::create(['user_id' => $tUser->id, 'name' => 'Guru', 'nip' => '9', 'is_active' => true]);
        $classroom = Classroom::create(['name' => '7', 'grade_level' => 7]);
        TeachingAssignment::create([
            'academic_period_id' => $this->target->id, 'teacher_id' => $teacher->id,
            'subject_id' => $this->mtk->id, 'classroom_id' => $classroom->id, 'grading_formula' => 'average',
        ]);

        $allowed = $this->svc->allowedSubjectIdsFor($tUser->fresh(), $this->target->id);
        $this->assertEquals([$this->mtk->id], $allowed);
    }
}
