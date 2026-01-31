<?php

namespace App\Filament\Widgets;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Classroom;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1; // <--- Tambahkan ini (Urutan 1)
    // Agar widget tidak terlalu sering refresh (beban server), kita set polling agak lama atau matikan (null)
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Siswa', Student::where('status', 'active')->count())
                ->description('Siswa Aktif')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]), // Hiasan grafik mini

            Stat::make('Total Guru', Teacher::where('is_active', true)->count())
                ->description('Pengajar Aktif')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->chart([3, 5, 3, 5, 6, 7, 3, 3]),

            Stat::make('Total Kelas', Classroom::count())
                ->description('Rombongan Belajar')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('warning'),
        ];
    }
}