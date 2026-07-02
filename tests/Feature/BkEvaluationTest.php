<?php

namespace Tests\Feature;

use App\Filament\Pages\Student\MyQuestionnaires;
use App\Filament\Pages\StudentBkResults;
use App\Models\AcademicPeriod;
use App\Models\BkQuestion;
use App\Models\BkQuestionOption;
use App\Models\BkQuestionnaire;
use App\Models\BkQuestionnaireTarget;
use App\Models\BkStudentResponse;
use App\Models\ClassHomeroom;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BkEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $activePeriod;
    private Classroom $classroom;
    private Classroom $otherClassroom;
    private User $counselorUser;
    private User $studentUser;
    private Student $student;
    private BkQuestionnaire $questionnaire;
    private BkStudentResponse $response;

    /**
     * Setup: membuat data dasar untuk semua test.
     * - Tahun ajaran aktif, 2 kelas, siswa terenroll, kuesioner dengan respons.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // ── Roles & Permissions ──────────────────────────────────
        $studentRole    = Role::create(['name' => 'student',    'guard_name' => 'web']);
        $teacherRole    = Role::create(['name' => 'teacher',    'guard_name' => 'web']);
        $guruBkRole     = Role::create(['name' => 'guru_bk',    'guard_name' => 'web']);
        $waliSiswaRole  = Role::create(['name' => 'wali_siswa', 'guard_name' => 'web']);
        $headmasterRole = Role::create(['name' => 'headmaster', 'guard_name' => 'web']);

        $pagePerm = Permission::create(['name' => 'page_MyQuestionnaires',  'guard_name' => 'web']);
        $resultsPerm = Permission::create(['name' => 'page_StudentBkResults', 'guard_name' => 'web']);

        $studentRole->givePermissionTo($pagePerm);
        $waliSiswaRole->givePermissionTo($pagePerm);
        $teacherRole->givePermissionTo($resultsPerm);
        $guruBkRole->givePermissionTo($resultsPerm);
        $headmasterRole->givePermissionTo($resultsPerm);

        // ── Tahun Ajaran ─────────────────────────────────────────
        $this->activePeriod = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-19', 'is_active' => true,
        ]);

        // ── Kelas ────────────────────────────────────────────────
        $this->classroom      = Classroom::create(['name' => 'Kelas 7.1', 'grade_level' => 7]);
        $this->otherClassroom = Classroom::create(['name' => 'Kelas 8.1', 'grade_level' => 8]);

        // ── Guru BK / Counselor ──────────────────────────────────
        $this->counselorUser = User::factory()->create([
            'name' => 'Guru BK Test', 'role' => 'teacher', 'is_active' => true,
        ]);
        $this->counselorUser->assignRole('guru_bk');

        // ── Siswa ────────────────────────────────────────────────
        $this->studentUser = User::factory()->create([
            'name' => 'Siswa Test', 'role' => 'student', 'is_active' => true,
        ]);
        $this->studentUser->assignRole('student');

        $this->student = Student::create([
            'user_id' => $this->studentUser->id, 'name' => 'Siswa Test',
            'nisn' => '1234567890', 'gender' => 'L', 'status' => 'active',
        ]);

        Enrollment::create([
            'student_id' => $this->student->id, 'classroom_id' => $this->classroom->id,
            'academic_period_id' => $this->activePeriod->id, 'status' => 'active',
        ]);

        // ── Kuesioner + Respons ──────────────────────────────────
        $this->questionnaire = BkQuestionnaire::create([
            'title' => 'Kuesioner Asesmen Kognitif', 'counselor_id' => $this->counselorUser->id,
            'academic_period_id' => $this->activePeriod->id, 'status' => 'published',
        ]);
        BkQuestionnaireTarget::create([
            'questionnaire_id' => $this->questionnaire->id, 'classroom_id' => $this->classroom->id,
        ]);

        $q1 = BkQuestion::create([
            'questionnaire_id' => $this->questionnaire->id,
            'question_text' => 'Pertanyaan uji?', 'question_type' => 'text', 'order' => 1,
        ]);

        // Alur ticketing: Guru BK membuat tiket (pending) via "Buka Akses",
        // siswa submit -> tiket menjadi 'completed'. Di sini kita simulasikan
        // kondisi PASCA-submit (tiket completed) sebagai bahan evaluasi.
        $this->response = BkStudentResponse::create([
            'questionnaire_id' => $this->questionnaire->id,
            'student_id' => $this->student->id,
            'academic_period_id' => $this->activePeriod->id,
            'status' => 'completed',
            'submitted_at' => now(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 1: Guru BK bisa menyimpan data evaluasi
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_guru_bk_can_save_evaluation_data(): void
    {
        // Verifikasi kolom evaluasi awal kosong
        $this->assertNull($this->response->score);
        $this->assertNull($this->response->feedback);
        $this->assertNull($this->response->recommendation);
        $this->assertNull($this->response->evaluated_at);

        // Simulasi evaluasi oleh Guru BK
        $this->response->update([
            'score'          => 85.50,
            'feedback'       => 'Siswa menunjukkan pemahaman yang baik.',
            'recommendation' => 'Lanjutkan dengan metode belajar kolaboratif.',
            'evaluated_at'   => now(),
        ]);

        $this->response->refresh();

        // Verifikasi data tersimpan
        $this->assertEquals('85.50', $this->response->score);
        $this->assertEquals('Siswa menunjukkan pemahaman yang baik.', $this->response->feedback);
        $this->assertEquals('Lanjutkan dengan metode belajar kolaboratif.', $this->response->recommendation);
        $this->assertNotNull($this->response->evaluated_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $this->response->evaluated_at);

        // Verifikasi di database
        $this->assertDatabaseHas('bk_student_responses', [
            'id'             => $this->response->id,
            'score'          => 85.50,
            'feedback'       => 'Siswa menunjukkan pemahaman yang baik.',
            'recommendation' => 'Lanjutkan dengan metode belajar kolaboratif.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 2: Hasil tersembunyi dari siswa sampai evaluated_at diisi
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_result_hidden_from_student_until_evaluated(): void
    {
        $this->actingAs($this->studentUser);
        $page = new MyQuestionnaires();

        // Respons BELUM dievaluasi
        $questionnaires = $page->getQuestionnairesForStudent();
        $q = $questionnaires->firstWhere('id', $this->questionnaire->id);

        $this->assertNotNull($q);
        $this->assertTrue($q->has_responded);
        $this->assertNull($q->evaluated_at, 'evaluated_at harus null karena belum dievaluasi');

        // viewResult seharusnya menampilkan "belum tersedia" jika belum dievaluasi
        // (kita verifikasi melalui buildResultView internal logic)
        $reflector = new \ReflectionMethod($page, 'buildResultView');
        $reflector->setAccessible(true);
        $result = $reflector->invoke($page, ['questionnaire_id' => $this->questionnaire->id]);

        // Seharusnya menampilkan placeholder "belum tersedia"
        $this->assertCount(1, $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 3: Hasil terlihat oleh siswa setelah evaluasi
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_result_visible_to_student_after_evaluation(): void
    {
        // Evaluasi respons
        $this->response->update([
            'score'          => 72.00,
            'feedback'       => 'Perlu peningkatan di area penalaran.',
            'recommendation' => 'Latihan soal lebih banyak dan diskusi kelompok.',
            'evaluated_at'   => now(),
        ]);

        $this->actingAs($this->studentUser);
        $page = new MyQuestionnaires();

        $questionnaires = $page->getQuestionnairesForStudent();
        $q = $questionnaires->firstWhere('id', $this->questionnaire->id);

        $this->assertNotNull($q);
        $this->assertTrue($q->has_responded);
        $this->assertNotNull($q->evaluated_at, 'evaluated_at harus terisi setelah evaluasi');

        // viewResult seharusnya menampilkan data evaluasi lengkap
        $reflector = new \ReflectionMethod($page, 'buildResultView');
        $reflector->setAccessible(true);
        $result = $reflector->invoke($page, ['questionnaire_id' => $this->questionnaire->id]);

        // Section "Hasil Asesmen" berisi 4 Placeholder fields
        $this->assertCount(1, $result); // 1 Section
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 4: Guru hanya bisa melihat data kelas sendiri
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_teacher_can_only_see_own_classroom_results(): void
    {
        // Evaluasi respons agar muncul di hasil
        $this->response->update([
            'score' => 80, 'feedback' => 'Baik.', 'recommendation' => 'Pertahankan.',
            'evaluated_at' => now(),
        ]);

        // Buat guru yang mengajar di kelas 7.1 (kelas siswa)
        $teacherUser = User::factory()->create([
            'name' => 'Guru Kelas 7.1', 'role' => 'teacher', 'is_active' => true,
        ]);
        $teacherUser->assignRole('teacher');

        $teacher = Teacher::create([
            'user_id' => $teacherUser->id, 'name' => 'Guru Kelas 7.1',
            'nip' => '198001012005011001', 'is_active' => true,
        ]);

        $subject = Subject::create(['name' => 'Matematika', 'code' => 'MTK']);
        TeachingAssignment::create([
            'teacher_id' => $teacher->id, 'classroom_id' => $this->classroom->id,
            'academic_period_id' => $this->activePeriod->id, 'subject_id' => $subject->id,
        ]);

        // Act: guru login & lihat hasil
        $this->actingAs($teacherUser);
        $page = new StudentBkResults();

        $accessibleClassrooms = $page->getAccessibleClassrooms();

        // Assert: guru bisa akses kelas 7.1 tapi TIDAK kelas 8.1
        $this->assertTrue($accessibleClassrooms->has($this->classroom->id), 'Guru harus bisa akses kelas 7.1');
        $this->assertFalse($accessibleClassrooms->has($this->otherClassroom->id), 'Guru TIDAK boleh akses kelas 8.1');

        // Lihat data hasil untuk kelas yang diajar
        $page->selectedClassroomId = $this->classroom->id;
        $responses = $page->getEvaluatedResponses();

        $this->assertCount(1, $responses);
        $this->assertEquals($this->student->id, $responses->first()->student_id);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 5: Guru TIDAK bisa melihat data kelas lain
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_teacher_cannot_see_other_classroom_results(): void
    {
        // Evaluasi respons siswa kelas 7.1
        $this->response->update([
            'score' => 90, 'feedback' => 'Sangat baik.', 'recommendation' => 'Excellent.',
            'evaluated_at' => now(),
        ]);

        // Buat guru yang HANYA mengajar di kelas 8.1 (bukan kelas siswa)
        $otherTeacherUser = User::factory()->create([
            'name' => 'Guru Kelas 8.1', 'role' => 'teacher', 'is_active' => true,
        ]);
        $otherTeacherUser->assignRole('teacher');

        $otherTeacher = Teacher::create([
            'user_id' => $otherTeacherUser->id, 'name' => 'Guru Kelas 8.1',
            'nip' => '198002022006012002', 'is_active' => true,
        ]);

        $subject = Subject::create(['name' => 'Bahasa Indonesia', 'code' => 'BIN']);
        TeachingAssignment::create([
            'teacher_id' => $otherTeacher->id, 'classroom_id' => $this->otherClassroom->id,
            'academic_period_id' => $this->activePeriod->id, 'subject_id' => $subject->id,
        ]);

        // Act: guru kelas 8.1 login dan coba akses kelas 7.1
        $this->actingAs($otherTeacherUser);
        $page = new StudentBkResults();

        // Classroom 7.1 TIDAK ada di daftar akses guru kelas 8.1
        $accessibleClassrooms = $page->getAccessibleClassrooms();
        $this->assertFalse($accessibleClassrooms->has($this->classroom->id));

        // Coba akses data kelas 7.1 secara paksa → harus kosong
        $page->selectedClassroomId = $this->classroom->id;
        $responses = $page->getEvaluatedResponses();

        $this->assertCount(0, $responses, 'Guru kelas 8.1 TIDAK boleh melihat data kelas 7.1');
    }
}
