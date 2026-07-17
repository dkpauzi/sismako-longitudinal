<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\SchoolProfile;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataIntegrityPhaseTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_setting_auto_links_to_school_profile(): void
    {
        // Pasca-konsolidasi: school_settings tidak lagi menyimpan kolom identitas
        // (tidak ada backfill legacy). Identitas dibaca via relasi schoolProfile.
        $profile = SchoolProfile::create([
            'name' => 'SMPN Integritas',
            'npsn' => '12345678',
            'address' => 'Jl. Integritas No. 1',
            'phone' => '081234567890',
            'email' => 'info@integritas.sch.id',
            'principal_name' => 'Drs. Integritas',
        ]);

        $setting = SchoolSetting::create([
            'default_kkm' => 78,
        ])->fresh();

        $this->assertSame($profile->id, $setting->school_profile_id);
        $this->assertSame('SMPN Integritas', $setting->schoolProfile->name);
        $this->assertSame('Drs. Integritas', $setting->schoolProfile->principal_name);
    }

    public function test_grade_table_rejects_duplicate_assessment_student_pairs(): void
    {
        [$assessment, $student] = $this->buildAssessmentFixture();

        Grade::create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 88,
        ]);

        $this->expectException(QueryException::class);

        Grade::create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 90,
        ]);
    }

    public function test_attendance_table_rejects_duplicate_student_attendance_per_date(): void
    {
        [$assignment, $student] = $this->buildTeachingAssignmentFixture();

        Attendance::create([
            'teaching_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'date' => '2026-01-10',
            'status' => 'present',
        ]);

        $this->expectException(QueryException::class);

        Attendance::create([
            'teaching_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'date' => '2026-01-10',
            'status' => 'sick',
        ]);
    }

    private function buildAssessmentFixture(): array
    {
        [$assignment, $student] = $this->buildTeachingAssignmentFixture();

        $assessment = Assessment::create([
            'teaching_assignment_id' => $assignment->id,
            'name' => 'Sumatif Harian',
            'category' => 'sumatif_lingkup_materi',
            'technique' => 'tes_tertulis',
            'date' => '2026-01-10',
            'weight' => 1,
        ]);

        return [$assessment, $student];
    }

    private function buildTeachingAssignmentFixture(): array
    {
        $period = AcademicPeriod::create([
            'start_year' => 2025,
            'end_year' => 2026,
            'semester' => 'odd',
            'start_date' => '2025-07-14',
            'end_date' => '2025-12-19',
            'is_active' => true,
        ]);

        $classroom = Classroom::create([
            'name' => 'Kelas 7.1',
            'grade_level' => 7,
        ]);

        $teacher = Teacher::create([
            'name' => 'Guru Integritas',
            'gender' => 'L',
        ]);

        $subject = Subject::create([
            'name' => 'Matematika',
            'code' => 'MTK',
            'type' => 'mandatory',
        ]);

        $student = Student::create([
            'name' => 'Siswa Integritas',
            'gender' => 'P',
        ]);

        $assignment = TeachingAssignment::create([
            'academic_period_id' => $period->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'grading_formula' => 'average',
            'kktp' => 75,
        ]);

        return [$assignment, $student];
    }
}

