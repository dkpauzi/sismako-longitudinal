<?php

namespace App\Observers;

use App\Models\Teacher;

class TeacherObserver
{
    /**
     * Handle the Teacher "saved" event.
     */
    public function saved(Teacher $teacher): void
    {
        if ($teacher->user_id && ($teacher->wasChanged('name') || $teacher->wasChanged('email'))) {
            $user = $teacher->user;
            if ($user) {
                if ($teacher->wasChanged('name')) {
                    $user->name = $teacher->name;
                }
                if ($teacher->wasChanged('email')) {
                    $user->email = $teacher->email;
                }
                $user->saveQuietly(); // Prevent recursive observer triggers
            }
        }
    }
}
