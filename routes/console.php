<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\SchoolProfile;
use App\Models\SchoolSetting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/**
 * Audit konsistensi identitas sekolah.
 *
 * Pasca-konsolidasi identitas: school_profiles adalah SATU-SATUNYA sumber
 * identitas (nama, alamat, kepsek, dll). school_settings hanya menyimpan
 * konfigurasi sistem dan WAJIB tertaut ke profil via school_profile_id.
 * Audit ini memverifikasi (dan dengan --fix, memperbaiki) tautan tersebut.
 */
Artisan::command('siakad:audit-school-identity {--fix : Tautkan ulang school_settings ke school_profiles}', function () {
    $profileCount = SchoolProfile::query()->count();
    $settingCount = SchoolSetting::query()->count();

    $this->info("SchoolProfile records: {$profileCount}");
    $this->info("SchoolSetting records: {$settingCount}");

    if ($profileCount === 0) {
        $this->error('Tidak ada data SchoolProfile. Audit tidak dapat dilanjutkan.');
        return 1;
    }

    if ($profileCount > 1) {
        $this->warn('Ditemukan lebih dari 1 SchoolProfile. Disarankan jadikan singleton.');
    }

    $profile = SchoolProfile::query()->first();
    $setting = SchoolSetting::query()->first();

    if (!$setting) {
        $this->warn('SchoolSetting belum ada.');

        if (!$this->option('fix')) {
            return 1;
        }

        SchoolSetting::query()->create([
            'school_profile_id' => $profile->id,
            'default_kkm' => 75,
            'show_score_sd' => true,
        ]);

        $this->info('SchoolSetting berhasil dibuat dan ditautkan ke SchoolProfile.');
        return 0;
    }

    if ((int) $setting->school_profile_id === (int) $profile->id) {
        $this->info('Audit selesai: school_settings sudah tertaut ke school_profiles.');
        return 0;
    }

    $this->warn(sprintf(
        'Ditemukan mismatch tautan: school_profile_id current="%s" expected="%s"',
        $setting->school_profile_id ?? 'null',
        $profile->id
    ));

    if (!$this->option('fix')) {
        $this->error('Jalankan ulang dengan --fix untuk menautkan ulang otomatis.');
        return 1;
    }

    $setting->update(['school_profile_id' => $profile->id]);

    $this->info('Sinkronisasi selesai. school_settings tertaut ke school_profiles.');
    return 0;
})->purpose('Audit konsistensi identitas sekolah');
