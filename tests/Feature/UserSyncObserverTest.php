<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSyncObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_update_syncs_to_user()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'role' => 'teacher',
        ]);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'nip' => '123456789',
            'gender' => 'L',
        ]);

        // Update the teacher
        $teacher->update([
            'name' => 'New Teacher Name',
            'email' => 'new.teacher@example.com',
        ]);

        // Check if user was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Teacher Name',
            'email' => 'new.teacher@example.com',
        ]);
    }

    public function test_student_update_syncs_to_user()
    {
        $user = User::factory()->create([
            'name' => 'Old Student Name',
            'email' => 'student.old@example.com',
            'role' => 'student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'name' => 'Old Student Name',
            'nisn' => '1234567890',
            'gender' => 'P',
        ]);

        // Update the student
        $student->update([
            'name' => 'New Student Name',
        ]);

        // Check if user was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Student Name',
        ]);
    }
}
