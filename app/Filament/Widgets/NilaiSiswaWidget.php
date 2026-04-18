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

    public static function canView(): bool
    {
        return false; // Dinonaktifkan sesuai permintaan pengguna
    }

    public ?string $filter = null;

    protected function getFilters(): ?array
    {
        $service   = app(NilaiVisualisasiService::class);
        $studentId = Auth::user()->student?->id;
        
        if (!$studentId) return [];

        $longitudinal = $service->getNilaiLongitudinal($studentId);
        $allSubjects = collect($longitudinal)
            ->flatMap(fn($grades) => array_keys($grades))
            ->unique()
            ->sort()
            ->values()
            ->toArray();
            
        return collect($allSubjects)->mapWithKeys(fn($s) => [$s => $s])->toArray();
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
        
        $selectedSubject = $this->filter;
        if (!$selectedSubject || !in_array($selectedSubject, $allSubjects)) {
            $selectedSubject = $allSubjects[0] ?? null;
        }
        
        $datasets = [];

        if ($selectedSubject) {
            $data = [];
            foreach ($periods as $period) {
                $data[] = $longitudinal[$period][$selectedSubject] ?? null;
            }

            $colorIdx = array_search($selectedSubject, $allSubjects);
            $colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316'];
            $color = $colors[$colorIdx % count($colors)];

            // Dataset 1: Tren Garis (Line)
            $datasets[] = [
                'type'             => 'line',
                'label'            => $selectedSubject . ' (Tren)',
                'data'             => $data,
                'borderColor'      => $color,
                'backgroundColor'  => $color,
                'fill'             => false,
                'tension'          => 0.4,
                'spanGaps'         => true,
                'pointRadius'      => 6,
                'pointHoverRadius' => 8,
                'borderWidth'      => 3,
            ];

            // Dataset 2: Batang (Bar)
            $datasets[] = [
                'type'             => 'bar',
                'label'            => $selectedSubject . ' (Nilai)',
                'data'             => $data,
                'borderColor'      => $color,
                'backgroundColor'  => $color . '40', // opacity
                'borderWidth'      => 1,
                'borderRadius'     => 4,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels'   => $periods,
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