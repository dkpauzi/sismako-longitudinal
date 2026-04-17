<?php
// app/Filament/Widgets/NilaiSiswaWidget.php

namespace App\Filament\Widgets;

use App\Services\NilaiVisualisasiService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class NilaiSiswaWidget extends ChartWidget
{
    protected static ?string $heading = 'Grafik Nilai Saya';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    // Hanya muncul untuk role student
    public static function canView(): bool
    {
        return Auth::user()?->hasRole('student') ?? false;
    }

    protected function getData(): array
    {
        $service   = app(NilaiVisualisasiService::class);
        $studentId = Auth::user()->student?->id;

        if (!$studentId) {
            return ['datasets' => [], 'labels' => []];
        }

        $longitudinal = $service->getNilaiLongitudinal($studentId);

        // Kumpulkan semua nama mapel unik
        $allSubjects = collect($longitudinal)
            ->flatMap(fn($grades) => array_keys($grades))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Kumpulkan semua label periode
        $periods = array_keys($longitudinal);

        // Bangun dataset per mapel
        $colors = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B',
            '#8B5CF6', '#EC4899', '#14B8A6', '#F97316',
            '#6366F1', '#84CC16',
        ];

        $datasets = [];
        foreach ($allSubjects as $idx => $subject) {
            $data = [];
            foreach ($periods as $period) {
                $data[] = $longitudinal[$period][$subject] ?? null;
            }

            $color = $colors[$idx % count($colors)];

            $datasets[] = [
                'label'                => $subject,
                'data'                 => $data,
                'borderColor'          => $color,
                'backgroundColor'      => $color . '20',
                'fill'                 => false,
                'tension'              => 0.3,
                'spanGaps'             => true,
                'pointRadius'          => 5,
                'pointHoverRadius'     => 7,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels'   => $periods,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend'  => ['position' => 'bottom'],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
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
            'interaction' => [
                'mode'         => 'nearest',
                'axis'         => 'x',
                'intersect'    => false,
            ],
        ];
    }
}