<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <--- INI WAJIB ADA

class SchoolOrganizationStructure extends Model
{
    protected $guarded = [];

    // Relasi Balik ke Induk
    public function school_profile(): BelongsTo
    {
        return $this->belongsTo(SchoolProfile::class);
    }
}