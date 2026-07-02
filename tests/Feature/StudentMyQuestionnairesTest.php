<?php

namespace Tests\Feature;

use App\Filament\Pages\Student\MyQuestionnaires;
use App\Models\AcademicPeriod;
use App\Models\BkAnswer;
use App\Models\BkQuestion;
use App\Models\BkQuestionOption;
use App\Models\BkQuestionnaire;
use App\Models\BkQuestionnaireTarget;
use App\Models\BkStudentResponse;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentMyQuestionnairesTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $activePeriod;
    private Classroom $classroom;
    private User $studentUser;
    private Student $student;

    /**
     * Common setup: create an active period, classroom, student user, student profile,
     * enrollment, and the 'student' role with the required permission.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create the 'student' role and permission
        $role = Role::create(['name' => 'student', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'page_MyQuestionnaires', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        // Active academic period
        $this->activePeriod = AcademicPeriod::create([
            'start_year'  => 2025,
            'end_year'    => 2026,
            'semester'    => 'odd',
            'start_date'  => '2025-07-14',
            'end_date'    => '2025-12-19',
            'is_active'   => true,
        ]);

        // Classroom
        $this->classroom = Classroom::create([
            'name'        => 'Kelas 7.1',
            'grade_level' => 7,
        ]);

        // Student user with role
        $this->studentUser = User::factory()->create([
            'name'      => 'Siswa Test',
            'role'      => 'student',
            'is_active' => true,
        ]);
        $this->studentUser->assignRole('student');

        // Student profile
        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'name'    => 'Siswa Test',
            'nisn'    => '1234567890',
            'gender'  => 'L',
            'status'  => 'active',
        ]);

        // Enroll the student in the classroom for the active period
        Enrollment::create([
            'student_id'         => $this->student->id,
            'classroom_id'       => $this->classroom->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'active',
        ]);
    }

    // ─── AUTHORIZATION ───────────────────────────────────────────

    /** @test */
    public function test_student_can_access_my_questionnaires_page(): void
    {
        $this->assertTrue(
            $this->studentUser->hasRole('student'),
            'Student user should have the student role'
        );

        $this->assertTrue(
            $this->studentUser->can('page_MyQuestionnaires'),
            'Student should have the page_MyQuestionnaires permission'
        );
    }

    /** @test */
    public function test_teacher_cannot_access_my_questionnaires_page(): void
    {
        $teacherRole = Role::create(['name' => 'teacher', 'guard_name' => 'web']);

        $teacherUser = User::factory()->create([
            'name'      => 'Guru Test',
            'role'      => 'teacher',
            'is_active' => true,
        ]);
        $teacherUser->assignRole('teacher');

        $this->assertFalse(
            $teacherUser->can('page_MyQuestionnaires'),
            'Teacher should NOT have the page_MyQuestionnaires permission'
        );
    }

    /** @test */
    public function test_can_access_returns_false_for_user_without_student_profile(): void
    {
        // User with student role but no linked Student model
        $orphanUser = User::factory()->create([
            'name'      => 'Orphan Student',
            'role'      => 'student',
            'is_active' => true,
        ]);
        $orphanUser->assignRole('student');

        // canAccess() requires both the role AND a linked Student
        $this->actingAs($orphanUser);
        $this->assertFalse(MyQuestionnaires::canAccess());
    }

    // ─── QUESTIONNAIRE VISIBILITY (TICKETING) ────────────────────
    // Sistem ticketing: siswa HANYA melihat kuesioner published yang
    // Guru BK telah membukakan tiket (BkStudentResponse pending) untuknya.
    // Target kelas dipakai saat "Buka Akses" membuat tiket massal, bukan
    // sebagai filter langsung di halaman siswa.

    /** @test */
    public function test_only_published_questionnaires_with_ticket_are_loaded(): void
    {
        // Create a counselor user
        $counselor = User::factory()->create(['name' => 'Guru BK', 'is_active' => true]);

        // Questionnaire 1: published + tiket pending ✅ (satu-satunya yang tampil)
        $q1 = BkQuestionnaire::create([
            'title'              => 'Kuesioner Minat Bakat',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'published',
        ]);
        BkQuestionnaireTarget::create([
            'questionnaire_id' => $q1->id,
            'classroom_id'     => $this->classroom->id,
        ]);
        BkStudentResponse::create([
            'questionnaire_id'   => $q1->id,
            'student_id'         => $this->student->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'pending',
        ]);

        // Questionnaire 2: draft + tiket pending ❌ (draft tidak tampil walau bertiket)
        $q2 = BkQuestionnaire::create([
            'title'              => 'Kuesioner Draft',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'draft',
        ]);
        BkStudentResponse::create([
            'questionnaire_id'   => $q2->id,
            'student_id'         => $this->student->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'pending',
        ]);

        // Questionnaire 3: published + target kelas siswa, TANPA tiket ❌
        $q3 = BkQuestionnaire::create([
            'title'              => 'Kuesioner Tanpa Tiket',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'published',
        ]);
        BkQuestionnaireTarget::create([
            'questionnaire_id' => $q3->id,
            'classroom_id'     => $this->classroom->id,
        ]);

        // Questionnaire 4: published + tiket yang sudah DICABUT (revoked) ❌
        $q4 = BkQuestionnaire::create([
            'title'              => 'Kuesioner Tiket Dicabut',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'published',
        ]);
        BkStudentResponse::create([
            'questionnaire_id'   => $q4->id,
            'student_id'         => $this->student->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'revoked',
        ]);

        // Act: simulate loading the page as the student
        $this->actingAs($this->studentUser);
        $page = new MyQuestionnaires();
        $questionnaires = $page->getQuestionnairesForStudent();

        // Assert: only q1 should appear
        $this->assertCount(1, $questionnaires);
        $this->assertEquals($q1->id, $questionnaires->first()->id);
    }

    // ─── TIME RESTRICTION LOGIC ──────────────────────────────────

    /** @test */
    public function test_time_restriction_status_returns_correct_labels(): void
    {
        $counselor = User::factory()->create(['name' => 'Guru BK', 'is_active' => true]);

        // Questionnaire that hasn't started yet
        $futureQ = BkQuestionnaire::create([
            'title'              => 'Kuesioner Masa Depan',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'published',
            'starts_at'          => now()->addDays(5),
            'ends_at'            => now()->addDays(10),
        ]);
        BkQuestionnaireTarget::create([
            'questionnaire_id' => $futureQ->id,
            'classroom_id'     => $this->classroom->id,
        ]);

        // Questionnaire that has already ended
        $pastQ = BkQuestionnaire::create([
            'title'              => 'Kuesioner Expired',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'published',
            'starts_at'          => now()->subDays(10),
            'ends_at'            => now()->subDays(1),
        ]);
        BkQuestionnaireTarget::create([
            'questionnaire_id' => $pastQ->id,
            'classroom_id'     => $this->classroom->id,
        ]);

        // Questionnaire that is currently open
        $openQ = BkQuestionnaire::create([
            'title'              => 'Kuesioner Aktif',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'published',
            'starts_at'          => now()->subDays(1),
            'ends_at'            => now()->addDays(5),
        ]);
        BkQuestionnaireTarget::create([
            'questionnaire_id' => $openQ->id,
            'classroom_id'     => $this->classroom->id,
        ]);

        // Questionnaire with no time constraints (always open)
        $noTimeQ = BkQuestionnaire::create([
            'title'              => 'Kuesioner Tanpa Batas Waktu',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'published',
        ]);
        BkQuestionnaireTarget::create([
            'questionnaire_id' => $noTimeQ->id,
            'classroom_id'     => $this->classroom->id,
        ]);

        $this->actingAs($this->studentUser);
        $page = new MyQuestionnaires();

        // Future: not yet open
        $this->assertEquals('not_started', $page->getTimeStatus($futureQ));

        // Past: already closed
        $this->assertEquals('closed', $page->getTimeStatus($pastQ));

        // Open: within window
        $this->assertEquals('open', $page->getTimeStatus($openQ));

        // No constraints: always open
        $this->assertEquals('open', $page->getTimeStatus($noTimeQ));
    }

    // ─── FORM SUBMISSION & DB TRANSACTION ────────────────────────

    /** @test */
    public function test_submission_creates_response_and_answers_in_transaction(): void
    {
        $counselor = User::factory()->create(['name' => 'Guru BK', 'is_active' => true]);

        // Create a full questionnaire with different question types
        $questionnaire = BkQuestionnaire::create([
            'title'              => 'Kuesioner Lengkap',
            'instructions'       => 'Jawab semua pertanyaan.',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'published',
        ]);
        BkQuestionnaireTarget::create([
            'questionnaire_id' => $questionnaire->id,
            'classroom_id'     => $this->classroom->id,
        ]);

        // Q1: Single choice
        $q1 = BkQuestion::create([
            'questionnaire_id' => $questionnaire->id,
            'question_text'    => 'Bagaimana perasaanmu di sekolah?',
            'question_type'    => 'single_choice',
            'order'            => 1,
        ]);
        $opt1a = BkQuestionOption::create([
            'question_id' => $q1->id, 'option_text' => 'Senang', 'score_weight' => 3, 'order' => 1,
        ]);
        $opt1b = BkQuestionOption::create([
            'question_id' => $q1->id, 'option_text' => 'Biasa', 'score_weight' => 2, 'order' => 2,
        ]);

        // Q2: Multiple choice
        $q2 = BkQuestion::create([
            'questionnaire_id' => $questionnaire->id,
            'question_text'    => 'Kegiatan apa yang kamu sukai?',
            'question_type'    => 'multiple_choice',
            'order'            => 2,
        ]);
        $opt2a = BkQuestionOption::create([
            'question_id' => $q2->id, 'option_text' => 'Olahraga', 'score_weight' => 1, 'order' => 1,
        ]);
        $opt2b = BkQuestionOption::create([
            'question_id' => $q2->id, 'option_text' => 'Musik', 'score_weight' => 1, 'order' => 2,
        ]);
        $opt2c = BkQuestionOption::create([
            'question_id' => $q2->id, 'option_text' => 'Membaca', 'score_weight' => 1, 'order' => 3,
        ]);

        // Q3: Text (essay)
        $q3 = BkQuestion::create([
            'questionnaire_id' => $questionnaire->id,
            'question_text'    => 'Ceritakan cita-citamu.',
            'question_type'    => 'text',
            'order'            => 3,
        ]);

        // Q4: Scale (Likert)
        $q4 = BkQuestion::create([
            'questionnaire_id' => $questionnaire->id,
            'question_text'    => 'Seberapa sering kamu merasa cemas?',
            'question_type'    => 'scale',
            'order'            => 4,
        ]);
        $opt4a = BkQuestionOption::create([
            'question_id' => $q4->id, 'option_text' => 'Tidak Pernah', 'score_weight' => 1, 'order' => 1,
        ]);
        $opt4b = BkQuestionOption::create([
            'question_id' => $q4->id, 'option_text' => 'Kadang-kadang', 'score_weight' => 2, 'order' => 2,
        ]);

        // Ticketing: Guru BK membuka akses -> tiket pending dibuat lebih dulu.
        // submitQuestionnaire() TIDAK membuat respons baru, melainkan mengisi tiket ini.
        $ticket = BkStudentResponse::create([
            'questionnaire_id'   => $questionnaire->id,
            'student_id'         => $this->student->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'pending',
        ]);

        // Act: simulate form submission
        $this->actingAs($this->studentUser);
        $page = new MyQuestionnaires();

        $formData = [
            "question_{$q1->id}" => (string) $opt1a->id,       // Single choice: selected 'Senang'
            "question_{$q2->id}" => [$opt2a->id, $opt2c->id],  // Multiple choice: 'Olahraga' and 'Membaca'
            "question_{$q3->id}" => 'Saya ingin menjadi dokter.',  // Text answer
            "question_{$q4->id}" => (string) $opt4b->id,       // Scale: 'Kadang-kadang'
        ];

        $page->submitQuestionnaire($questionnaire->id, $formData);

        // Assert: tiket terisi menjadi completed (bukan membuat baris baru)
        $this->assertDatabaseHas('bk_student_responses', [
            'id'               => $ticket->id,
            'questionnaire_id' => $questionnaire->id,
            'student_id'       => $this->student->id,
            'status'           => 'completed',
        ]);

        $response = BkStudentResponse::where('questionnaire_id', $questionnaire->id)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertNotNull($response->submitted_at, 'submitted_at should be set');

        // Assert: Q1 single choice answer
        $this->assertDatabaseHas('bk_answers', [
            'response_id'        => $response->id,
            'question_id'        => $q1->id,
            'selected_option_id' => $opt1a->id,
            'text_answer'        => null,
        ]);

        // Assert: Q2 multiple choice answers (one row per selected option)
        $this->assertDatabaseHas('bk_answers', [
            'response_id'        => $response->id,
            'question_id'        => $q2->id,
            'selected_option_id' => $opt2a->id,
        ]);
        $this->assertDatabaseHas('bk_answers', [
            'response_id'        => $response->id,
            'question_id'        => $q2->id,
            'selected_option_id' => $opt2c->id,
        ]);
        // Opt2b (Musik) should NOT be present
        $this->assertDatabaseMissing('bk_answers', [
            'response_id'        => $response->id,
            'question_id'        => $q2->id,
            'selected_option_id' => $opt2b->id,
        ]);

        // Assert: Q3 text answer
        $this->assertDatabaseHas('bk_answers', [
            'response_id'        => $response->id,
            'question_id'        => $q3->id,
            'selected_option_id' => null,
            'text_answer'        => 'Saya ingin menjadi dokter.',
        ]);

        // Assert: Q4 scale answer
        $this->assertDatabaseHas('bk_answers', [
            'response_id'        => $response->id,
            'question_id'        => $q4->id,
            'selected_option_id' => $opt4b->id,
        ]);

        // Total answers: 1 (q1) + 2 (q2 multiple) + 1 (q3) + 1 (q4) = 5
        $this->assertEquals(5, BkAnswer::where('response_id', $response->id)->count());
    }

    /** @test */
    public function test_student_cannot_submit_same_questionnaire_twice(): void
    {
        $counselor = User::factory()->create(['name' => 'Guru BK', 'is_active' => true]);

        $questionnaire = BkQuestionnaire::create([
            'title'              => 'Kuesioner Sekali Submit',
            'counselor_id'       => $counselor->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'published',
        ]);
        BkQuestionnaireTarget::create([
            'questionnaire_id' => $questionnaire->id,
            'classroom_id'     => $this->classroom->id,
        ]);

        $q1 = BkQuestion::create([
            'questionnaire_id' => $questionnaire->id,
            'question_text'    => 'Pertanyaan sederhana?',
            'question_type'    => 'text',
            'order'            => 1,
        ]);

        // Tiket yang SUDAH selesai (submit pertama telah terjadi)
        BkStudentResponse::create([
            'questionnaire_id'   => $questionnaire->id,
            'student_id'         => $this->student->id,
            'academic_period_id' => $this->activePeriod->id,
            'status'             => 'completed',
            'submitted_at'       => now(),
        ]);

        // Verify the student has already responded
        $this->actingAs($this->studentUser);
        $page = new MyQuestionnaires();
        $questionnaires = $page->getQuestionnairesForStudent();

        // The questionnaire should still be listed but marked as responded
        $q = $questionnaires->firstWhere('id', $questionnaire->id);
        $this->assertNotNull($q);
        $this->assertTrue($q->has_responded);

        // Submit kedua harus DITOLAK: tidak ada tiket 'pending' tersisa,
        // sehingga tidak ada jawaban yang tercatat.
        $page->submitQuestionnaire($questionnaire->id, [
            "question_{$q1->id}" => 'Jawaban percobaan kedua',
        ]);

        $this->assertSame(0, BkAnswer::count(), 'Submit kedua tidak boleh membuat jawaban');
        $this->assertSame(1, BkStudentResponse::where('questionnaire_id', $questionnaire->id)
            ->where('student_id', $this->student->id)
            ->count(), 'Tidak boleh ada respons ganda');
    }
}
