<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\ClassHomeroom;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Policies\StudentSubjectEnrollmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage untuk StudentSubjectEnrollmentPolicy::update() (Audit 3.5).
 *
 * Aturan: penilaian ekstrakurikuler HANYA boleh oleh super_admin/admin,
 * atau Wali Kelas AKTIF (ClassHomeroom is_current) untuk kelas DAN tahun
 * ajaran yang sama dengan SK ekskul. Guru biasa & mantan wali kelas ditolak.
 */
class StudentSubjectEnrollmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private StudentSubjectEnrollmentPolicy $policy;
    private AcademicPeriod $currentPeriod;
    private AcademicPeriod $pastPeriod;
    private Classroom $classroom;
    private StudentSubjectEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new StudentSubjectEnrollmentPolicy();

        foreach (['super_admin', 'admin', 'teacher'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->pastPeriod = AcademicPeriod::create([
            'start_year' => 2024, 'end_year' => 2025, 'semester' => 'odd',
            'start_date' => '2024-07-15', 'end_date' => '2024-12-20', 'is_active' => false,
        ]);
        $this->currentPeriod = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-19', 'is_active' => true,
        ]);

        $this->classroom = Classroom::create(['name' => 'Kelas 8.1', 'grade_level' => 8]);

        // Siswa + SK Ekskul (extracurricular) di kelas 8.1, TAHUN AJARAN AKTIF.
        $studentUser = User::factory()->create(['name' => 'Siswa Uji', 'role' => 'student']);
        $student = \App\Models\Student::create([
            'user_id' => $studentUser->id, 'name' => 'Siswa Uji',
            'nisn' => '9990001112', 'gender' => 'L', 'status' => 'active',
        ]);

        $pembina = $this->makeTeacher('Pembina Pramuka', '111');
        $ekskulSubject = Subject::create(['name' => 'Pramuka', 'code' => 'PRM', 'type' => 'extracurricular']);
        $assignment = TeachingAssignment::create([
            'academic_period_id' => $this->currentPeriod->id,
            'teacher_id' => $pembina->id,
            'subject_id' => $ekskulSubject->id,
            'classroom_id' => $this->classroom->id,
        ]);

        $this->enrollment = StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'teaching_assignment_id' => $assignment->id,
        ]);
    }

    /** Helper: buat User(role) + profil Teacher tertaut. */
    private function makeTeacher(string $name, string $nip, string $role = 'teacher'): Teacher
    {
        $user = User::factory()->create(['name' => $name, 'role' => 'teacher', 'is_active' => true]);
        $user->assignRole($role);

        return Teacher::create([
            'user_id' => $user->id, 'name' => $name, 'nip' => $nip, 'is_active' => true,
        ]);
    }

    /** @test */
    public function admin_can_update_extracurricular_grade(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('admin');

        $this->assertTrue($this->policy->update($user, $this->enrollment));
    }

    /** @test */
    public function super_admin_can_update_extracurricular_grade(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->assignRole('super_admin');

        $this->assertTrue($this->policy->update($user, $this->enrollment));
    }

    /** @test */
    public function active_homeroom_teacher_can_update_extracurricular_grade(): void
    {
        $waliKelas = $this->makeTeacher('Wali Kelas 8.1', '222');

        // Wali kelas AKTIF untuk kelas 8.1 di tahun ajaran yang SAMA dengan SK ekskul.
        ClassHomeroom::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $waliKelas->id,
            'academic_period_id' => $this->currentPeriod->id,
            'is_current' => true,
        ]);

        $this->assertTrue($this->policy->update($waliKelas->user, $this->enrollment));
    }

    /** @test */
    public function non_homeroom_teacher_cannot_update_extracurricular_grade(): void
    {
        // Guru dengan profil, tapi bukan wali kelas mana pun.
        $guruBiasa = $this->makeTeacher('Guru Mapel', '333');

        $this->assertFalse($this->policy->update($guruBiasa->user, $this->enrollment));
    }

    /** @test */
    public function past_period_homeroom_teacher_cannot_update_current_extracurricular_grade(): void
    {
        $mantanWali = $this->makeTeacher('Mantan Wali 8.1', '444');

        // Pernah menjadi wali kelas 8.1, tetapi di TAHUN AJARAN LAMPAU & sudah tidak aktif.
        ClassHomeroom::create([
            'classroom_id' => $this->classroom->id,
            'teacher_id' => $mantanWali->id,
            'academic_period_id' => $this->pastPeriod->id,
            'is_current' => false,
        ]);

        // SK ekskul berada di periode aktif → mantan wali periode lampau harus DITOLAK.
        $this->assertFalse($this->policy->update($mantanWali->user, $this->enrollment));
    }

    /** @test */
    public function homeroom_teacher_of_different_classroom_cannot_update(): void
    {
        $otherClassroom = Classroom::create(['name' => 'Kelas 8.2', 'grade_level' => 8]);
        $waliLain = $this->makeTeacher('Wali Kelas 8.2', '555');

        // Wali kelas aktif, tetapi untuk KELAS BERBEDA dari SK ekskul.
        ClassHomeroom::create([
            'classroom_id' => $otherClassroom->id,
            'teacher_id' => $waliLain->id,
            'academic_period_id' => $this->currentPeriod->id,
            'is_current' => true,
        ]);

        $this->assertFalse($this->policy->update($waliLain->user, $this->enrollment));
    }
}
