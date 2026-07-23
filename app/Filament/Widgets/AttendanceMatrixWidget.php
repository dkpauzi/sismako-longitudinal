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
            // Accessor `name` SUDAH memuat label semester (mis. "2025/2026 Genap"),
            // jadi jangan ditambahi lagi — dulu tercetak ganda: "… Genap - Genap".
            ->mapWithKeys(fn ($period) => [$period->id => $period->name])
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

    /**
     * Keterangan di bawah judul. Saat tidak ada data, Chart.js hanya menampilkan
     * area kosong tanpa penjelasan sehingga terlihat seperti aplikasi error —
     * deskripsi ini membuat kondisi "memang belum ada data" menjadi eksplisit.
     */
    public function getDescription(): ?string
    {
        if (! $this->filter) {
            return 'Pilih tahun ajaran untuk menampilkan data.';
        }

        if (! $this->hasAttendanceData()) {
            return 'Belum ada data absensi harian pada tahun ajaran ini.';
        }

        return 'Rekap ketidakhadiran (Sakit / Izin / Alpa) per bulan.';
    }

    /** Apakah ada minimal satu baris absensi pada periode terpilih? */
    private function hasAttendanceData(): bool
    {
        return DB::table('attendances')
            ->join('teaching_assignments', 'attendances.teaching_assignment_id', '=', 'teaching_assignments.id')
            ->where('teaching_assignments.academic_period_id', $this->filter)
            ->exists();
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
