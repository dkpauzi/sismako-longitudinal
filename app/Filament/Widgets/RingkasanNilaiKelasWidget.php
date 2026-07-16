<?php
// app/Filament/Widgets/RingkasanNilaiKelasWidget.php

namespace App\Filament\Widgets;

use App\Models\AcademicPeriod;
use App\Models\ClassHomeroom;
use App\Models\Classroom;
use App\Services\NilaiVisualisasiService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class RingkasanNilaiKelasWidget extends ChartWidget
{
    protected static ?string $heading = 'Rata-rata Nilai Kelas';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    /** Memoization agar resolusi kelas tidak diulang antara heading & data. */
    private ?Classroom $resolvedClassroom = null;
    private bool $classroomResolved = false;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(['teacher', 'headmaster', 'super_admin']) ?? false;
    }

    /**
     * Heading dinamis: SELALU menampilkan nama kelas yang sedang ditampilkan
     * agar kepsek/admin tidak mengira ini ringkasan seluruh sekolah.
     */
    public function getHeading(): ?string
    {
        $name = $this->resolveClassroom()?->name;

        return $name
            ? 'Rata-rata Nilai Kelas — ' . $name
            : 'Rata-rata Nilai Kelas';
    }

    /**
     * Kepsek/Super Admin memilih kelas via dropdown filter bawaan ChartWidget
     * (menggantikan perilaku lama yang diam-diam mengambil kelas pertama).
     */
    protected function getFilters(): ?array
    {
        $user = Auth::user();

        if (!$user?->hasAnyRole(['super_admin', 'headmaster'])) {
            return null; // Wali kelas: otomatis kelasnya sendiri, tanpa dropdown
        }

        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        if (!$activePeriod) {
            return null;
        }

        return ClassHomeroom::where('academic_period_id', $activePeriod->id)
            ->with('classroom')
            ->get()
            ->filter(fn($h) => $h->classroom !== null)
            ->sortBy('classroom.name')
            ->mapWithKeys(fn($h) => [(string) $h->classroom_id => $h->classroom->name])
            ->toArray();
    }

    /**
     * Resolusi kelas yang ditampilkan (memoized):
     * - super_admin/headmaster → kelas dari filter terpilih (default: kelas pertama)
     * - wali kelas             → kelas yang dipegangnya pada periode aktif
     */
    private function resolveClassroom(): ?Classroom
    {
        if ($this->classroomResolved) {
            return $this->resolvedClassroom;
        }
        $this->classroomResolved = true;

        $user         = Auth::user();
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (!$user || !$activePeriod) {
            return $this->resolvedClassroom = null;
        }

        if ($user->hasAnyRole(['super_admin', 'headmaster'])) {
            $homeroom = ClassHomeroom::where('academic_period_id', $activePeriod->id)
                ->when($this->filter, fn($q) => $q->where('classroom_id', $this->filter))
                ->with('classroom')
                ->orderBy('classroom_id')
                ->first();
        } else {
            // Wali kelas → hanya kelasnya sendiri (null-safe bila profil belum tertaut)
            $homeroom = ClassHomeroom::where('teacher_id', $user->teacher?->id)
                ->where('academic_period_id', $activePeriod->id)
                ->with('classroom')
                ->first();
        }

        return $this->resolvedClassroom = $homeroom?->classroom;
    }

    protected function getData(): array
    {
        $service   = app(NilaiVisualisasiService::class);
        $classroom = $this->resolveClassroom();

        if (!$classroom) {
            return ['datasets' => [], 'labels' => []];
        }

        $data = $service->getRingkasanNilaiKelas($classroom->id);

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