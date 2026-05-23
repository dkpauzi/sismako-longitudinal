<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPromotionTest extends TestCase
{
    use RefreshDatabase;

    protected PromotionService $promotionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->promotionService = new PromotionService();
    }

    /** @test */
    public function it_processes_promoted_students_correctly()
    {
        // 1. Setup Source Period & Classroom
        $sourcePeriod = AcademicPeriod::create(['start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd', 'start_date' => '2025-07-01', 'end_date' => '2025-12-31', 'is_active' => true]);
        $sourceClassroom = Classroom::create(['grade_level' => 7, 'name' => '7A']);
        
        $user = User::create(['username' => 'stud1', 'password' => '123', 'name' => 'Stud 1', 'role' => 'student', 'is_active' => true]);
        $student = Student::create(['user_id' => $user->id, 'nisn' => '1234', 'name' => 'Stud 1', 'gender' => 'L', 'status' => 'active']);
        
        $sourceEnrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_period_id' => $sourcePeriod->id,
            'classroom_id' => $sourceClassroom->id,
            'status' => 'active'
        ]);

        // 2. Setup Target Period & Classroom
        $targetPeriod = AcademicPeriod::create(['start_year' => 2025, 'end_year' => 2026, 'semester' => 'even', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'is_active' => false]);
        $targetClassroom = Classroom::create(['grade_level' => 8, 'name' => '8A']);

        // 3. Process Promotion
        $promotions = [
            [
                'enrollment_id' => $sourceEnrollment->id,
                'action' => 'promoted',
                'target_classroom_id' => $targetClassroom->id
            ]
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $targetPeriod->id);

        $this->assertTrue($result['success']);
        
        // Assert old enrollment is now 'promoted'
        $this->assertEquals('promoted', $sourceEnrollment->fresh()->status);

        // Assert new enrollment exists and is linked
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'academic_period_id' => $targetPeriod->id,
            'classroom_id' => $targetClassroom->id,
            'status' => 'active',
            'promoted_from_enrollment_id' => $sourceEnrollment->id
        ]);
    }

    /** @test */
    public function it_processes_retained_students_correctly()
    {
        $sourcePeriod = AcademicPeriod::create(['start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd', 'start_date' => '2025-07-01', 'end_date' => '2025-12-31', 'is_active' => true]);
        $sourceClassroom = Classroom::create(['grade_level' => 7, 'name' => '7A']);
        
        $user = User::create(['username' => 'stud2', 'password' => '123', 'name' => 'Stud 2', 'role' => 'student', 'is_active' => true]);
        $student = Student::create(['user_id' => $user->id, 'nisn' => '1235', 'name' => 'Stud 2', 'gender' => 'L', 'status' => 'active']);
        
        $sourceEnrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_period_id' => $sourcePeriod->id,
            'classroom_id' => $sourceClassroom->id,
            'status' => 'active'
        ]);

        $targetPeriod = AcademicPeriod::create(['start_year' => 2025, 'end_year' => 2026, 'semester' => 'even', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'is_active' => false]);
        $targetClassroom = Classroom::create(['grade_level' => 7, 'name' => '7B']); // Retained in same grade, different room maybe

        $promotions = [
            [
                'enrollment_id' => $sourceEnrollment->id,
                'action' => 'retained',
                'target_classroom_id' => $targetClassroom->id
            ]
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $targetPeriod->id);

        $this->assertTrue($result['success']);
        
        // Assert old enrollment is now 'retained'
        $this->assertEquals('retained', $sourceEnrollment->fresh()->status);

        // Assert new enrollment exists and is linked
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'academic_period_id' => $targetPeriod->id,
            'classroom_id' => $targetClassroom->id,
            'status' => 'active',
            'promoted_from_enrollment_id' => $sourceEnrollment->id
        ]);
    }

    /** @test */
    public function it_processes_graduated_students_correctly()
    {
        $sourcePeriod = AcademicPeriod::create(['start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd', 'start_date' => '2025-07-01', 'end_date' => '2025-12-31', 'is_active' => true]);
        $sourceClassroom = Classroom::create(['grade_level' => 9, 'name' => '9A']);
        
        $user = User::create(['username' => 'stud3', 'password' => '123', 'name' => 'Stud 3', 'role' => 'student', 'is_active' => true]);
        $student = Student::create(['user_id' => $user->id, 'nisn' => '1236', 'name' => 'Stud 3', 'gender' => 'P', 'status' => 'active']);
        
        $sourceEnrollment = Enrollment::create([
            'student_id' => $student->id,
            'academic_period_id' => $sourcePeriod->id,
            'classroom_id' => $sourceClassroom->id,
            'status' => 'active'
        ]);

        $targetPeriod = AcademicPeriod::create(['start_year' => 2025, 'end_year' => 2026, 'semester' => 'even', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'is_active' => false]);

        $promotions = [
            [
                'enrollment_id' => $sourceEnrollment->id,
                'action' => 'graduated',
                'target_classroom_id' => null
            ]
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $targetPeriod->id);

        $this->assertTrue($result['success']);
        
        // Assert old enrollment is now 'graduated'
        $this->assertEquals('graduated', $sourceEnrollment->fresh()->status);

        // Assert user is deactivated
        $this->assertFalse((bool) $user->fresh()->is_active);
        
        // Assert student status is graduated
        $this->assertEquals('graduated', $student->fresh()->status);

        // Assert no new enrollment was created for the target period
        $this->assertDatabaseMissing('enrollments', [
            'student_id' => $student->id,
            'academic_period_id' => $targetPeriod->id,
        ]);
    }
}
