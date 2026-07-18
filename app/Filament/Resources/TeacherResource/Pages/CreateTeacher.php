<?php

namespace App\Filament\Resources\TeacherResource\Pages;

use App\Filament\Resources\TeacherResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    /**
     * Generate akun login guru secara ATOMIK saat guru dibuat manual.
     *
     * Kontrak IDENTIK dengan TeacherImporter (paritas manual vs impor, Audit 2.2):
     * - Ada NIP  → username = NIP,   password = NIP
     * - Tanpa NIP (honorer) → wajib email; username = email, password = 'guru123'
     * Role login SELALU 'teacher' (tidak dibaca dari input — cegah eskalasi hak).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $nip   = trim($data['nip'] ?? '');
        $email = trim(strtolower($data['email'] ?? ''));

        // Sama seperti importer: tanpa NIP, email wajib untuk membuat akun login.
        if ($nip === '' && $email === '') {
            throw ValidationException::withMessages([
                'data.email' => 'Guru tanpa NIP (honorer) wajib mengisi Email untuk pembuatan akun login.',
            ]);
        }

        $username = $nip !== '' ? $nip : $email;
        $password = $nip !== '' ? $nip : 'guru123';

        return DB::transaction(function () use ($data, $nip, $email, $username, $password) {
            // ── STEP 1: AKUN USER (LOGIN) ─────────────────────────
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $data['name'],
                    'email' => $email !== '' ? $email : null,
                    'password' => Hash::make($password),
                    'role' => 'teacher',
                    'is_active' => true,
                ]
            );

            if (!$user->hasRole('teacher')) {
                $user->assignRole(
                    Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web'])
                );
            }

            // ── STEP 2: PROFIL GURU TERTAUT AKUN ──────────────────
            $data['user_id'] = $user->id;
            // Abaikan user_id dari form pada create (section disembunyikan).
            $data['email'] = $email !== '' ? $email : null;

            return static::getModel()::create($data);
        });
    }
}
