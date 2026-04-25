<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use App\Models\Attendance;
use App\Models\Grade;
use App\Models\SchoolProfile;
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

        // View Composer: share SchoolProfile ke semua Blade view publik.
        // ✅ PERBAIKAN: Gunakan Cache::remember() alih-alih static $cached.
        // Alasan: static variable di closure akan stale di Octane/queue worker
        // karena persist lintas request. Cache::remember bisa di-invalidate secara eksplisit
        // saat admin mengupdate SchoolProfile (lihat EditSchoolProfile::afterSave).
        View::composer(
            ['layouts.app', 'partials.navbar', 'dashboard.*'],
            function (\Illuminate\View\View $view) {
                if (!Schema::hasTable('school_profiles')) {
                    $view->with('schoolProfile', null);
                    return;
                }

                $profile = Cache::remember(
                    'school_profile_global',
                    now()->addMinutes(30),
                    fn() => SchoolProfile::first()
                );

                $view->with('schoolProfile', $profile);
            }
        );
    }
}