<?php

namespace Tests\Feature;

use App\Models\SchoolProfile;
use App\Models\SchoolSetting;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsistensi identitas sekolah pasca-konsolidasi:
 * - school_profiles = satu-satunya sumber identitas (nama, kepsek, alamat).
 * - school_settings = konfigurasi sistem, tertaut via school_profile_id
 *   (auto-link oleh model event saving).
 */
class SchoolIdentityConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_setting_auto_links_to_school_profile_on_save(): void
    {
        $profile = SchoolProfile::create([
            'name' => 'SMPN 45 Sijunjung',
            'principal_name' => 'Kepala Baru',
            'address' => 'Jl. Pendidikan',
        ]);

        // Tanpa mengisi school_profile_id — model event harus menautkan otomatis
        $setting = SchoolSetting::create([
            'default_kkm' => 80,
        ])->fresh();

        $this->assertSame($profile->id, $setting->school_profile_id);
        $this->assertSame(80, $setting->default_kkm);

        // Identitas dibaca dari relasi schoolProfile (bukan kolom lokal)
        $this->assertSame('SMPN 45 Sijunjung', $setting->schoolProfile->name);
        $this->assertSame('Kepala Baru', $setting->schoolProfile->principal_name);
        $this->assertSame('Jl. Pendidikan', $setting->schoolProfile->address);
    }

    public function test_school_settings_no_longer_stores_identity_columns(): void
    {
        // Kolom identitas legacy sudah dihapus — identitas hidup di school_profiles
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('school_settings');

        $this->assertNotContains('school_name', $columns);
        $this->assertNotContains('principal_name', $columns);
        $this->assertNotContains('address', $columns);
        $this->assertContains('school_profile_id', $columns);
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
