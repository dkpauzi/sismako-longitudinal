<?php

// ============================================================
// lang/id/filament-shield.php
// Translasi Filament Shield ke Bahasa Indonesia
// Letakkan file ini di: lang/id/filament-shield.php
// ============================================================

return [

    // ── HALAMAN DAFTAR ROLE ──────────────────────────────────
    'nav.group'          => 'Manajemen Sistem',
    'nav.role.label'     => 'Manajemen Role & Hak Akses',
    'nav.role.icon'      => 'heroicon-o-shield-check',

    'resource.label.role'        => 'Role',
    'resource.label.roles'       => 'Daftar Role',

    // ── KOLOM TABEL ──────────────────────────────────────────
    'column.name'                => 'Nama Role',
    'column.guard_name'          => 'Guard',
    'column.permissions_count'   => 'Jumlah Izin',
    'column.users_count'         => 'Jumlah Pengguna',
    'column.created_at'          => 'Dibuat Pada',
    'column.updated_at'          => 'Diperbarui Pada',

    // ── FORM ─────────────────────────────────────────────────
    'field.name'                 => 'Nama Role',
    'field.guard_name'           => 'Guard Name',
    'field.permissions'          => 'Hak Akses (Permissions)',
    'field.select_all.name'      => 'Pilih Semua',
    'field.select_all.message'   => 'Aktifkan semua hak akses yang tersedia saat ini dan di masa mendatang',

    // ── SECTION DALAM FORM ───────────────────────────────────
    'section'                    => 'Hak Akses',
    'section.all'                => 'Izin Global',

    // ── TOGGLE LABEL ─────────────────────────────────────────
    'toggle.title'               => 'Centang / Hapus Semua',

    // ── BUTTON ───────────────────────────────────────────────
    'button.save'                => 'Simpan Role',

    // ── PESAN NOTIFIKASI ─────────────────────────────────────
    'forbidden'                  => 'Anda tidak memiliki izin untuk melakukan tindakan ini.',

    // ── LABEL IZIN (per entity) ───────────────────────────────
    // Format: [nama_entitas] => [label tampil]
    // Shield akan menggunakan format: "view_any_<entity>", "create_<entity>", dst.
    // Label di bawah ini muncul sebagai header grup izin.

    'models' => [
        // Sistem & Pengguna
        'user'                               => 'Manajemen Akun Pengguna',
        'role'                               => 'Manajemen Role',

        // Profil & Pengaturan Sekolah
        'school::profile'                    => 'Profil Sekolah (Website)',
        'school::setting'                    => 'Pengaturan Umum Sekolah',

        // Konten Website
        'school::activity'                   => 'Galeri Kegiatan',
        'school::post'                       => 'Postingan & Pengumuman',
        'school::organization::structure'    => 'Struktur Organisasi',

        // Akademik - Master Data
        'academic::period'                   => 'Tahun Ajaran',
        'classroom'                          => 'Ruang Kelas (Rombel)',
        'subject'                            => 'Mata Pelajaran',
        'teacher'                            => 'Data Guru',
        'student'                            => 'Data Siswa',

        // Akademik - Kegiatan Belajar
        'teaching::assignment'               => 'SK Mengajar (Kelas Ajar)',
        'lesson::journal'                    => 'Jurnal KBM',
        'learning::objective'                => 'Tujuan Pembelajaran (TP)',
        'student::subject::enrollment'       => 'Mapel Pilihan Siswa (SMA)',

        // Penilaian & Rapor
        'rapor'                              => 'Rekap & Cetak Rapor',
    ],

    // ── LABEL TIPE IZIN ──────────────────────────────────────
    // Ini adalah label untuk setiap jenis aksi
    'permissions' => [
        'view_any'     => 'Lihat Daftar',
        'view'         => 'Lihat Detail',
        'create'       => 'Tambah Baru',
        'update'       => 'Edit / Perbarui',
        'delete'       => 'Hapus',
        'delete_any'   => 'Hapus Massal',
        'force_delete' => 'Hapus Permanen',
        'force_delete_any' => 'Hapus Permanen Massal',
        'restore'      => 'Pulihkan (Restore)',
        'restore_any'  => 'Pulihkan Massal',
        'replicate'    => 'Duplikat',
        'reorder'      => 'Ubah Urutan',
    ],
];