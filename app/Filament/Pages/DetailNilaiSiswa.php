<?php
// app/Filament/Pages/DetailNilaiSiswa.php

namespace App\Filament\Pages;

use App\Models\Student;
use App\Services\NilaiVisualisasiService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
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
    public ?string $selectedSubject = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole([
            'super_admin', 'headmaster', 'teacher', 'student'
        ]) ?? false;
    }

    public function mount(): void
    {
        // Siswa langsung diarahkan ke datanya sendiri
        if (Auth::user()->hasRole('student')) {
            $this->student_id = Auth::user()->student?->id;
            $this->loadChartData();
        }
    }

    public function form(Form $form): Form
    {
        $service  = app(NilaiVisualisasiService::class);
        $students = $service->getAccessibleStudents();

        return $form
            ->schema([
                Select::make('student_id')
                    ->label('Pilih Siswa')
                    ->options($students->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn() => $this->loadChartData())
                    ->visible(fn() => !Auth::user()->hasRole('student')),

                Select::make('selectedSubject')
                    ->label('Filter Mata Pelajaran')
                    ->options(fn() => collect($this->subjectList)
                        ->mapWithKeys(fn($s) => [$s => $s])
                        ->prepend('Semua Mata Pelajaran', 'all')
                    )
                    ->default('all')
                    ->live()
                    ->afterStateUpdated(fn() => $this->loadChartData())
                    ->visible(fn() => $this->student_id !== null),
            ])
            ->columns(2);
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

        // Filter berdasarkan pilihan mapel
        $subjectsToShow = ($this->selectedSubject && $this->selectedSubject !== 'all')
            ? [$this->selectedSubject]
            : $this->subjectList;

        $colors = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B',
            '#8B5CF6', '#EC4899', '#14B8A6', '#F97316',
        ];

        $datasets = [];
        foreach ($subjectsToShow as $idx => $subject) {
            $data = [];
            foreach ($periods as $period) {
                $data[] = $longitudinal[$period][$subject] ?? null;
            }

            $color      = $colors[$idx % count($colors)];
            $datasets[] = [
                'label'            => $subject,
                'data'             => $data,
                'borderColor'      => $color,
                'backgroundColor'  => $color . '20',
                'fill'             => false,
                'tension'          => 0.3,
                'spanGaps'         => true,
                'pointRadius'      => 5,
                'pointHoverRadius' => 7,
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