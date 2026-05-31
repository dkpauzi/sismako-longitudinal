<?php

namespace App\Filament\Widgets;

use App\Models\AcademicPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AttendanceMatrixWidget extends ChartWidget
{
    protected static ?string $heading = 'Matriks Kedisiplinan & Absensi Bulanan';
    
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    // Pemetaan angka bulan ke nama bulan dalam Bahasa Indonesia
    private const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public static function canView(): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        // Widget ini secara spesifik hanya ditujukan untuk level Eksekutif/Bimbingan
        return $user->hasRole(['super_admin', 'headmaster', 'guru_bk']);
    }

    public function mount(): void
    {
        // Tetapkan filter default ke Tahun Ajaran yang sedang aktif
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        if ($activePeriod) {
            $this->filter = (string) $activePeriod->id;
        }
    }

    protected function getFilters(): ?array
    {
        return AcademicPeriod::query()
            ->orderByDesc('start_year')
            ->orderByDesc('semester')
            ->get()
            ->mapWithKeys(function ($period) {
                $semesterLabel = $period->semester === 'odd' ? 'Ganjil' : 'Genap';
                return [$period->id => "{$period->name} - {$semesterLabel}"];
            })
            ->toArray();
    }

    protected function getData(): array
    {
        $periodId = $this->filter;

        if (!$periodId) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // KENDALA ARSITEKTUR: Jangan gunakan foreach Eloquent. Gunakan Query Database murni.
        $results = DB::table('attendances')
            ->join('teaching_assignments', 'attendances.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->where('teaching_assignments.academic_period_id', $periodId)
            ->selectRaw("
                MONTH(attendances.date) as month_num,
                SUM(CASE WHEN attendances.status = 'sick' THEN 1 ELSE 0 END) as total_sick,
                SUM(CASE WHEN attendances.status = 'permit' THEN 1 ELSE 0 END) as total_permit,
                SUM(CASE WHEN attendances.status = 'alpha' THEN 1 ELSE 0 END) as total_alpha
            ")
            ->groupBy('month_num')
            ->orderBy('month_num', 'asc')
            ->get();

        $labels = [];
        $dataSick = [];
        $dataPermit = [];
        $dataAlpha = [];

        // Konversi pemetaan bulan dan pemisahan array
        foreach ($results as $row) {
            $labels[] = self::MONTHS[$row->month_num] ?? "Bulan {$row->month_num}";
            $dataSick[] = (int) $row->total_sick;
            $dataPermit[] = (int) $row->total_permit;
            $dataAlpha[] = (int) $row->total_alpha;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sakit',
                    'data' => $dataSick,
                    'backgroundColor' => '#3b82f6', // Tailwind Blue-500
                ],
                [
                    'label' => 'Izin',
                    'data' => $dataPermit,
                    'backgroundColor' => '#eab308', // Tailwind Yellow-500
                ],
                [
                    'label' => 'Alpa',
                    'data' => $dataAlpha,
                    'backgroundColor' => '#ef4444', // Tailwind Red-500
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ],
            ],
        ];
    }
}
