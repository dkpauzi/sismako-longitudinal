<?php

namespace Tests\Feature;

use App\Models\SchoolProfile;
use App\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolIdentityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_command_reports_mismatch_without_fix(): void
    {
        $profile = SchoolProfile::create([
            'name' => 'SMPN Profil',
            'principal_name' => 'Kepsek Profil',
            'address' => 'Alamat Profil',
        ]);

        SchoolSetting::create([
            'school_profile_id' => null,
            'school_name' => 'Nama Lama',
            'principal_name' => 'Kepsek Lama',
            'address' => 'Alamat Lama',
            'default_kkm' => 75,
            'show_score_sd' => true,
        ]);

        $this->artisan('siakad:audit-school-identity')
            ->expectsOutputToContain('Ditemukan mismatch identitas')
            ->expectsOutputToContain('Jalankan ulang dengan --fix')
            ->assertExitCode(1);
    }

    public function test_audit_command_can_fix_mismatch_data(): void
    {
        $profile = SchoolProfile::create([
            'name' => 'SMPN Sinkron',
            'principal_name' => 'Kepsek Sinkron',
            'address' => 'Alamat Sinkron',
        ]);

        $setting = SchoolSetting::create([
            'school_name' => 'Nama Tidak Sinkron',
            'principal_name' => 'Kepsek Tidak Sinkron',
            'address' => 'Alamat Tidak Sinkron',
            'default_kkm' => 80,
            'show_score_sd' => true,
        ]);

        $this->artisan('siakad:audit-school-identity --fix')
            ->expectsOutputToContain('Sinkronisasi selesai')
            ->assertExitCode(0);

        $setting->refresh();

        $this->assertSame($profile->id, $setting->school_profile_id);
        $this->assertSame('SMPN Sinkron', $setting->getRawOriginal('school_name'));
        $this->assertSame('Kepsek Sinkron', $setting->getRawOriginal('principal_name'));
        $this->assertSame('Alamat Sinkron', $setting->getRawOriginal('address'));
    }
}

