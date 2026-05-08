<?php

namespace App\Services;

use App\Models\SchoolProfile;

class SchoolIdentityService
{
    public function profile(): ?SchoolProfile
    {
        return SchoolProfile::query()->first();
    }

    public function schoolName(): string
    {
        return $this->profile()?->name ?? 'SMPN 45 Sijunjung';
    }

    public function schoolAddress(): ?string
    {
        return $this->profile()?->address;
    }
}

