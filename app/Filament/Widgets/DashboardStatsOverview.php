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

    public static function canView(): bool
    {
        return auth()->check() && !auth()->user()?->hasRole('student');
    }

    protected function getStats(): array
    {
        // ✅ PERBAIKAN: 3 COUNT query digabung menjadi 1 query.
        $counts = \Illuminate\Support\Facades\DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM students WHERE status = 'active') as students,
                (SELECT COUNT(*) FROM teachers WHERE is_active = 1) as teachers,
                (SELECT COUNT(*) FROM classrooms) as classrooms
        ");

        return [
            Stat::make('Total Siswa', $counts->students)
                ->description('Siswa Aktif')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]), // Hiasan grafik mini

            Stat::make('Total Guru', $counts->teachers)
                ->description('Pengajar Aktif')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->chart([3, 5, 3, 5, 6, 7, 3, 3]),

            Stat::make('Total Kelas', $counts->classrooms)
                ->description('Rombongan Belajar')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('warning'),
        ];
    }
}