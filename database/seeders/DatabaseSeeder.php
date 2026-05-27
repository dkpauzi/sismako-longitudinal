<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. PRA-KONDISI: Spatie Permission / Filament Shield dijalankan manual di CLI
        // $this->command->info('⚙️ Pastikan Anda sudah menjalankan: php artisan shield:generate --all');

        // 2. EKSEKUSI SEEDER
        $this->call([
            // PONDASI SISTEM: Memetakan permission yang baru digenerate ke masing-masing Role
            RolePermissionSeeder::class,

            // DATA MASTER DASAR: Akun Admin, Periode, Profil Sekolah, Mapel
            SchoolSeeder::class,

            // INSTRUMEN BK: Akun Guru BK & Kuesioner VAK
            VakQuestionnaireSeeder::class,

            // =========================================================
            // Seeder simulasi longitudinal di bawah ini dinonaktifkan
            // =========================================================
            // StudentSeeder::class,
            // TeacherSeeder::class,
            // HomeroomSeeder::class,
            // KbmSeeder::class,
            // DummyDataSeeder::class,
            // SchoolOrganizationSeeder::class,
        ]);
    }
}