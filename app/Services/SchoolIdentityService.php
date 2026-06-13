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

    /**
     * Mengembalikan array identitas sekolah untuk keperluan rapor.
     * Kunci array ini selaras dengan variabel yang diharapkan oleh blade view rapor.
     */
    public function getIdentity(): array
    {
        $profile = $this->profile();

        return [
            'name'            => $profile?->name,
            'address'         => $profile?->address,
            'headmaster_name' => $profile?->principal_name,
        ];
    }
}
