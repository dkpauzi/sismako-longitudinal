<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Aset Vite tidak di-build di lingkungan test (public/build/manifest.json
        // tidak ada), sehingga @vite pada layout akan melempar ViewException.
        // Nonaktifkan Vite untuk seluruh test HTTP.
        $this->withoutVite();
    }
}
