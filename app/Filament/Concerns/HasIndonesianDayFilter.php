<?php

namespace App\Filament\Concerns;

use Filament\Tables;

/**
 * Trait HasIndonesianDayFilter
 *
 * Menyediakan filter hari (Senin–Sabtu) dalam Bahasa Indonesia
 * untuk widget jadwal Filament. Default otomatis ke hari ini.
 *
 * Digunakan oleh: StudentScheduleWidget, TeacherScheduleWidget
 */
trait HasIndonesianDayFilter
{
    /**
     * Buat instance SelectFilter untuk filter hari dalam Bahasa Indonesia.
     * Default value diatur ke hari ini (misal: 'Kamis' jika hari Kamis).
     */
    protected function getDayFilter(): Tables\Filters\SelectFilter
    {
        return Tables\Filters\SelectFilter::make('day')
            ->label('Hari')
            ->options([
                'Senin'   => 'Senin',
                'Selasa'  => 'Selasa',
                'Rabu'    => 'Rabu',
                'Kamis'   => 'Kamis',
                'Jumat'   => 'Jumat',
                'Sabtu'   => 'Sabtu',
            ])
            ->default($this->getTodayIndonesian())
            ->placeholder('Semua Hari');
    }

    /**
     * Mengembalikan nama hari ini dalam Bahasa Indonesia.
     * Menggunakan PHP match expression untuk kejelasan.
     */
    private function getTodayIndonesian(): ?string
    {
        return match (now()->format('l')) {
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            'Sunday'    => 'Minggu',
            default     => null,
        };
    }
}
