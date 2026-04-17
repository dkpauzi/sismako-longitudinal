<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema; // <--- Baris ini penting
use App\Models\Attendance;
use App\Models\Grade;
use App\Observers\AttendanceObserver;
use App\Observers\GradeObserver;
use App\Services\DescriptionGeneratorService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DescriptionGeneratorService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        // Registrasi Observer
        Attendance::observe(AttendanceObserver::class);
        // Observer: update final_grades setiap nilai berubah
        Grade::observe(GradeObserver::class);
    }
}