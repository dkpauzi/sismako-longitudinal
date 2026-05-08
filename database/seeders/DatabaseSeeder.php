<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Setup Dasar (Admin, Periode, Profil Sekolah, Mapel)
            SchoolSeeder::class,

            // 2. Import Siswa & Generate Kelas Otomatis
            //StudentSeeder::class,

            // 3. Import Guru
            //TeacherSeeder::class,

            // 4. Setting Wali Kelas (Menghubungkan Guru & Kelas)
            //HomeroomSeeder::class,

            // 5. Data TP & Jadwal
            //KbmSeeder::class,

            // 6. Data Dummy Nilai dan Absensi
            //DummyDataSeeder::class,

            // 7. Data Role permission
            //RolePermissionSeeder::class,

            // 8. Struktur Organisasi
            //SchoolOrganizationSeeder::class,
        ]);
    }
}