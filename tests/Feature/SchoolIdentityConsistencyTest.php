<?php

namespace Tests\Feature;

use App\Models\SchoolProfile;
use App\Models\SchoolSetting;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolIdentityConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_setting_reads_identity_from_school_profile(): void
    {
        $profile = SchoolProfile::create([
            'name' => 'SMPN 45 Sijunjung',
            'principal_name' => 'Kepala Baru',
            'address' => 'Jl. Pendidikan',
        ]);

        $setting = SchoolSetting::create([
            'school_profile_id' => $profile->id,
            'school_name' => 'Nama Lama',
            'principal_name' => 'Kepala Lama',
            'default_kkm' => 80,
            'show_score_sd' => true,
        ])->fresh();

        $this->assertSame('SMPN 45 Sijunjung', $setting->school_name);
        $this->assertSame('Kepala Baru', $setting->principal_name);
        $this->assertSame('Jl. Pendidikan', $setting->address);
        $this->assertSame(80, $setting->default_kkm);
    }

    public function test_school_setting_fallbacks_to_legacy_columns_when_profile_not_linked(): void
    {
        $setting = SchoolSetting::create([
            'school_name' => 'SMP Legacy',
            'principal_name' => 'Kepala Legacy',
            'address' => 'Alamat Legacy',
            'default_kkm' => 75,
            'show_score_sd' => true,
        ])->fresh();

        $this->assertSame('SMP Legacy', $setting->school_name);
        $this->assertSame('Kepala Legacy', $setting->principal_name);
        $this->assertSame('Alamat Legacy', $setting->address);
    }

    public function test_school_seeder_creates_linked_school_setting_record(): void
    {
        $this->seed(SchoolSeeder::class);

        $setting = SchoolSetting::query()->first();

        $this->assertNotNull($setting);
        $this->assertNotNull($setting->school_profile_id);
        $this->assertSame(75, $setting->default_kkm);
    }
}

