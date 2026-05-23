<?php

namespace App\Filament\Pages;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Services\PromotionService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;

class StudentPromotionWizard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Proses Kenaikan Kelas';
    protected static ?string $title = 'Proses Kenaikan Kelas & Kelulusan';
    protected static string $view = 'filament.pages.student-promotion-wizard';
    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Pilih Kelas Asal')
                        ->description('Pilih Tahun Ajaran dan Kelas yang akan diproses.')
                        ->schema([
                            Select::make('source_academic_period_id')
                                ->label('Tahun Ajaran Asal')
                                ->options(AcademicPeriod::pluck('name', 'id'))
                                ->required()
                                ->live(),

                            Select::make('source_classroom_id')
                                ->label('Kelas Asal')
                                ->options(Classroom::pluck('name', 'id'))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get) {
                                    $this->populateStudents($set, $get);
                                }),
                        ]),

                    Wizard\Step::make('Pilih Tahun Ajaran Tujuan')
                        ->description('Pilih Tahun Ajaran berikutnya.')
                        ->schema([
                            Select::make('target_academic_period_id')
                                ->label('Tahun Ajaran Tujuan')
                                ->options(function (Get $get) {
                                    return AcademicPeriod::where('id', '!=', $get('source_academic_period_id'))
                                        ->pluck('name', 'id');
                                })
                                ->required()
                                ->live(),
                        ]),

                    Wizard\Step::make('Proses Siswa')
                        ->description('Tentukan status tiap siswa (Naik/Tinggal/Lulus).')
                        ->schema([
                            Repeater::make('students')
                                ->label('')
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columns(4)
                                ->schema([
                                    Hidden::make('enrollment_id'),
                                    
                                    TextInput::make('student_name')
                                        ->label('Nama Siswa')
                                        ->disabled()
                                        ->columnSpan(1),
                                        
                                    Select::make('action')
                                        ->label('Status')
                                        ->options([
                                            'promoted' => 'Naik Kelas',
                                            'retained' => 'Tinggal Kelas',
                                            'graduated' => 'Lulus',
                                        ])
                                        ->default('promoted')
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            if ($state === 'retained') {
                                                // Jika tinggal kelas, otomatis set ke kelas asal (default suggestion)
                                                // We can't access parent state easily in nested, but we try to set it to current classroom logic
                                                $set('target_classroom_id', null); 
                                            } elseif ($state === 'graduated') {
                                                $set('target_classroom_id', null);
                                            }
                                        })
                                        ->columnSpan(1),
                                        
                                    Select::make('target_classroom_id')
                                        ->label('Kelas Tujuan')
                                        ->options(Classroom::pluck('name', 'id'))
                                        ->required(fn (Get $get) => in_array($get('action'), ['promoted', 'retained']))
                                        ->disabled(fn (Get $get) => $get('action') === 'graduated')
                                        ->columnSpan(2),
                                ]),
                        ]),
                ])
                ->submitAction(new HtmlString('<button type="submit" class="fi-btn fi-btn-color-primary">Proses Kenaikan Kelas</button>'))
            ])
            ->statePath('data');
    }

    protected function populateStudents(Set $set, Get $get)
    {
        $periodId = $get('source_academic_period_id');
        $classroomId = $get('source_classroom_id');

        if (!$periodId || !$classroomId) {
            $set('students', []);
            return;
        }

        $enrollments = Enrollment::where('academic_period_id', $periodId)
            ->where('classroom_id', $classroomId)
            ->where('status', 'active')
            ->with('student')
            ->get();

        $students = [];
        foreach ($enrollments as $enrollment) {
            $students[] = [
                'enrollment_id' => $enrollment->id,
                'student_name' => $enrollment->student->name . ' (' . $enrollment->student->nisn . ')',
                'action' => 'promoted',
                'target_classroom_id' => null, // Admin needs to pick this, or we can auto-suggest
            ];
        }

        $set('students', $students);
    }

    public function submit(PromotionService $service)
    {
        $data = $this->form->getState();

        $promotions = $data['students'] ?? [];
        $targetPeriodId = $data['target_academic_period_id'];

        if (empty($promotions)) {
            Notification::make()
                ->title('Tidak ada siswa yang diproses.')
                ->warning()
                ->send();
            return;
        }

        $result = $service->processBatchPromotions($promotions, $targetPeriodId);

        if ($result['success']) {
            Notification::make()
                ->title('Proses Berhasil')
                ->body($result['message'])
                ->success()
                ->send();
                
            // Reset form
            $this->form->fill();
        } else {
            Notification::make()
                ->title('Proses Gagal')
                ->body($result['message'])
                ->danger()
                ->send();
        }
    }
}
