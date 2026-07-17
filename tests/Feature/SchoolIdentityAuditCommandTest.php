<?php

namespace Tests\Feature;

use App\Models\SchoolProfile;
use App\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test perintah siakad:audit-school-identity.
 *
 * Pasca-konsolidasi identitas, school_settings tidak lagi menyimpan kolom
 * identitas (school_name, dst) — identitas hidup di school_profiles.
 * Audit memverifikasi tautan school_settings.school_profile_id.
 */
class SchoolIdentityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: buat setting lalu putuskan tautannya secara paksa
     * (bypass model event yang auto-link saat saving) untuk
     * mensimulasikan data legacy/rusak.
     */
    private function createUnlinkedSetting(): SchoolSetting
    {
        $setting = SchoolSetting::create([
            'default_kkm' => 75,
        ]);

        DB::table('school_settings')
            ->where('id', $setting->id)
            ->update(['school_profile_id' => null]);

        return $setting->refresh();
    }

    public function test_audit_command_reports_mismatch_without_fix(): void
    {
        SchoolProfile::create([
            'name' => 'SMPN Profil',
            'principal_name' => 'Kepsek Profil',
            'address' => 'Alamat Profil',
        ]);

        $this->createUnlinkedSetting();

        $this->artisan('siakad:audit-school-identity')
            ->expectsOutputToContain('Ditemukan mismatch tautan')
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

        $setting = $this->createUnlinkedSetting();
        $this->assertNull($setting->school_profile_id);

        $this->artisan('siakad:audit-school-identity --fix')
            ->expectsOutputToContain('Sinkronisasi selesai')
            ->assertExitCode(0);

        $setting->refresh();

        $this->assertSame($profile->id, $setting->school_profile_id);
    }

    public function test_audit_command_passes_when_already_linked(): void
    {
        SchoolProfile::create([
            'name' => 'SMPN Konsisten',
            'principal_name' => 'Kepsek Konsisten',
            'address' => 'Alamat Konsisten',
        ]);

        // Model event auto-link saat saving
        SchoolSetting::create([
            'default_kkm' => 75,
        ]);

        $this->artisan('siakad:audit-school-identity')
            ->expectsOutputToContain('sudah tertaut')
            ->assertExitCode(0);
    }
}
