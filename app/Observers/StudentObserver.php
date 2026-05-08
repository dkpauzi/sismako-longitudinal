<?php

namespace App\Observers;

use App\Models\Student;

class StudentObserver
{
    /**
     * Handle the Student "saved" event.
     */
    public function saved(Student $student): void
    {
        if ($student->user_id && $student->wasChanged('name')) {
            $user = $student->user;
            if ($user) {
                $user->name = $student->name;
                $user->saveQuietly();
            }
        }
    }
}
