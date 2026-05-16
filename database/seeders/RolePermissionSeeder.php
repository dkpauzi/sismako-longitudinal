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

        // ── BUAT ROLE JIKA BELUM ADA ────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $teacher    = Role::firstOrCreate(['name' => 'teacher',     'guard_name' => 'web']);
        $headmaster = Role::firstOrCreate(['name' => 'headmaster',  'guard_name' => 'web']);
        $student    = Role::firstOrCreate(['name' => 'student',     'guard_name' => 'web']);
        $guruBk     = Role::firstOrCreate(['name' => 'guru_bk',     'guard_name' => 'web']);

        // ── SUPER ADMIN: Dapat semua izin ────────────────────────
        // Super Admin tidak perlu didaftarkan permission satu per satu.
        // Filament Shield menangani ini via gate_intercept di config.
        // Cukup pastikan nama role-nya 'super_admin'.
        $this->command->info('✅ Super Admin: Mendapat semua izin secara otomatis.');

        // ── KEPALA SEKOLAH ────────────────────────────────────────
        // Bisa melihat semua data, tidak bisa mengubah pengaturan sistem.
        $headmasterPermissions = $this->resolvePermissions([
            // Akademik - READ ONLY untuk data master
            'view_any_academic::period', 'view_academic::period',
            'view_any_classroom',        'view_classroom',
            'view_any_subject',          'view_subject',
            'view_any_teacher',          'view_teacher',
            'view_any_student',          'view_student',

            // SK Mengajar - lihat semua kelas semua guru
            'view_any_teaching::assignment', 'view_teaching::assignment',

            // Jurnal & TP - bisa lihat semua
            'view_any_lesson::journal', 'view_lesson::journal',
            'view_any_learning::objective', 'view_learning::objective',

            // Rapor - bisa lihat semua & generate narasi
            'view_any_rapor', 'view_rapor',

            // Konten Website - bisa kelola semua
            'view_any_school::profile',   'view_school::profile',
            'update_school::profile',

            'view_any_school::activity',  'view_school::activity',
            'create_school::activity',    'update_school::activity',
            'delete_school::activity',

            'view_any_school::post',      'view_school::post',
            'create_school::post',        'update_school::post',
            'delete_school::post',

            'view_any_school::organization::structure',
            'view_school::organization::structure',

            // Hasil Asesmen BK - bisa melihat hasil evaluasi semua kelas
            'page_StudentBkResults',
        ]);
        $headmaster->syncPermissions($headmasterPermissions);
        $this->command->info('✅ Kepala Sekolah: ' . count($headmasterPermissions) . ' izin diberikan.');

        // ── ADMIN ─────────────────────────────────────────────────
        // Kelola semua data akademik & konten, tapi tidak bisa ubah Role/Permission.
        $adminPermissions = $this->resolvePermissions([
            // Manajemen Akun (bisa kelola user, tapi bukan role)
            'view_any_user',  'view_user',
            'create_user',    'update_user',
            'delete_user',

            // Tahun Ajaran - full CRUD
            'view_any_academic::period', 'view_academic::period',
            'create_academic::period',   'update_academic::period',
            'delete_academic::period',

            // Kelas, Mapel, Guru, Siswa - full CRUD
            'view_any_classroom',  'view_classroom',  'create_classroom',
            'update_classroom',    'delete_classroom',

            'view_any_subject',    'view_subject',    'create_subject',
            'update_subject',      'delete_subject',

            'view_any_teacher',    'view_teacher',    'create_teacher',
            'update_teacher',      'delete_teacher',  'delete_any_teacher',

            'view_any_student',    'view_student',    'create_student',
            'update_student',      'delete_student',  'delete_any_student',

            // SK Mengajar - full CRUD (admin yang atur jadwal)
            'view_any_teaching::assignment', 'view_teaching::assignment',
            'create_teaching::assignment',   'update_teaching::assignment',
            'delete_teaching::assignment',   'delete_any_teaching::assignment',

            // TP & Jurnal - lihat dan hapus jika perlu
            'view_any_learning::objective', 'view_learning::objective',
            'delete_learning::objective',

            'view_any_lesson::journal',     'view_lesson::journal',
            'delete_lesson::journal',

            // Mapel Pilihan Siswa SMA
            'view_any_student::subject::enrollment',
            'create_student::subject::enrollment',
            'update_student::subject::enrollment',
            'delete_student::subject::enrollment',

            // Rapor - full akses
            'view_any_rapor', 'view_rapor',

            // Konten Website - full CRUD
            'view_any_school::profile',    'view_school::profile',
            'create_school::profile',      'update_school::profile',

            'view_any_school::activity',   'view_school::activity',
            'create_school::activity',     'update_school::activity',
            'delete_school::activity',     'delete_any_school::activity',

            'view_any_school::post',       'view_school::post',
            'create_school::post',         'update_school::post',
            'delete_school::post',         'delete_any_school::post',

            'view_any_school::organization::structure',
            'view_school::organization::structure',
            'create_school::organization::structure',
            'update_school::organization::structure',
            'delete_school::organization::structure',

            // Pengaturan Sekolah
            'view_any_school::setting', 'view_school::setting',
            'create_school::setting',   'update_school::setting',

            // Template Narasi Rapor - full CRUD (Admin kelola template default)
            'view_any_narrative::template', 'view_narrative::template',
            'create_narrative::template',   'update_narrative::template',
            'delete_narrative::template',
        ]);
        $admin->syncPermissions($adminPermissions);
        $this->command->info('✅ Admin: ' . count($adminPermissions) . ' izin diberikan.');

        // ── GURU ──────────────────────────────────────────────────
        // Hanya bisa mengelola data yang berkaitan dengan tugasnya sendiri.
        // Filter "hanya kelas milik sendiri" ditangani di getEloquentQuery() Resource.
        $teacherPermissions = $this->resolvePermissions([
            // SK Mengajar - hanya bisa lihat & edit (tidak bisa buat/hapus)
            // Create & Delete dikelola Admin
            'view_any_teaching::assignment', 'view_teaching::assignment',
            'update_teaching::assignment',

            // Template Narasi Rapor - guru bisa lihat dan edit override kelasnya
            'view_any_narrative::template', 'view_narrative::template',
            'create_narrative::template',   'update_narrative::template',
            'delete_narrative::template',

            // Tujuan Pembelajaran (TP) - full CRUD untuk TPnya sendiri
            'view_any_learning::objective', 'view_learning::objective',
            'create_learning::objective',   'update_learning::objective',
            'delete_learning::objective',

            // Jurnal KBM - full CRUD
            'view_any_lesson::journal', 'view_lesson::journal',
            'create_lesson::journal',   'update_lesson::journal',
            'delete_lesson::journal',

            // Siswa - hanya bisa lihat (tidak bisa edit data pribadi siswa)
            'view_any_student', 'view_student',

            // Kelas - hanya bisa lihat
            'view_any_classroom', 'view_classroom',

            // Rapor - bisa lihat kelasnya sendiri
            'view_any_rapor', 'view_rapor',

            // Konten Website - bisa buat postingan/galeri (sebagai kontribusi)
            'view_any_school::post',    'view_school::post',
            'create_school::post',      'update_school::post',

            'view_any_school::activity', 'view_school::activity',
            'create_school::activity',   'update_school::activity',

            // Hasil Asesmen BK - bisa melihat hasil evaluasi siswa di kelasnya sendiri
            'page_StudentBkResults',
        ]);
        $teacher->syncPermissions($teacherPermissions);
        $this->command->info('✅ Guru: ' . count($teacherPermissions) . ' izin diberikan.');

        // ── GURU BK ───────────────────────────────────────────────
        // Mengelola rekam bimbingan, kuesioner BK, serta dapat melihat data siswa dan kelas.
        $guruBkPermissions = $this->resolvePermissions([
            // Rekam Bimbingan (CRUD untuk rekam yang dibuatnya sendiri, difilter di Resource)
            'view_any_bk::counseling::record', 'view_bk::counseling::record',
            'create_bk::counseling::record',   'update_bk::counseling::record',
            'delete_bk::counseling::record',

            // Kuesioner BK (CRUD untuk kuesioner yang dibuatnya sendiri, difilter di Resource)
            'view_any_bk::questionnaire', 'view_bk::questionnaire',
            'create_bk::questionnaire',   'update_bk::questionnaire',
            'delete_bk::questionnaire',

            // Siswa - hanya bisa lihat
            'view_any_student', 'view_student',

            // Kelas - hanya bisa lihat
            'view_any_classroom', 'view_classroom',
            
            // Guru - melihat data guru/wali kelas untuk koordinasi
            'view_any_teacher', 'view_teacher',

            // Rapor - bisa melihat rapor siswa untuk keperluan konseling
            'view_any_rapor', 'view_rapor',

            // Konten Website - bisa buat postingan/informasi (sebagai kontribusi)
            'view_any_school::post',    'view_school::post',
            'create_school::post',      'update_school::post',

            'view_any_school::activity', 'view_school::activity',
            'create_school::activity',   'update_school::activity',

            // Hasil Asesmen BK - bisa melihat hasil evaluasi semua kelas
            'page_StudentBkResults',
        ]);
        $guruBk->syncPermissions($guruBkPermissions);
        $this->command->info('✅ Guru BK: ' . count($guruBkPermissions) . ' izin diberikan.');

        // ── SISWA ─────────────────────────────────────────────────
        // Hanya bisa melihat datanya sendiri.
        // Filter "hanya data milik sendiri" ditangani di logika aplikasi.
        $studentPermissions = $this->resolvePermissions([
            // Hanya bisa lihat nilai & rapor diri sendiri
            // (difilter di DetailNilaiSiswa Page dan NilaiSiswaWidget)
            'view_any_rapor', 'view_rapor',
            'widget_StudentScheduleWidget',

            // Kuesioner BK — siswa bisa melihat & mengisi kuesioner yang ditargetkan ke kelasnya
            'page_MyQuestionnaires',
        ]);
        $student->syncPermissions($studentPermissions);
        $this->command->info('✅ Siswa: ' . count($studentPermissions) . ' izin diberikan.');

        // ── WALI SISWA ───────────────────────────────────────────
        // Saat ini mendapat izin yang SAMA dengan Siswa.
        // Dipisahkan agar nanti modul Konseling (Guru BK) bisa mengatur
        // visibilitas per role — misalnya: "Apakah wali bisa melihat
        // catatan konseling ini?" tanpa mempengaruhi akses siswa.
        $waliSiswa = Role::firstOrCreate(['name' => 'wali_siswa', 'guard_name' => 'web']);

        $waliPermissions = $this->resolvePermissions([
            // Bisa lihat nilai & rapor anak
            'view_any_rapor', 'view_rapor',
            'widget_StudentScheduleWidget',

            // Kuesioner BK — wali bisa melihat kuesioner & hasil evaluasi anak
            'page_MyQuestionnaires',
        ]);
        $waliSiswa->syncPermissions($waliPermissions);
        $this->command->info('✅ Wali Siswa: ' . count($waliPermissions) . ' izin diberikan.');

        $this->command->newLine();
        $this->command->info('🎉 Selesai! Semua role telah dikonfigurasi.');
        $this->command->info('   Jalankan: php artisan permission:cache-reset untuk membersihkan cache.');
    }

    /**
     * Helper: ambil Permission yang ada di database saja.
     * Menghindari error jika ada nama permission yang belum dibuat.
     */
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