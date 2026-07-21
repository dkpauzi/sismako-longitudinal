<?php

namespace Tests\Feature;

use App\Http\Controllers\RaporPrintController;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\ClassHomeroom;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * IDOR guard untuk RaporPrintController (rute /rapor/print/{homeroom}/{student}).
 * Rute hanya di balik `auth`; tanpa gerbang ini, user mana pun bisa mencetak
 * rapor siswa lain. canAccess() harus mengizinkan pemilik sah & menolak sisanya.
 */
class RaporPrintAccessTest extends TestCase
{
    use RefreshDatabase;

    private AcademicPeriod $period;
    private Classroom $classroom;
    private ClassHomeroom $homeroom;
    private Student $student;
    private Teacher $waliTeacher;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'headmaster', 'guru_bk', 'teacher', 'student', 'guardian'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        $this->period = AcademicPeriod::create(['start_year' => 2023, 'end_year' => 2024, 'semester' => 'odd', 'start_date' => '2023-07-01', 'end_date' => '2023-12-31', 'is_active' => true]);
        $this->classroom = Classroom::create(['name' => '7', 'grade_level' => 7]);

        $wUser = User::factory()->create(['role' => 'teacher']);
        $wUser->assignRole('teacher');
        $this->waliTeacher = Teacher::create(['user_id' => $wUser->id, 'name' => 'Wali', 'nip' => '1', 'is_active' => true]);
        $this->homeroom = ClassHomeroom::create(['classroom_id' => $this->classroom->id, 'teacher_id' => $this->waliTeacher->id, 'academic_period_id' => $this->period->id, 'is_current' => true]);

        $sUser = User::factory()->create(['role' => 'student']);
        $sUser->assignRole('student');
        $gUser = User::factory()->create(['role' => 'guardian']);
        $gUser->assignRole('guardian');
        $this->student = Student::create(['user_id' => $sUser->id, 'guardian_user_id' => $gUser->id, 'name' => 'Ananda', 'nisn' => '3000000001', 'gender' => 'L', 'status' => 'active']);
    }

    private function userWith(string $role): User
    {
        $u = User::factory()->create(['role' => $role]);
        $u->assignRole($role);
        return $u->fresh();
    }

    public function test_staff_can_access(): void
    {
        foreach (['super_admin', 'admin', 'headmaster', 'guru_bk'] as $role) {
            $this->assertTrue(
                RaporPrintController::canAccess($this->userWith($role), $this->homeroom, $this->student),
                "{$role} seharusnya boleh."
            );
        }
    }

    public function test_homeroom_teacher_can_access(): void
    {
        $this->assertTrue(RaporPrintController::canAccess($this->waliTeacher->user->fresh(), $this->homeroom, $this->student));
    }

    public function test_owning_student_and_guardian_can_access(): void
    {
        $this->assertTrue(RaporPrintController::canAccess($this->student->user->fresh(), $this->homeroom, $this->student));

        $guardian = User::find($this->student->guardian_user_id)->fresh();
        $this->assertTrue(RaporPrintController::canAccess($guardian, $this->homeroom, $this->student));
    }

    public function test_foreign_student_guardian_and_nonwali_teacher_are_denied(): void
    {
        // Siswa lain (IDOR utama) — ditolak.
        $otherSUser = User::factory()->create(['role' => 'student']);
        $otherSUser->assignRole('student');
        Student::create(['user_id' => $otherSUser->id, 'name' => 'Lain', 'nisn' => '3000000002', 'gender' => 'L', 'status' => 'active']);
        $this->assertFalse(RaporPrintController::canAccess($otherSUser->fresh(), $this->homeroom, $this->student));

        // Wali orang lain — ditolak.
        $this->assertFalse(RaporPrintController::canAccess($this->userWith('guardian'), $this->homeroom, $this->student));

        // Guru non-wali kelas ini — ditolak.
        $otherTUser = User::factory()->create(['role' => 'teacher']);
        $otherTUser->assignRole('teacher');
        Teacher::create(['user_id' => $otherTUser->id, 'name' => 'Guru Lain', 'nip' => '2', 'is_active' => true]);
        $this->assertFalse(RaporPrintController::canAccess($otherTUser->fresh(), $this->homeroom, $this->student));
    }

    public function test_guest_is_denied(): void
    {
        $this->assertFalse(RaporPrintController::canAccess(null, $this->homeroom, $this->student));
    }
}
