<?php

namespace App\Filament\Pages;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Services\PromotionService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * LANJUT SEMESTER (Ganjil → Genap tahun yang sama).
 *
 * Jalur mutasi enrollment TERPISAH dari Kenaikan Kelas (keputusan produk:
 * "Halaman terpisah Lanjut Semester"). Siswa TETAP di kelas & tingkat yang
 * sama; hanya enrollment periode Genap yang dibuat. Semua logika inti + guard
 * (rapor terkunci, periode Ganjil→Genap tahun sama) ada di
 * PromotionService::processSemesterChunk() agar teruji unit.
 *
 * Memakai pola event-recursion (chunk per round-trip) yang sama dengan
 * StudentPromotionWizard demi kepatuhan SRS §2 (QUEUE=sync, tanpa worker).
 */
class SemesterContinuationWizard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-long-right';
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Lanjut Semester';
    protected static ?string $title = 'Lanjut Semester (Ganjil → Genap)';
    protected static string $view = 'filament.pages.semester-continuation-wizard';
    protected static ?int $navigationSort = 12;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin']) ?? false;
    }

    public ?array $data = [];

    // ── CHUNKING STATE (paralel dengan StudentPromotionWizard) ──────
    public array $pendingQueue = [];
    public ?int $targetPeriodId = null;
    public int $processedCount = 0;
    public int $totalCount = 0;
    public bool $isProcessing = false;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('source_academic_period_id')
                    ->label('Tahun Ajaran Asal (Ganjil)')
                    // Hanya periode GANJIL yang bisa dilanjutkan ke Genap.
                    ->options(fn () => AcademicPeriod::where('semester', 'odd')
                        ->get()
                        ->mapWithKeys(fn ($p) => [$p->id => $p->name])
                        ->toArray())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('students', [])),

                Select::make('source_classroom_id')
                    ->label('Kelas')
                    ->options(Classroom::orderBy('grade_level')->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get) => $this->populateStudents($set, $get)),

                Placeholder::make('target_info')
                    ->label('Tahun Ajaran Tujuan (otomatis)')
                    ->content(function (Get $get): HtmlString {
                        $target = $this->resolveTargetPeriod($get('source_academic_period_id'));
                        if (!$target) {
                            return new HtmlString('<span class="text-danger-600">Belum ada periode <strong>Genap</strong> untuk tahun ajaran ini. Buat dulu di menu Tahun Ajaran.</span>');
                        }

                        return new HtmlString('<span class="text-success-600 font-medium">' . e($target->name) . '</span> — kelas & tingkat tidak berubah.');
                    }),

                Repeater::make('students')
                    ->label('Daftar Siswa yang Akan Dilanjutkan')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->schema([
                        Hidden::make('enrollment_id'),
                        TextInput::make('student_name')->label('Nama Siswa')->disabled(),
                    ]),
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

        $students = Enrollment::where('academic_period_id', $periodId)
            ->where('classroom_id', $classroomId)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->map(fn ($e) => [
                'enrollment_id' => $e->id,
                'student_name' => $e->student->name . ' (' . $e->student->nisn . ')',
            ])
            ->toArray();

        $set('students', $students);
    }

    /**
     * Cari periode Genap pada tahun ajaran yang sama dengan periode Ganjil asal.
     */
    private function resolveTargetPeriod(mixed $sourcePeriodId): ?AcademicPeriod
    {
        $source = $sourcePeriodId ? AcademicPeriod::find($sourcePeriodId) : null;
        if (!$source || $source->semester !== 'odd') {
            return null;
        }

        return AcademicPeriod::where('semester', 'even')
            ->where('start_year', $source->start_year)
            ->where('end_year', $source->end_year)
            ->first();
    }

    public function submit(): void
    {
        if ($this->isProcessing) {
            return;
        }

        $data = $this->form->getState();
        $students = $data['students'] ?? [];

        $target = $this->resolveTargetPeriod($data['source_academic_period_id'] ?? null);
        if (!$target) {
            Notification::make()
                ->title('Periode Genap tujuan tidak ditemukan')
                ->body('Buat dulu Tahun Ajaran semester Genap untuk tahun ajaran ini.')
                ->danger()
                ->send();
            return;
        }

        if (empty($students)) {
            Notification::make()->title('Tidak ada siswa untuk dilanjutkan.')->warning()->send();
            return;
        }

        $this->targetPeriodId = $target->id;
        $this->pendingQueue = $students;
        $this->totalCount = count($students);
        $this->processedCount = 0;
        $this->isProcessing = true;
        $this->errorMessage = null;

        $this->dispatch('process-next-semester-batch');
    }

    #[On('process-next-semester-batch')]
    public function processNextBatch(): void
    {
        if (empty($this->pendingQueue)) {
            $this->finishProcessing();
            return;
        }

        $chunk = array_splice($this->pendingQueue, 0, PromotionService::CHUNK_SIZE);
        $result = app(PromotionService::class)->processSemesterChunk($chunk, $this->targetPeriodId);

        if (!$result['success']) {
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

        if (!empty($this->pendingQueue)) {
            $this->dispatch('process-next-semester-batch');
        } else {
            $this->finishProcessing();
        }
    }

    private function finishProcessing(): void
    {
        $this->isProcessing = false;

        Notification::make()
            ->title('Proses Berhasil')
            ->body("{$this->processedCount} siswa berhasil dilanjutkan ke semester Genap.")
            ->success()
            ->send();

        $this->form->fill();
        $this->pendingQueue = [];
    }

    public function getProgressPercentageProperty(): int
    {
        if ($this->totalCount === 0) {
            return 0;
        }

        return (int) round(($this->processedCount / $this->totalCount) * 100);
    }
}
