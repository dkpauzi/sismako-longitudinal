<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Subject;
use App\Models\AcademicPeriod;
use App\Models\SchoolProfile;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // 0. Setup Role
            $roleAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
            $roleGuru = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
            $roleSiswa = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

            // 1. Buat User Super Admin
            $admin = User::firstOrCreate(
                ['email' => 'admin@sekolah.com'],
                [
                    'name' => 'Super Admin',
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );
            $admin->assignRole($roleAdmin);

            // 2. Buat Tahun Ajaran (Disesuaikan dengan Struktur Baru)
            // 2025/2026 Ganjil
            AcademicPeriod::firstOrCreate(
                [
                    'start_year' => 2025,
                    'end_year' => 2026,
                    'semester' => 'odd'
                ],
                [
                    'start_date' => '2025-07-14',
                    'end_date' => '2025-12-19',
                    'is_active' => true, // Default aktif
                ]
            );

            // 2025/2026 Genap
            AcademicPeriod::firstOrCreate(
                [
                    'start_year' => 2025,
                    'end_year' => 2026,
                    'semester' => 'even'
                ],
                [
                    'start_date' => '2026-01-16',
                    'end_date' => '2026-06-19',
                    'is_active' => false,
                ]
            );

            // 2026/2027 Ganjil
            AcademicPeriod::firstOrCreate(
                [
                    'start_year' => 2026,
                    'end_year' => 2027,
                    'semester' => 'odd'
                ],
                [
                    'start_date' => '2026-07-14',
                    'end_date' => '2026-12-19',
                    'is_active' => false,
                ]
            );

            // 3. Buat Profil Sekolah
            SchoolProfile::updateOrCreate(
                ['id' => 1],
                [
                    'name' => 'SMPN 45 Sijunjung',
                    'npsn' => '1030xxxx',
                    'email' => 'info@smpn45sijunjung.sch.id',
                    'phone' => '021-12345678',
                    'address' => 'Pasar Jumat Muaro, Sijunjung, Sumatera Barat, Indonesia 27511',
                    'accreditation' => 'A',
                    'primary_color' => '#007bff',
                    // Logo default bisa dikosongkan dulu atau isi path dummy
                    // 'logo_path' => '01KG9QPV3Y9FR190MB838W83C2.png', 
                ]
            );

            // 4. Buat Mata Pelajaran
            $subjects = [
                ['name' => 'Matematika', 'code' => 'MTK', 'is_kokurikuler' => false],
                ['name' => 'Bahasa Indonesia', 'code' => 'BIND', 'is_kokurikuler' => false],
                ['name' => 'Bahasa Inggris', 'code' => 'BING', 'is_kokurikuler' => false],
                ['name' => 'IPA Terpadu', 'code' => 'IPA', 'is_kokurikuler' => false],
                ['name' => 'IPS Terpadu', 'code' => 'IPS', 'is_kokurikuler' => false],
                ['name' => 'Pendidikan Agama Islam dan Budi Pekerti', 'code' => 'PAI', 'is_kokurikuler' => false],
                ['name' => 'PJOK', 'code' => 'PJOK', 'is_kokurikuler' => false],
                ['name' => 'Informatika', 'code' => 'TIK', 'is_kokurikuler' => false],
                ['name' => 'Pendidikan Pancasila', 'code' => 'PKN', 'is_kokurikuler' => false],
                ['name' => 'Seni Budaya', 'code' => 'SENBUD', 'is_kokurikuler' => false],
                ['name' => 'Bimbingan Konseling', 'code' => 'BK', 'is_kokurikuler' => false],
                // --- MAPEL KOKURIKULER ---
                ['name' => 'Kokurikuler', 'code' => 'KOK', 'is_kokurikuler' => true],
            ];

            foreach ($subjects as $sub) {
                Subject::firstOrCreate(['code' => $sub['code']], $sub);
            }
        });
    }
}