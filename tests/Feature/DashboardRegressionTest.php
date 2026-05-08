<?php

namespace Tests\Feature;

use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_still_loads_after_identity_consolidation(): void
    {
        $this->seed(SchoolSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('SMPN 45 Sijunjung');
    }
}

