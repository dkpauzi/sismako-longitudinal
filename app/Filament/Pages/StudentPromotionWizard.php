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
use Livewire\Attributes\On;

class StudentPromotionWizard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Proses Kenaikan Kelas';
    protected static ?string $title = 'Proses Kenaikan Kelas & Kelulusan';
    protected static string $view = 'filament.pages.student-promotion-wizard';
    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    public ?array $data = [];

    // ── CHUNKING STATE ──────────────────────────────────────────────
    // Properti ini menyimpan state proses chunking yang berjalan.
    // Livewire menjaga state ini di antara request melalui dehydration/hydration.

    /** Antrian data promosi yang belum diproses */
    public array $pendingQueue = [];

    /** ID Tahun Ajaran Tujuan (disimpan saat startProcessing) */
    public ?int $targetPeriodId = null;

    /** Jumlah siswa yang sudah berhasil diproses */
    public int $processedCount = 0;

    /** Total siswa yang harus diproses */
    public int $totalCount = 0;

    /** Flag apakah sedang dalam proses chunking */
    public bool $isProcessing = false;

    /** Pesan error jika ada chunk yang gagal */
    public ?string $errorMessage = null;

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
                                ->options(AcademicPeriod::getSelectOptions())
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
                                        ->get()
                                        ->mapWithKeys(fn($p) => [$p->id => $p->name]);
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
                                    // Tingkat kelas asal (diisi populateStudents) — dasar
                                    // gerbang validasi promosi agar tidak ada lompatan absurd.
                                    Hidden::make('source_grade_level'),

                                    TextInput::make('student_name')
                                        ->label('Nama Siswa')
                                        ->disabled()
                                        ->columnSpan(1),

                                    Select::make('action')
                                        ->label('Status')
                                        // GERBANG JENJANG (Audit 3.8): opsi status dibatasi
                                        // tingkat kelas asal. Kelas < 9 tidak bisa "Lulus";
                                        // kelas 9 (akhir SMP) tidak bisa "Naik Kelas".
                                        ->options(fn (Get $get) => self::actionOptionsForGrade($get('source_grade_level')))
                                        ->required()
                                        ->live()
                                        // Reset kelas tujuan setiap status berubah agar dropdown
                                        // ter-repopulasi dengan jenjang yang benar.
                                        ->afterStateUpdated(fn (Set $set) => $set('target_classroom_id', null))
                                        ->columnSpan(1),

                                    Select::make('target_classroom_id')
                                        ->label('Kelas Tujuan')
                                        // Hanya kelas berjenjang tepat: Naik → grade+1,
                                        // Tinggal → grade sama. Mencegah 7 lompat ke 9.
                                        ->options(fn (Get $get) => self::targetClassroomOptions(
                                            $get('source_grade_level'),
                                            $get('action')
                                        ))
                                        ->required(fn (Get $get) => in_array($get('action'), ['promoted', 'retained']))
                                        ->disabled(fn (Get $get) => $get('action') === 'graduated')
                                        ->columnSpan(2),
                                ]),
                        ]),
                ])
                ->submitAction(new HtmlString('<button type="submit" class="fi-btn fi-btn-color-primary" id="btn-submit-promotion">Proses Kenaikan Kelas</button>'))
            ])
            ->statePath('data');
    }

    protected function populateStudents(Set $set, Get $get): void
    {
        $periodId = $get('source_academic_period_id');
        $classroomId = $get('source_classroom_id');

        if (!$periodId || !$classroomId) {
            $set('students', []);
            return;
        }

        $sourceGrade = Classroom::find($classroomId)?->grade_level;

        // Default status disesuaikan jenjang: kelas akhir (9) default "Lulus"
        // karena "Naik Kelas" tidak tersedia; selain itu default "Naik Kelas".
        $defaultAction = ($sourceGrade !== null && $sourceGrade >= 9) ? 'graduated' : 'promoted';

        $enrollments = Enrollment::where('academic_period_id', $periodId)
            ->where('classroom_id', $classroomId)
            ->where('status', 'active')
            ->with('student')
            ->get();

        $students = [];
        foreach ($enrollments as $enrollment) {
            $students[] = [
                'enrollment_id' => $enrollment->id,
                'source_grade_level' => $sourceGrade,
                'student_name' => $enrollment->student->name . ' (' . $enrollment->student->nisn . ')',
                'action' => $defaultAction,
                'target_classroom_id' => null,
            ];
        }

        $set('students', $students);
    }

    /**
     * Opsi status promosi yang sah untuk suatu tingkat kelas (Audit 3.8).
     * - Kelas < 9 : Naik / Tinggal (tidak boleh Lulus).
     * - Kelas 9   : Tinggal / Lulus (tidak boleh Naik — akhir jenjang SMP).
     * - Tidak diketahui: fallback semua opsi (defensif).
     *
     * @return array<string,string>
     */
    public static function actionOptionsForGrade(mixed $gradeLevel): array
    {
        if ($gradeLevel === null || $gradeLevel === '') {
            return ['promoted' => 'Naik Kelas', 'retained' => 'Tinggal Kelas', 'graduated' => 'Lulus'];
        }

        $grade = (int) $gradeLevel;

        if ($grade >= 9) {
            return ['retained' => 'Tinggal Kelas', 'graduated' => 'Lulus'];
        }

        return ['promoted' => 'Naik Kelas', 'retained' => 'Tinggal Kelas'];
    }

    /**
     * Opsi Kelas Tujuan yang sah berdasarkan tingkat asal & status (Audit 3.8).
     * - promoted → hanya kelas ber-grade (asal + 1).
     * - retained → hanya kelas ber-grade sama.
     * - graduated / lainnya → tidak ada (tanpa kelas tujuan).
     *
     * @return array<int,string>
     */
    public static function targetClassroomOptions(mixed $gradeLevel, ?string $action): array
    {
        if ($gradeLevel === null || $gradeLevel === '') {
            // Jenjang tak diketahui: jangan batasi (defensif, submit tetap divalidasi).
            return Classroom::orderBy('grade_level')->orderBy('name')->pluck('name', 'id')->toArray();
        }

        $grade = (int) $gradeLevel;

        $targetGrade = match ($action) {
            'promoted' => $grade + 1,
            'retained' => $grade,
            default => null,
        };

        if ($targetGrade === null) {
            return [];
        }

        return Classroom::where('grade_level', $targetGrade)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Dipanggil oleh wire:submit pada form.
     * Validasi data, populate antrian, dan mulai proses chunking.
     */
    public function submit(): void
    {
        // Cegah submit ganda saat masih memproses
        if ($this->isProcessing) {
            return;
        }

        $data = $this->form->getState();
        $promotions = $data['students'] ?? [];
        $this->targetPeriodId = (int) $data['target_academic_period_id'];

        if (empty($promotions)) {
            Notification::make()
                ->title('Tidak ada siswa yang diproses.')
                ->warning()
                ->send();
            return;
        }

        // Inisialisasi state chunking
        $this->pendingQueue = $promotions;
        $this->totalCount = count($promotions);
        $this->processedCount = 0;
        $this->isProcessing = true;
        $this->errorMessage = null;

        // Dispatch event ke browser → browser akan segera memanggilnya kembali.
        // Ini memastikan PHP mendapatkan execution timer BARU untuk setiap chunk.
        $this->dispatch('process-next-batch');
    }

    /**
     * Proses satu chunk dari antrian.
     *
     * ARSITEKTUR SHARED HOSTING:
     * Livewire event recursion: setiap kali method ini selesai, response
     * dikirim ke browser. Browser menerima instruksi dispatch('process-next-batch'),
     * lalu mengirim request HTTP baru. Request baru = PHP execution timer di-reset.
     *
     * Ini menghindari timeout pada shared hosting yang memiliki
     * max_execution_time rendah (30-60 detik).
     */
    #[On('process-next-batch')]
    public function processNextBatch(): void
    {
        if (empty($this->pendingQueue)) {
            $this->finishProcessing();
            return;
        }

        // Ambil chunk dari depan antrian
        $chunk = array_splice($this->pendingQueue, 0, PromotionService::CHUNK_SIZE);

        $service = app(PromotionService::class);
        $result = $service->processChunk($chunk, $this->targetPeriodId);

        if (!$result['success']) {
            // Hentikan proses jika chunk gagal
            $this->isProcessing = false;
            $this->errorMessage = $result['message'];

            Notification::make()
                ->title('Proses Gagal')
                ->body($result['message'] . " ({$this->processedCount}/{$this->totalCount} sudah diproses)")
                ->danger()
                ->send();
            return;
        }

        $this->processedCount += $result['processed'];

        // Jika masih ada antrian, dispatch lagi → browser round-trip → timer reset
        if (!empty($this->pendingQueue)) {
            $this->dispatch('process-next-batch');
        } else {
            $this->finishProcessing();
        }
    }

    /**
     * Selesaikan proses dan tampilkan notifikasi sukses.
     */
    private function finishProcessing(): void
    {
        $this->isProcessing = false;

        Notification::make()
            ->title('Proses Berhasil')
            ->body("{$this->processedCount} siswa berhasil diproses.")
            ->success()
            ->send();

        // Reset form untuk siklus berikutnya
        $this->form->fill();
        $this->pendingQueue = [];
    }

    /**
     * Hitung persentase progress untuk progress bar di blade view.
     */
    public function getProgressPercentageProperty(): int
    {
        if ($this->totalCount === 0) {
            return 0;
        }

        return (int) round(($this->processedCount / $this->totalCount) * 100);
    }
}
