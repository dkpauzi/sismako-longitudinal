<?php

namespace App\Policies;

use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\StudentSubjectEnrollment;
use Illuminate\Auth\Access\HandlesAuthorization;

class StudentSubjectEnrollmentPolicy
{
    use HandlesAuthorization;

    /**
     * Apakah user adalah Wali Kelas AKTIF dari KELAS SISWA PEMILIK nilai ekskul
     * ini, pada tahun ajaran SK ekskul tersebut?
     *
     * Konteks bisnis (Audit 3.5): pembina ekstrakurikuler umumnya pihak
     * eksternal tanpa akun sistem, sehingga input predikat & narasi ekskul
     * didelegasikan ke Admin dan Wali Kelas. Status "Wali Kelas" bukan role
     * Spatie, melainkan relasi ClassHomeroom — karena itu tidak bisa diperiksa
     * lewat permission dan harus dicek eksplisit di sini.
     *
     * PENTING (scoped per SISWA, bukan per SK): ekskul lintas-kelas (mis.
     * Pramuka) menaruh siswa dari banyak rombel pada SATU SK yang classroom-nya
     * hanya satu. Karena itu kepemilikan diturunkan dari ENROLLMENT siswa pada
     * periode SK (kelas aktual siswa), selaras KokurikulerGradePolicy & mandat
     * RBAC ("Wali Kelas dari kelas aktif siswa tersebut"). Wali kelas periode
     * lampau tetap ditolak karena is_current=true wajib.
     */
    private function isHomeroomTeacherFor(User $user, StudentSubjectEnrollment $enrollment): bool
    {
        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            return false;
        }

        $assignment = $enrollment->teachingAssignment;
        if ($assignment === null) {
            return false;
        }

        // Kelas AKTUAL siswa pada tahun ajaran SK ekskul (bukan classroom SK).
        $classroomId = Enrollment::where('student_id', $enrollment->student_id)
            ->where('academic_period_id', $assignment->academic_period_id)
            ->value('classroom_id');

        if ($classroomId === null) {
            return false;
        }

        return ClassHomeroom::where('teacher_id', $teacherId)
            ->where('classroom_id', $classroomId)
            ->where('academic_period_id', $assignment->academic_period_id)
            ->where('is_current', true)
            ->exists();
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_student::subject::enrollment');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StudentSubjectEnrollment $studentSubjectEnrollment): bool
    {
        return $user->can('view_student::subject::enrollment');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_student::subject::enrollment');
    }

    /**
     * Determine whether the user can update the model.
     *
     * Penilaian ekstrakurikuler (predicate & description) HANYA untuk:
     *   1. super_admin / admin — pemegang permission update (dipetakan seeder), atau
     *   2. Wali Kelas aktif dari kelas ybs pada periode SK tersebut.
     * Role teacher biasa sengaja TIDAK memiliki permission ini sejak Step 2
     * (Audit 3.5), sehingga guru non-wali otomatis tertolak di cabang pertama.
     */
    public function update(User $user, StudentSubjectEnrollment $studentSubjectEnrollment): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        if ($user->hasRole('teacher')) {
            return $this->isHomeroomTeacherFor($user, $studentSubjectEnrollment);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StudentSubjectEnrollment $studentSubjectEnrollment): bool
    {
        return $user->can('delete_student::subject::enrollment');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_student::subject::enrollment');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, StudentSubjectEnrollment $studentSubjectEnrollment): bool
    {
        return $user->can('force_delete_student::subject::enrollment');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_student::subject::enrollment');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, StudentSubjectEnrollment $studentSubjectEnrollment): bool
    {
        return $user->can('restore_student::subject::enrollment');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_student::subject::enrollment');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, StudentSubjectEnrollment $studentSubjectEnrollment): bool
    {
        return $user->can('replicate_student::subject::enrollment');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_student::subject::enrollment');
    }
}
