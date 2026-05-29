<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VakDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Distribusi Gaya Belajar Siswa (VAK)';
    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 2; // Position it near other widgets

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(['headmaster', 'guru_bk', 'super_admin']) ?? false;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        // Execute the aggregated database query
        $results = DB::table('bk_student_responses')
            ->select('dominant_style', DB::raw('COUNT(*) as total_students'))
            ->where('status', 'completed')
            ->whereNotNull('dominant_style')
            ->groupBy('dominant_style')
            ->get();

        // Initialize predefined VAK categories with 0 to ensure strict payload compliance
        $vakData = [
            'VISUAL' => 0,
            'AUDITORI' => 0,
            'KINESTETIK' => 0,
        ];

        // Map the flat SQL results to the structured array
        foreach ($results as $row) {
            $style = strtoupper($row->dominant_style);
            if (array_key_exists($style, $vakData)) {
                $vakData[$style] = (int) $row->total_students;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Siswa',
                    'data' => [
                        $vakData['VISUAL'],
                        $vakData['AUDITORI'],
                        $vakData['KINESTETIK'],
                    ],
                    'backgroundColor' => [
                        '#3b82f6', // VISUAL
                        '#eab308', // AUDITORI
                        '#10b981', // KINESTETIK
                    ],
                ]
            ],
            'labels' => ['VISUAL', 'AUDITORI', 'KINESTETIK'],
        ];
    }
}
