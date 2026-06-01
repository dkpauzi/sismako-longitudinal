<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\FinalGrade;
use App\Models\Grade;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\Classroom;
use App\Models\AcademicPeriod;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\SchoolSetting;
use App\Models\SchoolProfile;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManualOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_observer_does_not_overwrite_manual_override()
    {
        // 1. Setup Data Dasar
        $profile = SchoolProfile::create(['name' => 'Test School']);
        SchoolSetting::create([
            'school_profile_id' => $profile->id,
            'default_kkm' => 75,
        ]);
        $period = AcademicPeriod::create(['name' => '2023/2024', 'start_year' => 2023, 'end_year' => 2024, 'semester' => 'odd', 'is_active' => true, 'start_date' => '2023-07-01', 'end_date' => '2023-12-31']);
        $classroom = Classroom::create(['name' => '7A', 'grade_level' => 7, 'phase' => 'D']);
        $teacher = Teacher::create(['name' => 'Budi', 'nip' => '12345']);
        $subject = Subject::create(['name' => 'Matematika', 'code' => 'MTK', 'type' => 'mandatory']);
        
        $studentUser = User::create(['name' => 'Student A', 'email' => 'studenta@test.com', 'password' => bcrypt('password'), 'role' => 'student', 'username' => '123']);
        $student = Student::create(['name' => 'Student A', 'nisn' => '123', 'nis' => '1234', 'gender' => 'L', 'place_of_birth' => 'Jakarta', 'date_of_birth' => '2010-01-01', 'religion' => 'Islam', 'status' => 'active', 'user_id' => $studentUser->id]);
        
        Enrollment::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_period_id' => $period->id,
            'status' => 'active',
        ]);

        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'academic_period_id' => $period->id,
            'kktp' => 75,
        ]);

        // 2. Admin inputs a manual FinalGrade override
        $overrideGrade = FinalGrade::create([
            'student_id' => $student->id,
            'teaching_assignment_id' => $assignment->id,
            'semester' => 'odd',
            'final_score' => 88.00,
            'grade_label' => 'A',
            'is_manual_override' => true,
        ]);

        $this->assertEquals(88.00, $overrideGrade->final_score);
        $this->assertTrue($overrideGrade->is_manual_override);

        // 3. Simulate a Teacher inputting a daily grade (which triggers the Observer)
        $assessment = Assessment::create([
            'teaching_assignment_id' => $assignment->id,
            'category' => 'sumatif_akhir_semester',
            'name' => 'UAS',
            'technique' => 'tertulis',
            'date' => now(),
        ]);

        $grade = Grade::create([
            'student_id' => $student->id,
            'assessment_id' => $assessment->id,
            'score' => 60, // Teacher inputted 60
        ]);

        // 4. Assert that the FinalGrade is STILL 88.00 (not overwritten)
        $finalGradeAfter = FinalGrade::where('student_id', $student->id)
            ->where('teaching_assignment_id', $assignment->id)
            ->where('semester', 'odd')
            ->first();

        $this->assertEquals(88.00, $finalGradeAfter->final_score);
        $this->assertEquals('A', $finalGradeAfter->grade_label);
        $this->assertTrue($finalGradeAfter->is_manual_override);
    }
}
