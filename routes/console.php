<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\SchoolProfile;
use App\Models\SchoolSetting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('siakad:audit-school-identity {--fix : Sinkronkan school_settings dari school_profiles}', function () {
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
            'school_name' => $profile->name,
            'npsn' => $profile->npsn,
            'address' => $profile->address,
            'phone' => $profile->phone,
            'email' => $profile->email,
            'website' => $profile->website,
            'principal_name' => $profile->principal_name,
            'default_kkm' => 75,
            'show_score_sd' => true,
        ]);

        $this->info('SchoolSetting berhasil dibuat dari SchoolProfile.');
        return 0;
    }

    $checks = [
        'school_profile_id' => [$setting->school_profile_id, $profile->id],
        'school_name' => [$setting->getRawOriginal('school_name'), $profile->name],
        'npsn' => [$setting->getRawOriginal('npsn'), $profile->npsn],
        'address' => [$setting->getRawOriginal('address'), $profile->address],
        'phone' => [$setting->getRawOriginal('phone'), $profile->phone],
        'email' => [$setting->getRawOriginal('email'), $profile->email],
        'website' => [$setting->getRawOriginal('website'), $profile->website],
        'principal_name' => [$setting->getRawOriginal('principal_name'), $profile->principal_name],
    ];

    $mismatches = [];
    foreach ($checks as $field => [$current, $expected]) {
        if (($current ?? '') !== ($expected ?? '')) {
            $mismatches[$field] = ['current' => $current, 'expected' => $expected];
        }
    }

    if (empty($mismatches)) {
        $this->info('Audit selesai: tidak ada mismatch identitas sekolah.');
        return 0;
    }

    $this->warn('Ditemukan mismatch identitas:');
    foreach ($mismatches as $field => $values) {
        $this->line("- {$field}: current=\"{$values['current']}\" expected=\"{$values['expected']}\"");
    }

    if (!$this->option('fix')) {
        $this->error('Jalankan ulang dengan --fix untuk sinkronisasi otomatis.');
        return 1;
    }

    $setting->update([
        'school_profile_id' => $profile->id,
        'school_name' => $profile->name,
        'npsn' => $profile->npsn,
        'address' => $profile->address,
        'phone' => $profile->phone,
        'email' => $profile->email,
        'website' => $profile->website,
        'principal_name' => $profile->principal_name,
    ]);

    $this->info('Sinkronisasi selesai. Data identitas sudah konsisten.');
    return 0;
})->purpose('Audit konsistensi identitas sekolah');
