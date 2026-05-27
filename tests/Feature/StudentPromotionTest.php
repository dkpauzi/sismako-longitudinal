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

    private AcademicPeriod $sourcePeriod;
    private AcademicPeriod $targetPeriod;
    private Classroom $sourceClassroom;
    private Classroom $targetClassroomA;
    private Classroom $targetClassroomB;
    private PromotionService $promotionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promotionService = new PromotionService();

        $this->sourcePeriod = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'even',
            'is_active' => false, 'start_date' => '2026-01-01', 'end_date' => '2026-06-30',
        ]);

        $this->targetPeriod = AcademicPeriod::create([
            'start_year' => 2026, 'end_year' => 2027, 'semester' => 'odd',
            'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);

        $this->sourceClassroom = Classroom::create(['name' => '7A', 'grade_level' => 7]);
        $this->targetClassroomA = Classroom::create(['name' => '8A', 'grade_level' => 8]);
        $this->targetClassroomB = Classroom::create(['name' => '8B', 'grade_level' => 8]);
    }

    private function createStudentWithEnrollment(string $name, string $nisn): array
    {
        $studentUser = User::factory()->create(['role' => 'student', 'username' => 'siswa_' . $nisn]);
        $guardianUser = User::factory()->create(['role' => 'guardian', 'username' => 'wali_' . $nisn]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'guardian_user_id' => $guardianUser->id,
            'nisn' => $nisn,
            'name' => $name,
            'gender' => 'L',
            'status' => 'active',
        ]);

        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'classroom_id' => $this->sourceClassroom->id,
            'academic_period_id' => $this->sourcePeriod->id,
            'status' => 'active',
        ]);

        return ['student' => $student, 'enrollment' => $enrollment, 'user' => $studentUser, 'guardian' => $guardianUser];
    }

    /**
     * Test: Siswa bisa dipetakan ke kelas tujuan yang berbeda (shuffling).
     */
    public function test_students_can_be_mapped_to_different_destination_classrooms(): void
    {
        $data1 = $this->createStudentWithEnrollment('Andi', '1111111111');
        $data2 = $this->createStudentWithEnrollment('Budi', '2222222222');

        $promotions = [
            [
                'enrollment_id' => $data1['enrollment']->id,
                'action' => 'promoted',
                'target_classroom_id' => $this->targetClassroomA->id, // Andi -> 8A
            ],
            [
                'enrollment_id' => $data2['enrollment']->id,
                'action' => 'promoted',
                'target_classroom_id' => $this->targetClassroomB->id, // Budi -> 8B
            ],
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $this->targetPeriod->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['processed']);

        // Andi di kelas 8A
        $andiNewEnrollment = Enrollment::where('student_id', $data1['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)
            ->first();
        $this->assertNotNull($andiNewEnrollment);
        $this->assertEquals($this->targetClassroomA->id, $andiNewEnrollment->classroom_id);

        // Budi di kelas 8B
        $budiNewEnrollment = Enrollment::where('student_id', $data2['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)
            ->first();
        $this->assertNotNull($budiNewEnrollment);
        $this->assertEquals($this->targetClassroomB->id, $budiNewEnrollment->classroom_id);
    }

    /**
     * Test: promoted_from_enrollment_id ditugaskan dengan benar.
     */
    public function test_promoted_from_enrollment_id_is_correctly_assigned(): void
    {
        $data = $this->createStudentWithEnrollment('Cici', '3333333333');

        $promotions = [
            [
                'enrollment_id' => $data['enrollment']->id,
                'action' => 'promoted',
                'target_classroom_id' => $this->targetClassroomA->id,
            ],
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $this->targetPeriod->id);
        $this->assertTrue($result['success']);

        // Enrollment baru harus merujuk ke enrollment lama
        $newEnrollment = Enrollment::where('student_id', $data['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)
            ->first();

        $this->assertNotNull($newEnrollment);
        $this->assertEquals($data['enrollment']->id, $newEnrollment->promoted_from_enrollment_id);

        // Enrollment lama harus berstatus 'promoted'
        $data['enrollment']->refresh();
        $this->assertEquals('promoted', $data['enrollment']->status);

        // Verifikasi relasi promotedFrom
        $this->assertNotNull($newEnrollment->promotedFrom);
        $this->assertEquals($data['enrollment']->id, $newEnrollment->promotedFrom->id);
    }

    /**
     * Test: Siswa tinggal kelas tetap mendapat enrollment baru di periode tujuan.
     */
    public function test_retained_student_gets_new_enrollment_in_target(): void
    {
        $data = $this->createStudentWithEnrollment('Dedi', '4444444444');

        $promotions = [
            [
                'enrollment_id' => $data['enrollment']->id,
                'action' => 'retained',
                'target_classroom_id' => $this->sourceClassroom->id, // Tinggal di 7A
            ],
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $this->targetPeriod->id);
        $this->assertTrue($result['success']);

        // Enrollment lama berstatus 'retained'
        $data['enrollment']->refresh();
        $this->assertEquals('retained', $data['enrollment']->status);

        // Enrollment baru di periode tujuan dengan kelas yang sama
        $newEnrollment = Enrollment::where('student_id', $data['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)
            ->first();
        $this->assertNotNull($newEnrollment);
        $this->assertEquals($this->sourceClassroom->id, $newEnrollment->classroom_id);
        $this->assertEquals('active', $newEnrollment->status);
        $this->assertEquals($data['enrollment']->id, $newEnrollment->promoted_from_enrollment_id);
    }

    /**
     * Test: Siswa lulus -> student.status=graduated, user+wali dinonaktifkan.
     */
    public function test_graduated_students_have_accounts_deactivated(): void
    {
        $data = $this->createStudentWithEnrollment('Eka', '5555555555');

        // Pastikan sebelum proses semua user aktif
        $data['user']->refresh();
        $data['guardian']->refresh();
        $this->assertTrue($data['user']->is_active);
        $this->assertTrue($data['guardian']->is_active);

        $promotions = [
            [
                'enrollment_id' => $data['enrollment']->id,
                'action' => 'graduated',
                'target_classroom_id' => null,
            ],
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $this->targetPeriod->id);
        $this->assertTrue($result['success']);

        // Enrollment lama berstatus 'graduated'
        $data['enrollment']->refresh();
        $this->assertEquals('graduated', $data['enrollment']->status);

        // Student status = graduated
        $data['student']->refresh();
        $this->assertEquals('graduated', $data['student']->status);

        // User siswa dinonaktifkan
        $data['user']->refresh();
        $this->assertFalse($data['user']->is_active);

        // User wali dinonaktifkan
        $data['guardian']->refresh();
        $this->assertFalse($data['guardian']->is_active);

        // Tidak boleh ada enrollment baru di periode tujuan
        $newEnrollment = Enrollment::where('student_id', $data['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)
            ->first();
        $this->assertNull($newEnrollment);
    }

    /**
     * Test: Batch campuran (promosi + lulusan) diproses dalam satu transaksi.
     */
    public function test_mixed_batch_promotion_and_graduation(): void
    {
        $promoted = $this->createStudentWithEnrollment('Fani', '6666666666');
        $graduated = $this->createStudentWithEnrollment('Gita', '7777777777');

        $promotions = [
            [
                'enrollment_id' => $promoted['enrollment']->id,
                'action' => 'promoted',
                'target_classroom_id' => $this->targetClassroomA->id,
            ],
            [
                'enrollment_id' => $graduated['enrollment']->id,
                'action' => 'graduated',
                'target_classroom_id' => null,
            ],
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $this->targetPeriod->id);
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['processed']);

        // Fani naik kelas
        $faniNew = Enrollment::where('student_id', $promoted['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)
            ->first();
        $this->assertNotNull($faniNew);
        $this->assertEquals('active', $faniNew->status);

        // Gita lulus
        $graduated['student']->refresh();
        $this->assertEquals('graduated', $graduated['student']->status);
        $graduated['user']->refresh();
        $this->assertFalse($graduated['user']->is_active);
    }
}
