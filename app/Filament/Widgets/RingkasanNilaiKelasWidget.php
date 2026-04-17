<?php
// app/Filament/Widgets/RingkasanNilaiKelasWidget.php

namespace App\Filament\Widgets;

use App\Models\AcademicPeriod;
use App\Models\ClassHomeroom;
use App\Services\NilaiVisualisasiService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class RingkasanNilaiKelasWidget extends ChartWidget
{
    protected static ?string $heading = 'Rata-rata Nilai Kelas';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(['teacher', 'headmaster', 'super_admin']) ?? false;
    }

    protected function getData(): array
    {
        $user         = Auth::user();
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        $service      = app(NilaiVisualisasiService::class);

        if (!$activePeriod) {
            return ['datasets' => [], 'labels' => []];
        }

        // Tentukan classroom berdasarkan role
        if ($user->hasAnyRole(['super_admin', 'headmaster'])) {
            // Ambil semua kelas aktif — tampilkan dropdown pilih kelas
            // Default: kelas pertama
            $homeroom = ClassHomeroom::where('academic_period_id', $activePeriod->id)
                ->with('classroom')
                ->first();
        } else {
            // Wali kelas → hanya kelasnya sendiri
            $homeroom = ClassHomeroom::where('teacher_id', $user->teacher?->id)
                ->where('academic_period_id', $activePeriod->id)
                ->with('classroom')
                ->first();
        }

        if (!$homeroom) {
            return ['datasets' => [], 'labels' => []];
        }

        $data = $service->getRingkasanNilaiKelas($homeroom->classroom_id);

        if (empty($data)) {
            return ['datasets' => [], 'labels' => []];
        }

        $labels     = array_column($data, 'subject');
        $rataRata   = array_column($data, 'rata_rata');
        $tertinggi  = array_column($data, 'tertinggi');
        $terendah   = array_column($data, 'terendah');

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => 'Rata-rata',
                    'data'            => $rataRata,
                    'backgroundColor' => '#3B82F680',
                    'borderColor'     => '#3B82F6',
                    'borderWidth'     => 2,
                    'borderRadius'    => 4,
                ],
                [
                    'label'           => 'Tertinggi',
                    'data'            => $tertinggi,
                    'backgroundColor' => '#10B98140',
                    'borderColor'     => '#10B981',
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                ],
                [
                    'label'           => 'Terendah',
                    'data'            => $terendah,
                    'backgroundColor' => '#EF444440',
                    'borderColor'     => '#EF4444',
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'top'],
            ],
            'scales' => [
                'y' => [
                    'min'   => 0,
                    'max'   => 100,
                    'ticks' => ['stepSize' => 10],
                    'grid'  => ['color' => '#E5E7EB'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}