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

    // ─────────────────────────────────────────────────────────────────
    // TEST GROUP 1: Core Promotion Logic (via processBatchPromotions)
    // ─────────────────────────────────────────────────────────────────

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
                'target_classroom_id' => $this->targetClassroomA->id,
            ],
            [
                'enrollment_id' => $data2['enrollment']->id,
                'action' => 'promoted',
                'target_classroom_id' => $this->targetClassroomB->id,
            ],
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $this->targetPeriod->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['processed']);

        $andiNew = Enrollment::where('student_id', $data1['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)->first();
        $this->assertNotNull($andiNew);
        $this->assertEquals($this->targetClassroomA->id, $andiNew->classroom_id);

        $budiNew = Enrollment::where('student_id', $data2['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)->first();
        $this->assertNotNull($budiNew);
        $this->assertEquals($this->targetClassroomB->id, $budiNew->classroom_id);
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

        $newEnrollment = Enrollment::where('student_id', $data['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)->first();

        $this->assertNotNull($newEnrollment);
        $this->assertEquals($data['enrollment']->id, $newEnrollment->promoted_from_enrollment_id);

        $data['enrollment']->refresh();
        $this->assertEquals('promoted', $data['enrollment']->status);

        $this->assertNotNull($newEnrollment->promotedFrom);
        $this->assertEquals($data['enrollment']->id, $newEnrollment->promotedFrom->id);
    }

    /**
     * Test: Siswa tinggal kelas tetap mendapat enrollment baru.
     */
    public function test_retained_student_gets_new_enrollment_in_target(): void
    {
        $data = $this->createStudentWithEnrollment('Dedi', '4444444444');

        $promotions = [
            [
                'enrollment_id' => $data['enrollment']->id,
                'action' => 'retained',
                'target_classroom_id' => $this->sourceClassroom->id,
            ],
        ];

        $result = $this->promotionService->processBatchPromotions($promotions, $this->targetPeriod->id);
        $this->assertTrue($result['success']);

        $data['enrollment']->refresh();
        $this->assertEquals('retained', $data['enrollment']->status);

        $newEnrollment = Enrollment::where('student_id', $data['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)->first();
        $this->assertNotNull($newEnrollment);
        $this->assertEquals($this->sourceClassroom->id, $newEnrollment->classroom_id);
        $this->assertEquals('active', $newEnrollment->status);
        $this->assertEquals($data['enrollment']->id, $newEnrollment->promoted_from_enrollment_id);
    }

    /**
     * Test: Siswa lulus → student.status=graduated, user+wali dinonaktifkan.
     */
    public function test_graduated_students_have_accounts_deactivated(): void
    {
        $data = $this->createStudentWithEnrollment('Eka', '5555555555');

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

        $data['enrollment']->refresh();
        $this->assertEquals('graduated', $data['enrollment']->status);

        $data['student']->refresh();
        $this->assertEquals('graduated', $data['student']->status);

        $data['user']->refresh();
        $this->assertFalse($data['user']->is_active);

        $data['guardian']->refresh();
        $this->assertFalse($data['guardian']->is_active);

        $newEnrollment = Enrollment::where('student_id', $data['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)->first();
        $this->assertNull($newEnrollment);
    }

    /**
     * Test: Batch campuran (promosi + lulusan).
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

        $faniNew = Enrollment::where('student_id', $promoted['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)->first();
        $this->assertNotNull($faniNew);
        $this->assertEquals('active', $faniNew->status);

        $graduated['student']->refresh();
        $this->assertEquals('graduated', $graduated['student']->status);
        $graduated['user']->refresh();
        $this->assertFalse($graduated['user']->is_active);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST GROUP 2: Chunked Processing (via processChunk)
    // Simulasi perilaku event recursion di test environment:
    // memanggil processChunk() berulang secara sekuensial.
    // ─────────────────────────────────────────────────────────────────

    /**
     * Test: processChunk() memproses chunk kecil dengan benar.
     */
    public function test_process_chunk_handles_small_batch(): void
    {
        $data1 = $this->createStudentWithEnrollment('Hani', '8888888881');
        $data2 = $this->createStudentWithEnrollment('Irma', '8888888882');

        $chunk = [
            ['enrollment_id' => $data1['enrollment']->id, 'action' => 'promoted', 'target_classroom_id' => $this->targetClassroomA->id],
            ['enrollment_id' => $data2['enrollment']->id, 'action' => 'promoted', 'target_classroom_id' => $this->targetClassroomB->id],
        ];

        $result = $this->promotionService->processChunk($chunk, $this->targetPeriod->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['processed']);

        // Verifikasi enrollment baru
        $this->assertNotNull(
            Enrollment::where('student_id', $data1['student']->id)
                ->where('academic_period_id', $this->targetPeriod->id)->first()
        );
        $this->assertNotNull(
            Enrollment::where('student_id', $data2['student']->id)
                ->where('academic_period_id', $this->targetPeriod->id)->first()
        );
    }

    /**
     * Test: Simulasi event recursion — memproses 15 siswa dalam 2 chunk (10+5).
     *
     * Ini mensimulasikan perilaku sebenarnya di browser:
     * 1. Chunk 1: 10 siswa diproses, response dikirim ke browser
     * 2. Browser dispatch event lagi
     * 3. Chunk 2: 5 siswa terakhir diproses
     */
    public function test_chunked_recursion_processes_all_students_across_multiple_chunks(): void
    {
        // Buat 15 siswa (akan dipecah jadi 10 + 5)
        $allPromotions = [];
        for ($i = 1; $i <= 15; $i++) {
            $nisn = str_pad($i, 10, '0', STR_PAD_LEFT);
            $data = $this->createStudentWithEnrollment("Siswa {$i}", $nisn);
            $allPromotions[] = [
                'enrollment_id' => $data['enrollment']->id,
                'action' => 'promoted',
                'target_classroom_id' => $this->targetClassroomA->id,
            ];
        }

        $this->assertCount(15, $allPromotions);

        // Simulasikan event recursion: splice + processChunk berulang
        $pendingQueue = $allPromotions;
        $totalProcessed = 0;
        $chunkCount = 0;

        while (!empty($pendingQueue)) {
            $chunk = array_splice($pendingQueue, 0, PromotionService::CHUNK_SIZE);
            $result = $this->promotionService->processChunk($chunk, $this->targetPeriod->id);

            $this->assertTrue($result['success'], "Chunk #{$chunkCount} gagal: {$result['message']}");
            $totalProcessed += $result['processed'];
            $chunkCount++;
        }

        // Harus memproses dalam 2 chunk (10 + 5)
        $this->assertEquals(2, $chunkCount);
        $this->assertEquals(15, $totalProcessed);

        // Verifikasi semua 15 enrollment baru ada
        $newEnrollments = Enrollment::where('academic_period_id', $this->targetPeriod->id)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(15, $newEnrollments);

        // Verifikasi semua 15 enrollment lama berstatus 'promoted'
        $oldPromoted = Enrollment::where('academic_period_id', $this->sourcePeriod->id)
            ->where('status', 'promoted')
            ->count();
        $this->assertEquals(15, $oldPromoted);
    }

    /**
     * Test: Chunk campuran (promosi + lulusan) bekerja dengan benar.
     */
    public function test_chunk_with_mixed_actions(): void
    {
        $promoted = $this->createStudentWithEnrollment('Joko', '9999999901');
        $retained = $this->createStudentWithEnrollment('Kiki', '9999999902');
        $graduated = $this->createStudentWithEnrollment('Lina', '9999999903');

        $chunk = [
            ['enrollment_id' => $promoted['enrollment']->id, 'action' => 'promoted', 'target_classroom_id' => $this->targetClassroomA->id],
            ['enrollment_id' => $retained['enrollment']->id, 'action' => 'retained', 'target_classroom_id' => $this->sourceClassroom->id],
            ['enrollment_id' => $graduated['enrollment']->id, 'action' => 'graduated', 'target_classroom_id' => null],
        ];

        $result = $this->promotionService->processChunk($chunk, $this->targetPeriod->id);
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['processed']);

        // Joko: naik ke 8A
        $jokoNew = Enrollment::where('student_id', $promoted['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)->first();
        $this->assertNotNull($jokoNew);
        $this->assertEquals($this->targetClassroomA->id, $jokoNew->classroom_id);

        // Kiki: tinggal di 7A
        $kikiNew = Enrollment::where('student_id', $retained['student']->id)
            ->where('academic_period_id', $this->targetPeriod->id)->first();
        $this->assertNotNull($kikiNew);
        $this->assertEquals($this->sourceClassroom->id, $kikiNew->classroom_id);

        // Lina: lulus, user dinonaktifkan
        $graduated['student']->refresh();
        $this->assertEquals('graduated', $graduated['student']->status);
        $graduated['user']->refresh();
        $this->assertFalse($graduated['user']->is_active);
        $graduated['guardian']->refresh();
        $this->assertFalse($graduated['guardian']->is_active);
    }
}
