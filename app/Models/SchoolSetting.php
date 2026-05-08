<?php
// app/Models/SchoolSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'default_kkm' => 'integer',
        'show_score_sd' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (SchoolSetting $setting) {
            $profile = $setting->schoolProfile ?: SchoolProfile::query()->first();

            if (!$profile) {
                return;
            }

            // Auto-link ke profil sekolah agar read path konsisten.
            $setting->school_profile_id = $profile->id;
        });
    }

    /**
     * Sumber kebenaran identitas sekolah dipusatkan ke SchoolProfile.
     * school_settings tetap menyimpan konfigurasi sistem (KKTP, opsi rapor, dll).
     */
    public function schoolProfile(): BelongsTo
    {
        return $this->belongsTo(SchoolProfile::class);
    }
}