<?php

namespace Tests\Feature;

use App\Filament\Resources\TeachingAssignmentResource\RelationManagers\AssessmentsRelationManager;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\LearningObjective;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mandate 2 — TP field kosong/"hilang" di form asesmen.
 *
 * Akar masalah: filter opsi TP membuang TP ber-grade_level NULL (importer
 * mengizinkan NULL), sehingga CheckboxList kosong padahal wajib. Uji ini
 * memastikan filter (kini null-tolerant) mengembalikan TP yang tepat sehingga
 * field terisi & tampil.
 */
class AssessmentTpOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tp_options_include_null_grade_level_and_exclude_mismatches(): void
    {
        $period = AcademicPeriod::create([
            'start_year' => 2023, 'end_year' => 2024, 'semester' => 'odd',
            'start_date' => '2023-07-01', 'end_date' => '2023-12-31', 'is_active' => true,
        ]);
        $otherPeriod = AcademicPeriod::create([
            'start_year' => 2024, 'end_year' => 2025, 'semester' => 'odd',
            'start_date' => '2024-07-01', 'end_date' => '2024-12-31', 'is_active' => false,
        ]);
        $classroom = Classroom::create(['name' => '7', 'grade_level' => 7]);
        $mtk = Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'type' => 'mandatory']);
        $ipa = Subject::create(['name' => 'IPA', 'code' => 'IPA', 'type' => 'mandatory']);

        $tUser = User::factory()->create(['role' => 'teacher']);
        $teacher = Teacher::create(['user_id' => $tUser->id, 'name' => 'Guru', 'nip' => '1', 'is_active' => true]);

        $assignment = TeachingAssignment::create([
            'academic_period_id' => $period->id, 'teacher_id' => $teacher->id,
            'subject_id' => $mtk->id, 'classroom_id' => $classroom->id, 'grading_formula' => 'average',
        ]);

        $mk = fn(array $attr) => LearningObjective::create(array_merge([
            'subject_id' => $mtk->id, 'academic_period_id' => $period->id, 'phase' => 'D',
            'content' => 'isi', 'attribute' => 'ringkas',
        ], $attr));

        $matchGrade = $mk(['code' => 'MTK-7-1', 'grade_level' => 7]);
        $nullGrade  = $mk(['code' => 'MTK-N-1', 'grade_level' => null]); // dulu HILANG dari opsi
        $wrongGrade = $mk(['code' => 'MTK-8-1', 'grade_level' => 8]);    // kelas beda
        $wrongSubject = LearningObjective::create([
            'subject_id' => $ipa->id, 'academic_period_id' => $period->id, 'phase' => 'D',
            'code' => 'IPA-7-1', 'grade_level' => 7, 'content' => 'x', 'attribute' => 'y',
        ]);
        $wrongPeriod = $mk(['code' => 'MTK-7-2', 'grade_level' => 7, 'academic_period_id' => $otherPeriod->id]);

        $ids = AssessmentsRelationManager::filterLearningObjectiveOptions(
            LearningObjective::query(),
            $assignment
        )->pluck('id')->all();

        $this->assertContains($matchGrade->id, $ids);
        $this->assertContains($nullGrade->id, $ids, 'TP grade_level NULL harus tetap muncul (fix bug field hilang).');
        $this->assertNotContains($wrongGrade->id, $ids);
        $this->assertNotContains($wrongSubject->id, $ids);
        $this->assertNotContains($wrongPeriod->id, $ids);
    }
}
