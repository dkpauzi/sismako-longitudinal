<?php

namespace Tests\Unit;

use App\Models\SchoolProfile;
use App\Services\SchoolIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolIdentityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_profile_identity_when_available(): void
    {
        SchoolProfile::create([
            'name' => 'SMPN Testing',
            'address' => 'Jl. Test No. 1',
        ]);

        $service = app(SchoolIdentityService::class);

        $this->assertSame('SMPN Testing', $service->schoolName());
        $this->assertSame('Jl. Test No. 1', $service->schoolAddress());
    }

    public function test_it_returns_safe_defaults_when_profile_missing(): void
    {
        $service = app(SchoolIdentityService::class);

        $this->assertSame('SMPN 45 Sijunjung', $service->schoolName());
        $this->assertNull($service->schoolAddress());
    }
}

