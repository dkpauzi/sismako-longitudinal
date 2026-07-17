<?php

namespace Database\Seeders;

// ================================================================
// database/seeders/RolePermissionSeeder.php
//
// Seeder ini adalah SUMBER KEBENARAN TUNGGAL untuk Role & Permission.
// Jalankan dengan: php artisan db:seed --class=RolePermissionSeeder
//
// CATATAN PENTING:
// - Seeder ini TIDAK lagi bergantung pada `php artisan shield:generate --all`.
//   Seluruh permission dibuat manual di sini agar `migrate:fresh --seed`
//   langsung menghasilkan sistem yang siap pakai tanpa langkah CLI tambahan.
// - Nama permission mengikuti konvensi Filament Shield: {affix}_{resource},
//   contoh: view_any_rapor, update_kokurikuler::grade.
// - Nama resource HARUS sama persis dengan string yang dicek di app/Policies.
// - Seeder ini AKAN MENIMPA permission yang sudah melekat pada tiap role.
// ================================================================

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Awalan (affix) standar Filament Shield untuk setiap resource.
     * Digabung menjadi: {affix}_{resource}.
     */
    private const AFFIXES = [
        'view',
        'view_any',
        'create',
        'update',
        'restore',
        'restore_any',
        'replicate',
        'reorder',
        'delete',
        'delete_any',
        'force_delete',
        'force_delete_any',
    ];

    /**
     * Daftar resource yang memiliki Policy di app/Policies.
     * String di sini WAJIB identik dengan argumen $user->can() di Policy terkait,
     * jika tidak, permission tidak akan pernah cocok dan user tetap tertolak 403.
     */
    private const RESOURCES = [
        'academic::period',                // AcademicPeriodPolicy
        'bk::counseling::record',          // BkCounselingRecordPolicy
        'bk::questionnaire',               // BkQuestionnairePolicy
        'classroom',                       // ClassroomPolicy
        'kokurikuler::grade',              // KokurikulerGradePolicy
        'learning::objective',             // LearningObjectivePolicy
        'lesson::journal',                 // LessonJournalPolicy
        'narrative::template',             // NarrativeTemplatePolicy
        'rapor',                           // ClassHomeroomPolicy (RaporResource memakai ClassHomeroom sebagai anchor)
        'role',                            // RolePolicy
        'school::activity',                // SchoolActivityPolicy
        'school::organization::structure', // SchoolOrganizationStructurePolicy
        'school::post',                    // SchoolPostPolicy
        'school::profile',                 // SchoolProfilePolicy
        'school::setting',                 // SchoolSettingPolicy
        'student',                         // StudentPolicy
        'student::subject::enrollment',    // StudentSubjectEnrollmentPolicy
        'subject',                         // SubjectPolicy
        'teacher',                         // TeacherPolicy
        'teaching::assignment',            // TeachingAssignmentPolicy
        'user',                            // UserPolicy
    ];

    /**
     * Permission Halaman & Widget kustom.
     *
     * PERINGATAN: Saat ini permission ini BELUM ditegakkan oleh apa pun.
     * Halaman/widget terkait masih memakai canAccess()/shouldRegisterNavigation()
     * berbasis hasRole(). Permission ini dibuat agar peta hak akses lengkap dan
     * siap dipakai ketika trait HasPageShield/HasWidgetShield diterapkan nanti.
     */
    private const PAGE_AND_WIDGET_PERMISSIONS = [
        'page_StudentPromotionWizard',
        'page_StudentBkResults',
        'page_MyQuestionnaires',
        'page_MyGrades',
        'page_DetailNilaiSiswa',
        'widget_StudentScheduleWidget',
    ];

    public function run(): void
    {
        // Reset cache izin Spatie agar perubahan langsung berlaku
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── LANGKAH 1: BUAT SELURUH PERMISSION ──────────────────────────────
        $created = $this->createPermissions();
        $this->command->info("✅ Permission: {$created} izin tersedia di database.");

        // ── LANGKAH 2: BUAT ROLE JIKA BELUM ADA ─────────────────────────────
        // Slug di bawah ini adalah SATU-SATUNYA slug role yang sah di sistem.
        // Kode aplikasi tidak boleh mengecek slug di luar daftar ini.
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $teacher    = Role::firstOrCreate(['name' => 'teacher',     'guard_name' => 'web']);
        $headmaster = Role::firstOrCreate(['name' => 'headmaster',  'guard_name' => 'web']);
        $student    = Role::firstOrCreate(['name' => 'student',     'guard_name' => 'web']);
        $guardian   = Role::firstOrCreate(['name' => 'guardian',    'guard_name' => 'web']);
        $guruBk     = Role::firstOrCreate(['name' => 'guru_bk',     'guard_name' => 'web']);

        // ── SUPER ADMIN ─────────────────────────────────────────────────────
        // Config filament-shield mengaktifkan Gate intercept untuk super_admin,
        // namun izin tetap disematkan eksplisit agar tidak bergantung pada config.
        $superAdmin->syncPermissions(Permission::all());
        $this->command->info('✅ Super Admin: ' . Permission::count() . ' izin diberikan (seluruhnya).');

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
            'view_any_student::subject::enrollment', 'view_student::subject::enrollment',

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

            // Grafik Longitudinal Siswa
            'page_DetailNilaiSiswa',
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

            // Penilaian Ekstrakurikuler — Full CRUD.
            // Pembina ekskul umumnya pihak eksternal tanpa akun sistem, sehingga
            // input predikat & narasi ekskul didelegasikan ke Admin (lihat Audit 3.5).
            'view_any_student::subject::enrollment', 'view_student::subject::enrollment', 'create_student::subject::enrollment', 'update_student::subject::enrollment', 'delete_student::subject::enrollment',

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

            // Grafik Longitudinal Siswa
            'page_DetailNilaiSiswa',
        ]);
        $admin->syncPermissions($adminPermissions);
        $this->command->info('✅ Admin: ' . count($adminPermissions) . ' izin diberikan.');

        // ── GURU / TEACHER ──────────────────────────────────────────────────
        $teacherPermissions = $this->resolvePermissions([
            // SK Mengajar - Lihat Tugas & Update Beban Kelas
            'view_any_teaching::assignment', 'view_teaching::assignment', 'update_teaching::assignment',

            // Ekstrakurikuler — READ ONLY.
            // update_student::subject::enrollment SENGAJA TIDAK diberikan (Audit 3.5):
            // penilaian ekskul adalah kewenangan Admin & Wali Kelas, bukan seluruh guru.
            'view_any_student::subject::enrollment', 'view_student::subject::enrollment',

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

            // Grafik Longitudinal Siswa yang Diajar
            'page_DetailNilaiSiswa',
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
            'page_MyGrades',                          // Rekap Nilai & Kehadiran Pribadi
            'page_DetailNilaiSiswa',                  // Grafik Longitudinal Nilai Sendiri
        ]);
        $student->syncPermissions($studentPermissions);
        $this->command->info('✅ Siswa: ' . count($studentPermissions) . ' izin diberikan.');

        // ── WALI SISWA (GUARDIAN) ───────────────────────────────────────────
        $guardianPermissions = $this->resolvePermissions([
            'view_any_rapor', 'view_rapor',            // Memantau Grafik Perkembangan & Rapor Bayangan Anak
            'widget_StudentScheduleWidget',           // Meninjau Kehadiran & Jadwal Belajar Anak
            'page_MyQuestionnaires',                  // Meninjau Profil Gaya Belajar Hasil Angket Anak
            'page_DetailNilaiSiswa',                  // Grafik Longitudinal Nilai Anak
        ]);
        $guardian->syncPermissions($guardianPermissions);
        $this->command->info('✅ Wali Siswa: ' . count($guardianPermissions) . ' izin diberikan.');

        // Bersihkan cache sekali lagi agar izin baru langsung terbaca aplikasi
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->newLine();
        $this->command->info('🎉 Selesai! Semua role & permission telah dikonfigurasi secara sinkron.');
    }

    /**
     * Membuat seluruh permission yang dikenal sistem.
     *
     * Menggantikan `php artisan shield:generate --all` agar proses
     * `migrate:fresh --seed` berdiri sendiri tanpa langkah CLI manual.
     *
     * @return int Jumlah total permission yang tersedia setelah proses ini.
     */
    private function createPermissions(): int
    {
        // Permission per Resource: kombinasi {affix}_{resource}
        foreach (self::RESOURCES as $resource) {
            foreach (self::AFFIXES as $affix) {
                Permission::firstOrCreate([
                    'name' => "{$affix}_{$resource}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // Permission Halaman & Widget kustom
        foreach (self::PAGE_AND_WIDGET_PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        return Permission::count();
    }

    /**
     * Mengambil objek Permission berdasarkan nama.
     *
     * Berfungsi ganda sebagai pemeriksa integritas: jika ada nama yang salah ketik
     * atau resource baru yang belum didaftarkan di konstanta RESOURCES,
     * seeder akan memperingatkan alih-alih diam-diam memberi izin kosong.
     */
    private function resolvePermissions(array $names): \Illuminate\Support\Collection
    {
        $found  = Permission::whereIn('name', $names)->get();
        $missing = array_diff($names, $found->pluck('name')->toArray());

        if (!empty($missing)) {
            $this->command->warn('⚠️  Permission berikut TIDAK DIKENAL (kemungkinan salah ketik):');
            foreach ($missing as $name) {
                $this->command->warn("   - {$name}");
            }
            $this->command->warn('   → Periksa konstanta RESOURCES / PAGE_AND_WIDGET_PERMISSIONS di seeder ini.');
        }

        return $found;
    }
}
