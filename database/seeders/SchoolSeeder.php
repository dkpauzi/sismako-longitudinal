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
use App\Models\SchoolSetting;

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
            // 2023/2024 Ganjil
            AcademicPeriod::firstOrCreate(

                [
                    'start_year' => 2023,
                    'end_year' => 2024,
                    'semester' => 'odd'
                ],
                [
                    'start_date' => '2023-07-17',
                    'end_date' => '2023-12-22',
                    'is_active' => true, // Default aktif
                ]
            );

            // 2024/2025 Genap
            AcademicPeriod::firstOrCreate(
                [
                    'start_year' => 2023,
                    'end_year' => 2024,
                    'semester' => 'even'
                ],
                [
                    'start_date' => '2024-01-08',
                    'end_date' => '2024-06-28',
                    'is_active' => false,
                ]
            );

            // 2024/2025 Ganjil
            AcademicPeriod::firstOrCreate(

                [
                    'start_year' => 2024,
                    'end_year' => 2025,
                    'semester' => 'odd'
                ],
                [
                    'start_date' => '2024-07-15',
                    'end_date' => '2024-12-20',
                    'is_active' => true, // Default aktif
                ]
            );

            // 2024/2025 Genap
            AcademicPeriod::firstOrCreate(
                [
                    'start_year' => 2024,
                    'end_year' => 2025,
                    'semester' => 'even'
                ],
                [
                    'start_date' => '2025-01-06',
                    'end_date' => '2025-06-26',
                    'is_active' => false,
                ]
            );
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
            $profile = SchoolProfile::updateOrCreate(
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

            // 3b. Pastikan konfigurasi sistem sekolah tersedia dan terhubung
            // ke source of truth identitas (SchoolProfile).
            SchoolSetting::updateOrCreate(
                ['id' => 1],
                [
                    'school_profile_id' => $profile->id,
                    'default_kkm' => 75,
                    'show_score_sd' => true,
                ]
            );

            // 4. Buat Mata Pelajaran
            $subjects = [
                ['name' => 'Matematika', 'code' => 'MTK', 'type' => 'mandatory'],
                ['name' => 'Bahasa Indonesia', 'code' => 'BIND', 'type' => 'mandatory'],
                ['name' => 'Bahasa Inggris', 'code' => 'BING', 'type' => 'mandatory'],
                ['name' => 'IPA Terpadu', 'code' => 'IPA', 'type' => 'mandatory'],
                ['name' => 'IPS Terpadu', 'code' => 'IPS', 'type' => 'mandatory'],
                ['name' => 'Pendidikan Agama Islam dan Budi Pekerti', 'code' => 'PAI', 'type' => 'mandatory'],
                ['name' => 'PJOK', 'code' => 'PJOK', 'type' => 'mandatory'],
                ['name' => 'Informatika', 'code' => 'TIK', 'type' => 'mandatory'],
                ['name' => 'Pendidikan Pancasila', 'code' => 'PKN', 'type' => 'mandatory'],
                ['name' => 'Seni Budaya', 'code' => 'SENBUD', 'type' => 'mandatory'],
                ['name' => 'Bimbingan Konseling', 'code' => 'BK', 'type' => 'mandatory'],
                // --- MAPEL KOKURIKULER ---
                ['name' => 'Kokurikuler', 'code' => 'KOK', 'type' => 'kokurikuler'],
            ];

            foreach ($subjects as $sub) {
                Subject::firstOrCreate(['code' => $sub['code']], $sub);
            }
        });
    }
}