<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // <--- INI WAJIB ADA

class SchoolProfile extends Model
{
    protected $guarded = [];

    // Relasi 1: Misi
    public function missions(): HasMany
    {
        return $this->hasMany(SchoolMission::class);
    }

    // Relasi 2: Fasilitas
    public function facilities(): HasMany
    {
        return $this->hasMany(SchoolFacility::class);
    }

    // Relasi 3: Struktur Organisasi
    public function organization_structures(): HasMany
    {
        return $this->hasMany(SchoolOrganizationStructure::class);
    }

    // Relasi 4: Kegiatan
    public function activities(): HasMany
    {
        return $this->hasMany(SchoolActivity::class);
    }

    public function school_missions()
    {
        return $this->hasMany(SchoolMission::class);
    }
}