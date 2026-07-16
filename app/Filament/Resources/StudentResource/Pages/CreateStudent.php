<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    /**
     * Generate akun siswa + akun wali secara ATOMIK saat siswa dibuat manual.
     *
     * Kontrak IDENTIK dengan StudentImporter (aturan 1 siswa = 1 akun wali):
     * - Akun siswa : username = NISN,        password = NISN,        role 'student'
     * - Akun wali  : username = WALI_{NISN}, password = WALI_{NISN}, role 'guardian'
     *
     * updateOrCreate (match by username) menjaga idempotensi: jika profil siswa
     * pernah dihapus tetapi akun user-nya tertinggal, create ulang tidak crash —
     * akun lama dipakai kembali dan ditautkan.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $nisn = trim($data['nisn'] ?? '');

        // Guard lapis kedua (form sudah required): username digenerate dari NISN.
        if ($nisn === '') {
            throw ValidationException::withMessages([
                'data.nisn' => 'NISN wajib diisi — akun siswa & wali digenerate dari NISN.',
            ]);
        }

        return DB::transaction(function () use ($data, $nisn) {
            // ── STEP 1: BUAT/UPDATE AKUN USER SISWA ───────────────
            $studentUser = User::updateOrCreate(
                ['username' => $nisn],
                [
                    'name' => $data['name'],
                    'email' => null,
                    'password' => Hash::make($nisn),
                    'role' => 'student',
                    'is_active' => true,
                ]
            );

            if (!$studentUser->hasRole('student')) {
                $studentUser->assignRole(
                    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
                );
            }

            // ── STEP 2: BUAT/UPDATE AKUN USER WALI (khusus & terpisah) ──
            $guardianUsername = 'WALI_' . $nisn;
            $guardianUser = User::updateOrCreate(
                ['username' => $guardianUsername],
                [
                    'name' => 'Orang Tua/Wali dari ' . $data['name'],
                    'email' => null,
                    'password' => Hash::make($guardianUsername),
                    'role' => 'guardian',
                    'is_active' => true,
                ]
            );

            if (!$guardianUser->hasRole('guardian')) {
                $guardianUser->assignRole(
                    Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web'])
                );
            }

            // ── STEP 3: PROFIL SISWA TERTAUT KEDUA AKUN ───────────
            $data['user_id'] = $studentUser->id;
            $data['guardian_user_id'] = $guardianUser->id;

            return static::getModel()::create($data);
        });
    }
}
