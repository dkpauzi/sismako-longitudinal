<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Models\SchoolProfile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use Illuminate\Support\Facades\Blade;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // --- MULAI KUSTOMISASI BRANDING ---
            ->brandName(fn() => $this->getSchoolName())
            ->brandLogo(fn() => $this->getBrandHtml()) // Kita panggil fungsi baru
            ->brandLogoHeight(fn() => request()->routeIs('filament.admin.auth.login') ? '7rem' : '3rem')
            ->favicon(fn() => $this->getSchoolFavicon())
            // ----------------------------------
            ->colors([
                'primary' => \Filament\Support\Colors\Color::Blue, // Bisa diganti sesuai warna sekolah

            ])
            ->plugins([
                FilamentFullCalendarPlugin::make()
                    ->selectable() // Agar bisa klik tanggal untuk tambah event
                    ->editable(),  // Agar bisa geser/edit event
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                //Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                'panels::head.end',
                fn(): string => Blade::render(<<<'HTML'
                <style>
                    /* Warnai Background Sabtu & Minggu agak kemerahan */
                    .fc-day-sat, .fc-day-sun {
                        background-color: #fef2f2 !important; /* Merah muda lembut */
                    }
                    /* Warnai Angka Tanggalnya Merah */
                    .fc-day-sat .fc-daygrid-day-number, 
                    .fc-day-sun .fc-daygrid-day-number {
                        color: #dc2626 !important; /* Merah terang */
                    }
                </style>
            HTML)
            );
    }
    // --- FUNGSI BANTUAN

    private function getSchoolName(): string
    {
        // Cek dulu apakah tabel ada (untuk mencegah error saat migrasi awal)
        if (Schema::hasTable('school_profiles')) {
            $profile = SchoolProfile::first();
            return $profile?->name ?? 'Sistem Sekolah';
        }
        return 'Sistem Sekolah';
    }

    private function getSchoolLogo(): ?string
    {
        if (Schema::hasTable('school_profiles')) {
            $profile = SchoolProfile::first();
            if ($profile && $profile->logo_path) {
                return Storage::url($profile->logo_path);
            }
        }
        return null; // Jika null, Filament hanya menampilkan Nama (Teks)
    }

    private function getSchoolFavicon(): ?string
    {
        if (Schema::hasTable('school_profiles')) {
            $profile = SchoolProfile::first();
            if ($profile && $profile->logo_path) {
                return Storage::url($profile->logo_path); // Pakai logo sebagai favicon juga
            }
        }
        return null;
    }
    private function getBrandHtml(): HtmlString
    {
        $name = 'Sistem Sekolah';
        $logoUrl = null;

        // Cek Database
        if (Schema::hasTable('school_profiles')) {
            $profile = SchoolProfile::first();
            if ($profile) {
                $name = $profile->name;
                if ($profile->logo_path) {
                    $logoUrl = Storage::url($profile->logo_path);
                }
            }
        }

        // Cek apakah sedang di Halaman Login?
        $isLogin = request()->routeIs('filament.admin.auth.login');

        // --- TAMPILAN KHUSUS LOGIN (VERTIKAL / ATAS-BAWAH) ---
        if ($isLogin) {
            if ($logoUrl) {
                return new HtmlString("
                    <div class='flex flex-col items-center justify-center gap-y-2 mt-2'>
                        <img src='{$logoUrl}' alt='Logo' class='h-16 object-contain' />
                        <span class='font-bold text-xl text-center leading-tight text-gray-950 dark:text-white'>
                            {$name}
                        </span>
                    </div>
                ");
            }
            return new HtmlString("<div class='font-bold text-xl text-center'>{$name}</div>");
        }

        // --- TAMPILAN DASHBOARD / SIDEBAR (HORIZONTAL / SAMPING) ---
        // Ini agar header dashboard kembali rapi
        if ($logoUrl) {
            return new HtmlString("
                <div class='flex items-center gap-x-3'>
                    <img src='{$logoUrl}' alt='Logo' class='h-9 object-contain' />
                    <span class='font-bold text-lg hidden md:block text-gray-950 dark:text-white'>
                        {$name}
                    </span>
                </div>
            ");
        }

        return new HtmlString("<div class='font-bold text-lg'>{$name}</div>");
    }
}
