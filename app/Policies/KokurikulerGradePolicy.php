<?php

namespace App\Policies;

use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use App\Models\KokurikulerGrade;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class KokurikulerGradePolicy
{
    use HandlesAuthorization;

    /**
     * Apakah user adalah Wali Kelas AKTIF dari kelas siswa pemilik nilai P5 ini,
     * pada tahun ajaran yang sama? (Audit MED-3)
     *
     * KokurikulerGrade tidak menyimpan classroom/teacher — kepemilikan diturunkan
     * dari enrollment siswa pada academic_period nilai tersebut, lalu dicocokkan
     * dengan ClassHomeroom is_current. Ini mencegah guru kelas lain mengubah/
     * menghapus narasi P5 siswa yang bukan asuhannya.
     */
    private function isHomeroomForGrade(User $user, KokurikulerGrade $grade): bool
    {
        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            return false;
        }

        $classroomId = Enrollment::where('student_id', $grade->student_id)
            ->where('academic_period_id', $grade->academic_period_id)
            ->value('classroom_id');

        if ($classroomId === null) {
            return false;
        }

        return ClassHomeroom::where('teacher_id', $teacherId)
            ->where('classroom_id', $classroomId)
            ->where('academic_period_id', $grade->academic_period_id)
            ->where('is_current', true)
            ->exists();
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_kokurikuler::grade');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, KokurikulerGrade $kokurikulerGrade): bool
    {
        return $user->can('view_kokurikuler::grade');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_kokurikuler::grade');
    }

    /**
     * Determine whether the user can update the model.
     *
     * super_admin/admin bebas; guru HANYA boleh mengubah nilai P5 siswa yang
     * kelasnya ia ampu sebagai Wali Kelas aktif (Audit MED-3) — mencegah
     * guru kelas lain mengutak-atik narasi P5 lintas kelas.
     */
    public function update(User $user, KokurikulerGrade $kokurikulerGrade): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        if ($user->hasRole('teacher')) {
            return $this->isHomeroomForGrade($user, $kokurikulerGrade);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Kepemilikan sama dengan update: hanya admin atau Wali Kelas aktif kelas
     * siswa terkait yang boleh menghapus nilai P5 (Audit MED-3).
     */
    public function delete(User $user, KokurikulerGrade $kokurikulerGrade): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        if ($user->hasRole('teacher')) {
            return $this->isHomeroomForGrade($user, $kokurikulerGrade);
        }

        return false;
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_kokurikuler::grade');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, KokurikulerGrade $kokurikulerGrade): bool
    {
        return $user->can('force_delete_kokurikuler::grade');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_kokurikuler::grade');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, KokurikulerGrade $kokurikulerGrade): bool
    {
        return $user->can('restore_kokurikuler::grade');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_kokurikuler::grade');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, KokurikulerGrade $kokurikulerGrade): bool
    {
        return $user->can('replicate_kokurikuler::grade');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_kokurikuler::grade');
    }
}
