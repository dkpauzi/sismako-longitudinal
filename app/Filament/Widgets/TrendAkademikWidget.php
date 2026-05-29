<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TrendAkademikWidget extends ChartWidget
{
    protected static ?string $heading = 'Longitudinal Academic Trend (Average Score)';
    protected static ?string $description = 'Tracking school-wide average scores across academic periods based on classroom levels.';
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(['headmaster', 'super_admin']) ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'all' => 'All Subjects',
            'mandatory' => 'Mata Pelajaran Wajib (Nasional)',
            'elective' => 'Muatan Lokal / Pilihan',
            'kokurikuler' => 'Kokurikuler (P5)',
            'extracurricular' => 'Ekstrakurikuler',
        ];
    }

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        $query = DB::table('final_grades')
            ->join('teaching_assignments', 'final_grades.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->join('academic_periods', 'teaching_assignments.academic_period_id', '=', 'academic_periods.id')
            ->join('classrooms', 'teaching_assignments.classroom_id', '=', 'classrooms.id')
            ->join('subjects', 'teaching_assignments.subject_id', '=', 'subjects.id')
            ->select(
                'academic_periods.id as period_id',
                DB::raw("CONCAT(academic_periods.start_year, '/', academic_periods.end_year, ' - ', academic_periods.semester) as period_name"),
                'classrooms.grade_level',
                DB::raw('ROUND(AVG(final_grades.final_score), 2) as average_score')
            )
            ->groupBy(
                'academic_periods.id',
                'period_name',
                'classrooms.grade_level',
                'academic_periods.start_date'
            )
            ->orderBy('academic_periods.start_date', 'ASC');

        if ($activeFilter && $activeFilter !== 'all') {
            $query->where('subjects.type', $activeFilter);
        }

        $results = $query->get();

        $labels = [];
        $datasetsMap = [
            7 => ['label' => 'Kelas 7', 'data' => [], 'borderColor' => '#3b82f6', 'fill' => false, 'tension' => 0.4],
            8 => ['label' => 'Kelas 8', 'data' => [], 'borderColor' => '#eab308', 'fill' => false, 'tension' => 0.4],
            9 => ['label' => 'Kelas 9', 'data' => [], 'borderColor' => '#10b981', 'fill' => false, 'tension' => 0.4],
        ];

        // Ensure we collect all unique periods chronologically to act as labels
        $periods = $results->map(function ($row) {
            return [
                'id' => $row->period_id,
                'name' => $row->period_name,
            ];
        })->unique('id')->values();

        foreach ($periods as $period) {
            $labels[] = $period['name'];
            
            // For each level, find the average_score in this period
            foreach ([7, 8, 9] as $level) {
                $match = $results->first(function ($row) use ($period, $level) {
                    return $row->period_id === $period['id'] && (int)$row->grade_level === $level;
                });
                
                $datasetsMap[$level]['data'][] = $match ? (float) $match->average_score : null;
            }
        }

        return [
            'datasets' => array_values($datasetsMap),
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'min' => 0,
                    'max' => 100,
                ],
            ],
        ];
    }
}
