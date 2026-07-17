<?php

namespace App\Policies;

use App\Models\ClassHomeroom;
use App\Models\User;
use App\Models\StudentSubjectEnrollment;
use Illuminate\Auth\Access\HandlesAuthorization;

class StudentSubjectEnrollmentPolicy
{
    use HandlesAuthorization;

    /**
     * Apakah user adalah Wali Kelas AKTIF untuk kelas tempat
     * teaching assignment (ekskul) ini berjalan?
     *
     * Konteks bisnis (Audit 3.5): pembina ekstrakurikuler umumnya pihak
     * eksternal tanpa akun sistem, sehingga input predikat & narasi ekskul
     * didelegasikan ke Admin dan Wali Kelas. Status "Wali Kelas" bukan role
     * Spatie, melainkan relasi ClassHomeroom — karena itu tidak bisa
     * diperiksa lewat permission dan harus dicek eksplisit di sini.
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

        // Wali Kelas yang menjabat SAAT INI (is_current) pada kelas ybs,
        // dan pada tahun ajaran yang sama dengan SK ekskul tersebut —
        // wali kelas baru tidak boleh mengubah nilai ekskul periode lampau
        // (menjaga integritas data longitudinal).
        return ClassHomeroom::where('teacher_id', $teacherId)
            ->where('classroom_id', $assignment->classroom_id)
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
