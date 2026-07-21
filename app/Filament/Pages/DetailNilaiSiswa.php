<?php
// app/Filament/Pages/DetailNilaiSiswa.php

namespace App\Filament\Pages;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\NilaiVisualisasiService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class DetailNilaiSiswa extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup  = 'Akademik';
    protected static ?string $navigationLabel  = 'Grafik Nilai Siswa';
    protected static ?string $navigationIcon   = 'heroicon-o-chart-bar';
    protected static ?int    $navigationSort   = 10;
    protected static string  $view             = 'filament.pages.detail-nilai-siswa';

    // State
    public ?int    $student_id    = null;
    public array   $chartData     = [];
    public array   $subjectList   = [];
    public array   $selectedSubjects = [];

    // State filter bertingkat (cascade): status → kelas → siswa.
    public ?string $filter_status    = 'active';
    public ?int    $filter_classroom = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole([
            'super_admin', 'admin', 'headmaster', 'teacher', 'student', 'guardian'
        ]) ?? false;
    }

    public function mount(): void
    {
        $user = Auth::user();

        // Siswa langsung diarahkan ke datanya sendiri
        if ($user->hasRole('student')) {
            $this->student_id = $user->student?->id;
            $this->loadChartData();
        }

        // Wali Siswa diarahkan ke data anak pertamanya
        if ($user->hasRole('guardian')) {
            $firstChild = $user->guardianStudents()->first();
            $this->student_id = $firstChild?->id;
            $this->loadChartData();
        }
    }

    /** Field pemilih hanya untuk staf/guru — siswa & wali langsung ke datanya sendiri. */
    protected function isPickerVisible(): bool
    {
        $user = Auth::user();

        return $user && ! $user->hasRole('student') && ! $user->hasRole('guardian');
    }

    public function form(Form $form): Form
    {
        // CATATAN: getAccessibleStudents() TIDAK lagi dipanggil di sini. Dulu ia
        // jalan pada SETIAP render (termasuk untuk siswa/wali yang fieldnya
        // tersembunyi) dan memuat SELURUH siswa ke options. Kini hanya dievaluasi
        // di dalam closure options siswa, setelah kelas dipilih.
        return $form
            ->schema([
                // ── 1) STATUS ──────────────────────────────────────────────
                Select::make('filter_status')
                    ->label('Status Siswa')
                    ->options(['active' => 'Aktif', 'graduated' => 'Lulus'])
                    ->default('active')
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        // Reset turunannya agar tidak menyisakan pilihan basi.
                        $set('filter_classroom', null);
                        $set('student_id', null);
                        $this->selectedSubjects = [];
                        $this->loadChartData();
                    })
                    ->visible(fn() => $this->isPickerVisible()),

                // ── 2) KELAS (tergantung status) ───────────────────────────
                Select::make('filter_classroom')
                    ->label('Kelas')
                    ->placeholder('Pilih kelas')
                    ->options(fn(Get $get) => $this->classroomOptions($get('filter_status')))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('student_id', null);
                        $this->selectedSubjects = [];
                        $this->loadChartData();
                    })
                    ->helperText(fn(Get $get) => $get('filter_status') === 'graduated'
                        ? 'Untuk alumni, kelas diambil dari rombel TERAKHIR mereka (bukan periode aktif).'
                        : 'Kelas dengan siswa aktif pada tahun ajaran berjalan.')
                    ->visible(fn() => $this->isPickerVisible()),

                // ── 3) SISWA (tergantung status + kelas) ───────────────────
                Select::make('student_id')
                    ->label('Pilih Siswa')
                    ->placeholder(fn(Get $get) => $get('filter_classroom') ? 'Pilih siswa' : 'Pilih kelas terlebih dahulu')
                    ->options(fn(Get $get) => $this->studentOptions($get('filter_status'), $get('filter_classroom')))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->selectedSubjects = [];
                        $this->loadChartData();
                    })
                    ->visible(fn() => $this->isPickerVisible()),

                Select::make('selectedSubjects')
                    ->label('Gabungkan Mata Pelajaran (Filter)')
                    ->multiple()
                    ->options(fn() => collect($this->subjectList)->mapWithKeys(fn($s) => [$s => $s]))
                    ->live()
                    ->afterStateUpdated(fn() => $this->loadChartData())
                    ->visible(fn() => $this->student_id !== null),
            ])
            ->columns(2);
    }

    /**
     * Peta alumni: student_id => classroom_id dari enrollment TERAKHIR.
     *
     * Dibutuhkan karena alumni TIDAK punya enrollment di periode aktif —
     * memfilter kelas "pada periode aktif" akan mengembalikan kosong untuk
     * status 'Lulus'. Kelas mereka diambil dari rombel terakhir.
     */
    protected function graduatedLastClassroomMap(): \Illuminate\Support\Collection
    {
        $graduatedIds = Student::where('status', 'graduated')->pluck('id');

        if ($graduatedIds->isEmpty()) {
            return collect();
        }

        return Enrollment::query()
            ->select('enrollments.student_id', 'enrollments.classroom_id', 'academic_periods.start_year', 'academic_periods.semester')
            ->join('academic_periods', 'academic_periods.id', '=', 'enrollments.academic_period_id')
            ->whereIn('enrollments.student_id', $graduatedIds)
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows
                ->sortByDesc(fn ($r) => ((int) $r->start_year * 10) + ($r->semester === 'odd' ? 1 : 2))
                ->first()
                ->classroom_id);
    }

    /** Opsi kelas — bercabang sesuai status (aktif vs alumni). */
    protected function classroomOptions(?string $status): array
    {
        if ($status === 'graduated') {
            $ids = $this->graduatedLastClassroomMap()->values()->unique();
        } else {
            $activePeriodId = AcademicPeriod::where('is_active', true)->value('id');
            $ids = Enrollment::where('academic_period_id', $activePeriodId)
                ->where('status', 'active')
                ->pluck('classroom_id')
                ->unique();
        }

        return Classroom::whereIn('id', $ids)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Opsi siswa — hanya dihitung SETELAH kelas dipilih (inti perbaikan:
     * tidak pernah lagi menumpahkan seluruh siswa ke dalam satu dropdown).
     * Tetap dipotong dengan getAccessibleStudents() agar cakupan akses per-role
     * (mis. guru hanya siswa asuhannya) tidak melonggar.
     */
    protected function studentOptions(?string $status, $classroomId): array
    {
        if (! $classroomId) {
            return [];
        }

        $accessible = app(NilaiVisualisasiService::class)->getAccessibleStudents()->pluck('id');

        if ($status === 'graduated') {
            $ids = $this->graduatedLastClassroomMap()
                ->filter(fn ($cid) => (int) $cid === (int) $classroomId)
                ->keys();
        } else {
            $activePeriodId = AcademicPeriod::where('is_active', true)->value('id');
            $ids = Enrollment::where('academic_period_id', $activePeriodId)
                ->where('classroom_id', $classroomId)
                ->where('status', 'active')
                ->pluck('student_id');
        }

        return Student::whereIn('id', $ids->intersect($accessible))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function loadChartData(): void
    {
        if (!$this->student_id) {
            $this->chartData   = [];
            $this->subjectList = [];
            return;
        }

        $service = app(NilaiVisualisasiService::class);

        // Cek akses
        if (!$service->canViewStudent($this->student_id)) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Anda tidak memiliki akses untuk melihat data nilai siswa ini.')
                ->danger()
                ->send();

            $this->student_id = null;
            return;
        }

        $longitudinal = $service->getNilaiLongitudinal($this->student_id);

        // Kumpulkan semua mata pelajaran unik
        $this->subjectList = collect($longitudinal)
            ->flatMap(fn($grades) => array_keys($grades))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $periods = array_keys($longitudinal);

        // Jika tidak ada subject yg dipilih, setel otomatis ke pelajaran pertama
        if (empty($this->selectedSubjects)) {
            $this->selectedSubjects = isset($this->subjectList[0]) ? [$this->subjectList[0]] : [];
        }

        $datasets = [];

        if (!empty($this->selectedSubjects)) {
            $colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316'];
            
            $averageData = array_fill(0, count($periods), 0);
            $countData = array_fill(0, count($periods), 0);

            foreach ($this->selectedSubjects as $subject) {
                if (!in_array($subject, $this->subjectList)) continue;

                $data = [];
                $colorIdx = array_search($subject, $this->subjectList);
                $color = $colors[$colorIdx % count($colors)];

                foreach ($periods as $pIndex => $period) {
                    $score = $longitudinal[$period][$subject] ?? null;
                    $data[] = $score;
                    if ($score !== null) {
                        $averageData[$pIndex] += $score;
                        $countData[$pIndex]++;
                    }
                }

                // Dataset Batang (Bar) per matapelajaran
                $datasets[] = [
                    'type'             => 'bar',
                    'label'            => $subject,
                    'data'             => $data,
                    'borderColor'      => $color,
                    'backgroundColor'  => $color . '60', // opacity
                    'borderWidth'      => 1,
                    'borderRadius'     => 4,
                ];
            }

            // Hitung rata-rata untuk garis (Line) & Bar Rata-rata jika lebih dari 1
            $avgDataValues = [];
            foreach ($periods as $pIndex => $period) {
                if ($countData[$pIndex] > 0) {
                    $avgDataValues[] = round($averageData[$pIndex] / $countData[$pIndex], 2);
                } else {
                    $avgDataValues[] = null;
                }
            }

            if (count($this->selectedSubjects) > 1) {
                // Dataset Batang (Bar) untuk Rata-rata Gabungan
                $datasets[] = [
                    'type'             => 'bar',
                    'label'            => 'Rata-rata Gabungan (Bar)',
                    'data'             => $avgDataValues,
                    'borderColor'      => '#4B5563', // Gray-600
                    'backgroundColor'  => '#9CA3AF80', // Gray-400 with opacity
                    'borderWidth'      => 1,
                    'borderRadius'     => 4,
                ];
            }

            // Dataset Tren Garis (Line)
            $labelLine = count($this->selectedSubjects) > 1 ? 'Rata-rata Gabungan (Tren)' : $this->selectedSubjects[0] . ' (Tren)';
            $datasets[] = [
                'type'             => 'line',
                'label'            => $labelLine,
                'data'             => $avgDataValues,
                'borderColor'      => '#111827', // Gray-900 (Gelap)
                'backgroundColor'  => '#111827',
                'fill'             => false,
                'tension'          => 0.4,
                'spanGaps'         => true,
                'pointRadius'      => 6,
                'pointHoverRadius' => 8,
                'borderWidth'      => 3,
                'borderDash'       => count($this->selectedSubjects) > 1 ? [5, 5] : [],
            ];
        }

        $this->chartData = [
            'labels'   => $periods,
            'datasets' => $datasets,
        ];
    }

    public function getStudentInfo(): ?Student
    {
        if (!$this->student_id) return null;
        return Student::find($this->student_id);
    }
}