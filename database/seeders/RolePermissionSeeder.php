<?php

namespace Database\Seeders;

// ================================================================
// database/seeders/RolePermissionSeeder.php
//
// Seeder ini mengatur REKOMENDASI HAK AKSES per Role.
// Jalankan dengan: php artisan db:seed --class=RolePermissionSeeder
//
// CATATAN:
// - Pastikan php artisan shield:generate --all sudah dijalankan
//   agar semua permission terdaftar di database.
// - Seeder ini AKAN MENIMPA permission yang sudah ada.
// ================================================================

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache izin Spatie agar perubahan langsung berlaku
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── BUAT ROLE JIKA BELUM ADA (Diseragamkan Menggunakan Slugs Standar) ──
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $teacher    = Role::firstOrCreate(['name' => 'teacher',     'guard_name' => 'web']);
        $headmaster = Role::firstOrCreate(['name' => 'headmaster',  'guard_name' => 'web']);
        $student    = Role::firstOrCreate(['name' => 'student',     'guard_name' => 'web']);
        $guardian   = Role::firstOrCreate(['name' => 'guardian',    'guard_name' => 'web']);
        $guruBk     = Role::firstOrCreate(['name' => 'guru_bk',     'guard_name' => 'web']);

        // ── SUPER ADMIN: Dapat semua izin secara otomatis via Gate Intercept ──
        $this->command->info('✅ Super Admin: Mendapat semua izin secara otomatis.');

        // ── KEPALA SEKOLAH (HEADMASTER) ────────────────────────────────────────
        $headmasterPermissions = $this->resolvePermissions([
            // Akademik - READ ONLY untuk pemantauan data master
            'view_any_academic::period', 'view_academic::period',
            'view_any_classroom',        'view_classroom',
            'view_any_subject',          'view_subject',
            'view_any_teacher',          'view_teacher',
            'view_any_student',          'view_student',

            // SK Mengajar & Manajemen Ekstrakurikuler (Read-only)
            'view_any_teaching::assignment', 'view_teaching::assignment',
            'view_any_student::subject::enrollment',

            // Jurnal, TP, & Penilaian Kokurikuler (P5)
            'view_any_lesson::journal', 'view_lesson::journal',
            'view_any_learning::objective', 'view_learning::objective',
            'view_any_kokurikuler::grade', 'view_kokurikuler::grade',

            // Rapor - Pemantauan Transkrip & Hasil Akhir
            'view_any_rapor', 'view_rapor',

            // Konten Website Resmi Sekolah
            'view_any_school::profile',   'view_school::profile', 'update_school::profile',
            'view_any_school::activity',  'view_school::activity', 'create_school::activity', 'update_school::activity', 'delete_school::activity',
            'view_any_school::post',      'view_school::post', 'create_school::post', 'update_school::post', 'delete_school::post',
            'view_any_school::organization::structure', 'view_school::organization::structure',

            // Hasil Evaluasi Psikologis / Asesmen VAK Guru BK
            'page_StudentBkResults',
        ]);
        $headmaster->syncPermissions($headmasterPermissions);
        $this->command->info('✅ Kepala Sekolah: ' . count($headmasterPermissions) . ' izin diberikan.');

        // ── ADMIN / TATA USAHA ─────────────────────────────────────────────────
        $adminPermissions = $this->resolvePermissions([
            // Manajemen Kredensial Pengguna (Siswa, Wali, Guru)
            'view_any_user',  'view_user', 'create_user', 'update_user', 'delete_user',

            // Tahun Ajaran Terstruktur - Full CRUD
            'view_any_academic::period', 'view_academic::period', 'create_academic::period', 'update_academic::period', 'delete_academic::period',

            // Data Master Entitas Pendidikan - Full CRUD
            'view_any_classroom',  'view_classroom',  'create_classroom',  'update_classroom',  'delete_classroom',
            'view_any_subject',    'view_subject',    'create_subject',    'update_subject',    'delete_subject',
            'view_any_teacher',    'view_teacher',    'create_teacher',    'update_teacher',    'delete_teacher', 'delete_any_teacher',
            'view_any_student',    'view_student',    'create_student',    'update_student',    'delete_student', 'delete_any_student',

            // SK Mengajar / Pembina Ekskul - Full CRUD
            'view_any_teaching::assignment', 'view_teaching::assignment', 'create_teaching::assignment', 'update_teaching::assignment', 'delete_teaching::assignment', 'delete_any_teaching::assignment',

            // Manajemen Pendaftaran Anggota Ekstrakurikuler Siswa
            'view_any_student::subject::enrollment', 'create_student::subject::enrollment', 'update_student::subject::enrollment', 'delete_student::subject::enrollment',

            // Penilaian Nilai Akhir Modul Kokurikuler (P5) - Full CRUD
            'view_any_kokurikuler::grade', 'view_kokurikuler::grade', 'create_kokurikuler::grade', 'update_kokurikuler::grade', 'delete_kokurikuler::grade',

            // Hak Akses Log Purge & Pengawasan Jurnal/TP
            'view_any_learning::objective', 'view_learning::objective', 'delete_learning::objective',
            'view_any_lesson::journal',     'view_lesson::journal',     'delete_lesson::journal',

            // Output Layer Rapor Cetak
            'view_any_rapor', 'view_rapor',

            // Konten Website & Pengaturan Parameter Aplikasi
            'view_any_school::profile',    'view_school::profile', 'create_school::profile', 'update_school::profile',
            'view_any_school::activity',   'view_school::activity', 'create_school::activity', 'update_school::activity', 'delete_school::activity', 'delete_any_school::activity',
            'view_any_school::post',       'view_school::post', 'create_school::post', 'update_school::post', 'delete_school::post', 'delete_any_school::post',
            'view_any_school::organization::structure', 'view_school::organization::structure', 'create_school::organization::structure', 'update_school::organization::structure', 'delete_school::organization::structure',
            'view_any_school::setting',    'view_school::setting', 'create_school::setting', 'update_school::setting',
            'view_any_narrative::template', 'view_narrative::template', 'create_narrative::template', 'update_narrative::template', 'delete_narrative::template',

            // Izin Halaman Kenaikan Kelas Kritis (Promotion Wizard Page)
            'page_StudentPromotionWizard',
        ]);
        $admin->syncPermissions($adminPermissions);
        $this->command->info('✅ Admin: ' . count($adminPermissions) . ' izin diberikan.');

        // ── GURU / TEACHER ──────────────────────────────────────────────────
        $teacherPermissions = $this->resolvePermissions([
            // SK Mengajar - Lihat Tugas & Update Beban Kelas
            'view_any_teaching::assignment', 'view_teaching::assignment', 'update_teaching::assignment',

            // Akses Mengisi Predikat & Deskripsi Narasi Evaluasi Ekstrakurikuler Siswa
            'view_any_student::subject::enrollment', 'update_student::subject::enrollment',

            // Akses Mengisi Nilai Akhir Narasi Projek Kokurikuler (P5)
            'view_any_kokurikuler::grade', 'view_kokurikuler::grade', 'create_kokurikuler::grade', 'update_kokurikuler::grade', 'delete_kokurikuler::grade',

            // Kamus Narasi Otomatis Rapor (Rule Engine Overrides)
            'view_any_narrative::template', 'view_narrative::template', 'create_narrative::template', 'update_narrative::template', 'delete_narrative::template',

            // Pembuatan Kompetensi Dasar / Tujuan Pembelajaran (TP)
            'view_any_learning::objective', 'view_learning::objective', 'create_learning::objective', 'update_learning::objective', 'delete_learning::objective',

            // Pencatatan Harian Jurnal KBM
            'view_any_lesson::journal', 'view_lesson::journal', 'create_lesson::journal', 'update_lesson::journal', 'delete_lesson::journal',

            // Read-Only Profil Siswa & Wadah Kelas
            'view_any_student', 'view_student',
            'view_any_classroom', 'view_classroom',

            // Output Layer Rapor Kelas Sendiri
            'view_any_rapor', 'view_rapor',

            // Publikasi Konten Informasi Sekolah
            'view_any_school::post', 'view_school::post', 'create_school::post', 'update_school::post',
            'view_any_school::activity', 'view_school::activity', 'create_school::activity', 'update_school::activity',

            // Pemantauan Gaya Belajar Siswa dari Hasil BK
            'page_StudentBkResults',
        ]);
        $teacher->syncPermissions($teacherPermissions);
        $this->command->info('✅ Guru: ' . count($teacherPermissions) . ' izin diberikan.');

        // ── GURU BK ───────────────────────────────────────────────
        $guruBkPermissions = $this->resolvePermissions([
            // Rekam Kasus Bimbingan Konseling - Full CRUD
            'view_any_bk::counseling::record', 'view_bk::counseling::record', 'create_bk::counseling::record', 'update_bk::counseling::record', 'delete_bk::counseling::record',

            // Manajemen Instrumen Tes Psikologi VAK & Kontrol Tiket Akses
            'view_any_bk::questionnaire', 'view_bk::questionnaire', 'create_bk::questionnaire', 'update_bk::questionnaire', 'delete_bk::questionnaire',

            // Hak Akses Peninjauan Lintas Kelas & Guru untuk Koordinasi Kasus
            'view_any_student', 'view_student',
            'view_any_classroom', 'view_classroom',
            'view_any_teacher', 'view_teacher',
            'view_any_rapor', 'view_rapor',

            // Publikasi Buletin Bimbingan Remaja di Website
            'view_any_school::post', 'view_school::post', 'create_school::post', 'update_school::post',
            'view_any_school::activity', 'view_school::activity', 'create_school::activity', 'update_school::activity',

            // Halaman Hasil Distribusi Gaya Belajar VAK Siswa Secara Global
            'page_StudentBkResults',
        ]);
        $guruBk->syncPermissions($guruBkPermissions);
        $this->command->info('✅ Guru BK: ' . count($guruBkPermissions) . ' izin diberikan.');

        // ── SISWA (STUDENT) ─────────────────────────────────────────────────
        $studentPermissions = $this->resolvePermissions([
            'view_any_rapor', 'view_rapor',            // Akses Transkrip Pemantauan Nilai Mandiri
            'widget_StudentScheduleWidget',           // Peninjauan Jadwal Pelajaran Aktif
            'page_MyQuestionnaires',                  // Mengisi Angket VAK Saat Tiket Dibuka Guru BK
        ]);
        $student->syncPermissions($studentPermissions);
        $this->command->info('✅ Siswa: ' . count($studentPermissions) . ' izin diberikan.');

        // ── WALI SISWA (GUARDIAN) ───────────────────────────────────────────
        $guardianPermissions = $this->resolvePermissions([
            'view_any_rapor', 'view_rapor',            // Memantau Grafik Perkembangan & Rapor Bayangan Anak
            'widget_StudentScheduleWidget',           // Meninjau Kehadiran & Jadwal Belajar Anak
            'page_MyQuestionnaires',                  // Meninjau Profil Gaya Belajar Hasil Angket Anak
        ]);
        $guardian->syncPermissions($guardianPermissions);
        $this->command->info('✅ Wali Siswa: ' . count($guardianPermissions) . ' izin diberikan.');

        $this->command->newLine();
        $this->command->info('🎉 Selesai! Semua role telah dikonfigurasi secara sinkron.');
        $this->command->info('   Jalankan: php artisan permission:cache-reset untuk membersihkan cache.');
    }

    private function resolvePermissions(array $names): \Illuminate\Support\Collection
    {
        $found  = Permission::whereIn('name', $names)->get();
        $missing = array_diff($names, $found->pluck('name')->toArray());

        if (!empty($missing)) {
            $this->command->warn('⚠️  Permission berikut belum ada di database (skip):');
            foreach ($missing as $name) {
                $this->command->warn("   - {$name}");
            }
            $this->command->warn('   → Pastikan sudah menjalankan: php artisan shield:generate --all');
        }

        return $found;
    }
}