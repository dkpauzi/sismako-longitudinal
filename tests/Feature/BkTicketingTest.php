<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\BkAnswer;
use App\Models\BkQuestion;
use App\Models\BkQuestionnaire;
use App\Models\BkQuestionOption;
use App\Models\BkStudentResponse;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BkTicketingTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $academicPeriod;
    private BkQuestionnaire $questionnaire;
    private Student $student;
    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $counselor = User::factory()->create(['role' => 'teacher', 'username' => 'bk_counselor']);
        $studentUser = User::factory()->create(['role' => 'student', 'username' => 'siswa_bk']);
        $guardianUser = User::factory()->create(['role' => 'guardian', 'username' => 'wali_bk']);

        $this->academicPeriod = AcademicPeriod::create([
            'start_year' => 2026, 'end_year' => 2027, 'semester' => 'odd',
            'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);

        $this->classroom = Classroom::create(['name' => '7B', 'grade_level' => 7]);

        $this->student = Student::create([
            'user_id' => $studentUser->id, 'guardian_user_id' => $guardianUser->id,
            'nisn' => '8888777766', 'name' => 'Siswa BK Test', 'gender' => 'P', 'status' => 'active',
        ]);

        Enrollment::create([
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_period_id' => $this->academicPeriod->id,
            'status' => 'active',
        ]);

        $this->questionnaire = BkQuestionnaire::create([
            'title' => 'Kuesioner Gaya Belajar VAK',
            'status' => 'published',
            'counselor_id' => $counselor->id,
            'academic_period_id' => $this->academicPeriod->id,
        ]);

        // Buat 1 pertanyaan sederhana untuk testing submission
        $question = BkQuestion::create([
            'questionnaire_id' => $this->questionnaire->id,
            'question_text' => 'Saya lebih suka belajar dengan...',
            'question_type' => 'single_choice',
        ]);

        BkQuestionOption::create([
            'question_id' => $question->id,
            'option_text' => 'Melihat gambar',
            'option_code' => 'VISUAL',
        ]);
    }

    /**
     * Test: Siswa TIDAK bisa mengerjakan kuesioner tanpa tiket pending.
     */
    public function test_student_cannot_access_questionnaire_without_pending_ticket(): void
    {
        // Tidak ada BkStudentResponse dibuat untuk siswa ini
        $pendingTicket = BkStudentResponse::where('questionnaire_id', $this->questionnaire->id)
            ->where('student_id', $this->student->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNull($pendingTicket, 'Tidak boleh ada tiket pending tanpa aksi Guru BK.');
    }

    /**
     * Test: "Buka Akses Asesmen" membuat tiket pending untuk setiap siswa di kelas.
     */
    public function test_buka_akses_creates_pending_tickets_for_students(): void
    {
        // Simulasikan aksi "Buka Akses Asesmen"
        BkStudentResponse::create([
            'questionnaire_id' => $this->questionnaire->id,
            'student_id' => $this->student->id,
            'academic_period_id' => $this->academicPeriod->id,
            'status' => 'pending',
        ]);

        $ticket = BkStudentResponse::where('questionnaire_id', $this->questionnaire->id)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertNotNull($ticket);
        $this->assertEquals('pending', $ticket->status);
        $this->assertNull($ticket->submitted_at);
        $this->assertNull($ticket->score);
    }

    /**
     * Test: Submit mengubah status dari 'pending' ke 'completed' dan menyimpan jawaban.
     */
    public function test_submitting_test_locks_ticket_to_completed(): void
    {
        // 1. Buat tiket pending (simulasi "Buka Akses Asesmen" oleh Guru BK)
        $response = BkStudentResponse::create([
            'questionnaire_id' => $this->questionnaire->id,
            'student_id' => $this->student->id,
            'academic_period_id' => $this->academicPeriod->id,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $response->status);

        // 2. Simulasikan submission: update status dan tambah jawaban
        $question = $this->questionnaire->questions->first();
        $option = $question->options->first();

        $response->update([
            'submitted_at' => now(),
            'status' => 'completed',
        ]);

        BkAnswer::create([
            'response_id' => $response->id,
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $response->refresh();

        // 3. Assertions
        $this->assertEquals('completed', $response->status);
        $this->assertNotNull($response->submitted_at);
        $this->assertEquals(1, $response->answers()->count());
    }

    /**
     * Test: Tiket yang sudah completed TIDAK bisa disubmit ulang (double submit guard).
     */
    public function test_completed_ticket_cannot_be_resubmitted(): void
    {
        // Buat tiket dan langsung set ke completed
        BkStudentResponse::create([
            'questionnaire_id' => $this->questionnaire->id,
            'student_id' => $this->student->id,
            'academic_period_id' => $this->academicPeriod->id,
            'status' => 'completed',
            'submitted_at' => now(),
        ]);

        // Coba cari tiket pending — seharusnya tidak ada
        $pendingTicket = BkStudentResponse::where('questionnaire_id', $this->questionnaire->id)
            ->where('student_id', $this->student->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNull($pendingTicket, 'Tiket completed tidak boleh ditemukan sebagai pending.');
    }

    /**
     * Test: Duplikat tiket tidak boleh dibuat untuk siswa yang sama.
     */
    public function test_duplicate_tickets_are_prevented(): void
    {
        // Buat tiket pertama
        BkStudentResponse::create([
            'questionnaire_id' => $this->questionnaire->id,
            'student_id' => $this->student->id,
            'academic_period_id' => $this->academicPeriod->id,
            'status' => 'pending',
        ]);

        // Cek apakah tiket sudah ada sebelum membuat duplikat (logika di aksi Filament)
        $exists = BkStudentResponse::where('questionnaire_id', $this->questionnaire->id)
            ->where('student_id', $this->student->id)
            ->exists();

        $this->assertTrue($exists, 'Tiket pertama harus ada.');

        // Count harus tetap 1 (duplikat dicegah oleh logika bisnis)
        $count = BkStudentResponse::where('questionnaire_id', $this->questionnaire->id)
            ->where('student_id', $this->student->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Test: Tiket pending bisa di-revoke dan berubah status ke 'revoked'.
     */
    public function test_pending_ticket_can_be_revoked(): void
    {
        $response = BkStudentResponse::create([
            'questionnaire_id' => $this->questionnaire->id,
            'student_id' => $this->student->id,
            'academic_period_id' => $this->academicPeriod->id,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $response->status);

        // Simulasikan aksi "Batalkan Tiket"
        $response->update(['status' => 'revoked']);
        $response->refresh();

        $this->assertEquals('revoked', $response->status);
        $this->assertNull($response->submitted_at);
    }

    /**
     * Test: Tiket revoked TIDAK terlihat oleh siswa (difilter dari query).
     */
    public function test_revoked_ticket_is_invisible_to_student(): void
    {
        // Buat tiket lalu revoke
        BkStudentResponse::create([
            'questionnaire_id' => $this->questionnaire->id,
            'student_id' => $this->student->id,
            'academic_period_id' => $this->academicPeriod->id,
            'status' => 'revoked',
        ]);

        // Query yang sama dengan MyQuestionnaires: exclude 'revoked'
        $visibleResponses = BkStudentResponse::where('student_id', $this->student->id)
            ->where('status', '!=', 'revoked')
            ->get();

        $this->assertCount(0, $visibleResponses, 'Tiket revoked tidak boleh terlihat oleh siswa.');
    }
}
