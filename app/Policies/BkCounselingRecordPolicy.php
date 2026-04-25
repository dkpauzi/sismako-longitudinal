<?php

namespace App\Policies;

use App\Models\BkCounselingRecord;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BkCounselingRecordPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'headmaster', 'guru_bk']);
    }

    /**
     * Determine whether the user can view the model.
     * This method is also used natively by Filament's FileUpload to authorize downloading private files!
     */
    public function view(User $user, BkCounselingRecord $bkCounselingRecord): bool
    {
        // 1. Admin & Headmaster can view everything
        if ($user->hasAnyRole(['super_admin', 'admin', 'headmaster'])) {
            return true;
        }

        // 2. Guru BK can only view records they created
        if ($user->hasRole('guru_bk')) {
            return $bkCounselingRecord->counselor_id === $user->id;
        }

        // 3. Student can view if toggle is ON and they are the student
        if ($user->hasRole('student')) {
            return $bkCounselingRecord->is_visible_to_student && 
                   $bkCounselingRecord->student->user_id === $user->id;
        }

        // 4. Guardian can view if toggle is ON and they are the guardian of the student
        if ($user->hasRole('guardian')) {
            return $bkCounselingRecord->is_visible_to_guardian && 
                   $bkCounselingRecord->student->guardian_user_id === $user->id;
        }

        // 5. Homeroom teacher can view if toggle is ON and they are the active homeroom teacher
        // (Assuming teacher relation and checking if they are the homeroom teacher for the student's active classroom)
        if ($user->hasRole('teacher') && $bkCounselingRecord->is_visible_to_homeroom) {
            $student = $bkCounselingRecord->student;
            $activePeriod = \App\Models\AcademicPeriod::where('is_active', true)->first();
            
            if ($activePeriod) {
                $enrollment = $student->enrollments()->where('academic_period_id', $activePeriod->id)->first();
                if ($enrollment) {
                    $homeroom = \App\Models\ClassHomeroom::where('classroom_id', $enrollment->classroom_id)
                        ->where('academic_period_id', $activePeriod->id)
                        ->where('teacher_id', $user->teacher->id ?? 0)
                        ->first();
                    
                    if ($homeroom) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'guru_bk']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BkCounselingRecord $bkCounselingRecord): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) return true;
        
        if ($user->hasRole('guru_bk')) {
            return $bkCounselingRecord->counselor_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BkCounselingRecord $bkCounselingRecord): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) return true;
        
        if ($user->hasRole('guru_bk')) {
            return $bkCounselingRecord->counselor_id === $user->id;
        }

        return false;
    }
}
