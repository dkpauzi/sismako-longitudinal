<?php

namespace App\Policies;

use App\Models\BkQuestionnaire;
use App\Models\User;

class BkQuestionnairePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'headmaster', 'guru_bk']);
    }

    public function view(User $user, BkQuestionnaire $bkQuestionnaire): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'headmaster'])) return true;
        
        if ($user->hasRole('guru_bk')) {
            return $bkQuestionnaire->counselor_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'guru_bk']);
    }

    public function update(User $user, BkQuestionnaire $bkQuestionnaire): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) return true;
        
        if ($user->hasRole('guru_bk')) {
            return $bkQuestionnaire->counselor_id === $user->id;
        }

        return false;
    }

    public function delete(User $user, BkQuestionnaire $bkQuestionnaire): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) return true;
        
        if ($user->hasRole('guru_bk')) {
            return $bkQuestionnaire->counselor_id === $user->id;
        }

        return false;
    }
}
