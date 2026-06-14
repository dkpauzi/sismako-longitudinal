<?php

namespace App\Filament\Resources\TeachingAssignmentResource\RelationManagers;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Services\GradeRangeResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AssessmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assessments';
    protected static ?string $title = 'Rencana & Input Nilai';
    protected static ?string $icon = 'heroicon-o-academic-cap';

    /**
     * Sembunyikan Tab Nilai Akademik ini jika mapel adalah Kokurikuler/P5.
     */
    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return !$ownerRecord->isKokurikuler();
    }

    /**
     * Helper: Hitung total bobot yang sudah ada di DB (Hanya untuk Sumatif)
     * Formatif sama sekali tidak dihitung dalam kuota 100%.
     */
    /**
     * Helper: Apakah periode ini terkunci untuk user saat ini?
     * TRUE = periode non-aktif DAN user bukan admin/super_admin.
     */
    protected function isPeriodLocked(): bool
    {
        $active = (bool) $this->getOwnerRecord()->academicPeriod?->is_active;
        return !$active && !auth()->user()->hasAnyRole(['super_admin', 'admin']);
    }

    protected function getCurrentTotalWeight($excludeId = null): int
    {
        return $this->getOwnerRecord()->assessments()
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where('category', '!=', 'formatif_deskripsi')
            ->where('category', '!=', 'formatif_poin')
            ->sum('weight');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Asesmen')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Penilaian')
                            ->placeholder('Contoh: UH 1 / PR Bab 1 / Keaktifan Diskusi')
                            ->required(),

                        Forms\Components\Select::make('category')
                            ->label('Jenis Asesmen')
                            ->options([
                                'formatif_deskripsi' => 'Formatif Tipe 1 (Deskripsi Utama, Nilai Opsional)',
                                'formatif_poin' => 'Formatif Tipe 2 (Booster: Ceklis Poin PR/Keaktifan)',
                                'sumatif_lingkup_materi' => 'Sumatif Harian',
                                'sumatif_akhir_semester' => 'Sumatif Akhir (SAS)',
                            ])
                            ->default('sumatif_lingkup_materi')
                            ->live() // Memicu perubahan form bobot secara dinamis
                            ->required(),

                        Forms\Components\Select::make('technique')
                            ->label('Teknik')
                            ->options([
                                'tes_tertulis' => 'Tes Tertulis',
                                'penugasan' => 'Penugasan',
                                'projek' => 'Projek',
                                'kinerja' => 'Kinerja',
                                'observasi' => 'Observasi / Keaktifan'
                            ])
                            ->required(),

                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal Pelaksanaan')
                            ->default(now())
                            ->required(),

                        // --- FITUR BOBOT (Berubah sesuai kategori) ---
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\TextInput::make('weight')
                                    ->label(fn(Get $get) => $get('category') === 'formatif_poin' ? 'Poin Maksimal (Default 1)' : 'Persentase Bobot Sumatif (%)')
                                    ->numeric()
                                    ->default(fn(Get $get) => $get('category') === 'formatif_poin' ? 1 : 0)
                                    ->minValue(1)
                                    ->live(debounce: 500)
                                    ->required(),

                                // Indikator Sisa Kuota Bobot (Hanya muncul untuk Sumatif jika menggunakan formula Pembobotan)
                                Forms\Components\Placeholder::make('quota_info')
                                    ->label('Status Kuota Bobot Sumatif')
                                    ->content(function (Get $get, RelationManager $livewire, $record) {
                                        $currentDbTotal = $livewire->getCurrentTotalWeight($record?->id);
                                        $inputWeight = (int) $get('weight');
                                        $newTotal = $currentDbTotal + $inputWeight;
                                        $remaining = 100 - $newTotal;

                                        if ($newTotal > 100) {
                                            $diff = $newTotal - 100;
                                            return new HtmlString("<span class='text-danger-600 font-bold'>⛔ BERLEBIH! Kurangi {$diff}%.</span>");
                                        } elseif ($newTotal === 100) {
                                            return new HtmlString("<span class='text-success-600 font-bold'>✅ PAS 100%.</span>");
                                        } else {
                                            return new HtmlString("<span class='text-warning-600 font-bold'>ℹ️ Total: {$newTotal}%. Sisa {$remaining}%.</span>");
                                        }
                                    })
                                    ->visible(
                                        fn(Get $get, RelationManager $livewire) =>
                                        $livewire->getOwnerRecord()->grading_formula === 'weighting' &&
                                        !str_starts_with($get('category'), 'formatif')
                                    ),
                            ])
                            ->visible(
                                fn(Get $get, RelationManager $livewire) =>
                                $livewire->getOwnerRecord()->grading_formula === 'weighting' ||
                                $get('category') === 'formatif_poin' // Selalu muncul jika formatif poin dipilih
                            ),
                    ])->columns(2),

                // --- PILIH TP ---
                Forms\Components\CheckboxList::make('learningObjectives')
                    ->label('Tujuan Pembelajaran (TP)')
                    ->relationship(
                        name: 'learningObjectives',
                        titleAttribute: 'code',
                        modifyQueryUsing: function (Builder $query, RelationManager $livewire) {
                            $assignment = $livewire->getOwnerRecord();
                            $gradeLevel = $assignment->classroom->grade_level ?? null;

                            return $query
                                ->where('subject_id', $assignment->subject_id)
                                ->where('academic_period_id', $assignment->academic_period_id)
                                ->when($gradeLevel, fn($q) => $q->where('grade_level', $gradeLevel));
                        }
                    )
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->code} - " . \Illuminate\Support\Str::limit($record->attribute, 60))
                    ->columns(1)
                    // TP bersifat opsional untuk Formatif, tetapi wajib untuk Sumatif
                    ->required(fn(Get $get) => !str_starts_with($get('category'), 'formatif')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'formatif_deskripsi' => 'gray',
                        'formatif_poin' => 'success',
                        'sumatif_lingkup_materi' => 'info',
                        default => 'warning'
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'formatif_deskripsi' => 'Formatif (Deskripsi)',
                        'formatif_poin' => 'Formatif (Poin Booster)',
                        'sumatif_lingkup_materi' => 'Sumatif Harian',
                        default => 'SAS'
                    }),

                Tables\Columns\TextColumn::make('weight')
                    ->label('Bobot / Poin')
                    ->badge()
                    ->color(fn($state, $record) => $record->category === 'formatif_poin' ? 'success' : ($state > 0 ? 'primary' : 'gray'))
                    ->formatStateUsing(fn($state, $record) => $record->category === 'formatif_poin' ? "+{$state} Poin" : "{$state}%")
                    ->visible(
                        fn(RelationManager $livewire) =>
                        $livewire->getOwnerRecord()->grading_formula === 'weighting' ||
                        // ✅ PERBAIKAN: Cek dari relasi yang sudah ter-load, bukan query baru
                        $livewire->getOwnerRecord()->assessments->contains('category', 'formatif_poin')
                    ),

                Tables\Columns\TextColumn::make('date')->date('d M Y'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Buat Rencana Nilai')
                    ->visible(fn () => !$this->isPeriodLocked())
                    ->before(function (Tables\Actions\CreateAction $action, array $data, RelationManager $livewire) {
                        // Validasi persentase > 100% HANYA untuk Sumatif
                        if ($livewire->getOwnerRecord()->grading_formula === 'weighting' && !str_starts_with($data['category'], 'formatif')) {
                            $newTotal = $livewire->getCurrentTotalWeight() + (int) $data['weight'];
                            if ($newTotal > 100) {
                                Notification::make()->title('Gagal')->body("Total persentase Sumatif melebihi 100% ({$newTotal}%).")->danger()->send();
                                $action->halt();
                            }
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => !$this->isPeriodLocked())
                    ->before(function (Tables\Actions\EditAction $action, array $data, RelationManager $livewire, Assessment $record) {
                        if ($livewire->getOwnerRecord()->grading_formula === 'weighting' && !str_starts_with($data['category'], 'formatif')) {
                            $dbTotalWithoutThis = $livewire->getCurrentTotalWeight($record->id);
                            $newTotal = $dbTotalWithoutThis + (int) $data['weight'];
                            if ($newTotal > 100) {
                                Notification::make()->title('Gagal')->body("Total persentase Sumatif melebihi 100% ({$newTotal}%).")->danger()->send();
                                $action->halt();
                            }
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => !$this->isPeriodLocked()),

                // --- TOMBOL INPUT NILAI MASSAL (BERUBAH DINAMIS) ---
                Tables\Actions\Action::make('input_grades')
                    ->label('Input Nilai')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn () => !$this->isPeriodLocked())
                    ->modalWidth('5xl')
                    ->form(function (Assessment $record) {
                        // Merancang skema form berdasarkan kategori ujian
                        $schema = [];

                        $schema[] = Forms\Components\TextInput::make('student_name')->label('Nama Siswa')->disabled()->dehydrated(false);
                        $schema[] = Forms\Components\Hidden::make('student_id');

                        if ($record->category === 'formatif_deskripsi') {
                            // FORMATIF TIPE 1: Deskripsi Wajib, Angka Opsional
                            $schema[] = Forms\Components\TextInput::make('feedback')->label('Deskripsi Perkembangan (Wajib)')->required()->columnSpan(2);
                            $schema[] = Forms\Components\TextInput::make('score')->label('Nilai Angka (Opsional)')->numeric()->minValue(0)->maxValue(100)->columnSpan(2);
                        } elseif ($record->category === 'formatif_poin') {
                            // FORMATIF TIPE 2: Ceklis / Toggle (Poin Booster)
                            $schema[] = Forms\Components\Toggle::make('is_completed')
                                ->label("Mengerjakan / Aktif? (+{$record->weight} Poin)")
                                ->inline(false);
                            $schema[] = Forms\Components\TextInput::make('feedback')->label('Catatan (Opsional)');
                        } else {
                            // SUMATIF: Angka Wajib, Deskripsi Opsional
                            $schema[] = Forms\Components\TextInput::make('score')->label('Nilai Sumatif (0-100)')->numeric()->minValue(0)->maxValue(100)->required();
                            $schema[] = Forms\Components\TextInput::make('feedback')->label('Catatan (Opsional)');
                        }

                        return [
                            Forms\Components\Repeater::make('grades_data')
                                ->label('Daftar Siswa')
                                ->schema($schema)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columns(2)
                                ->columnSpanFull()
                        ];
                    })
                    ->mountUsing(function (Forms\Form $form, $record) {
                        $students = Enrollment::where('classroom_id', $record->teachingAssignment->classroom_id)
                            ->where('academic_period_id', $record->teachingAssignment->academic_period_id)
                            ->where('status', 'active')
                            ->with('student')
                            ->get()
                            ->sortBy('student.name');

                        $grades = Grade::where('assessment_id', $record->id)->get()->keyBy('student_id');

                        $form->fill([
                            'grades_data' => $students->map(function ($s) use ($grades, $record) {
                                $existingGrade = $grades[$s->student_id] ?? null;

                                return [
                                    'student_id' => $s->student_id,
                                    'student_name' => $s->student->name,
                                    'score' => $existingGrade->score ?? null,
                                    'feedback' => $existingGrade->feedback ?? null,
                                    // Set Toggle ke TRUE jika score > 0 (Artinya poin sudah pernah didapat/disimpan sebelumnya)
                                    'is_completed' => ($existingGrade->score ?? 0) > 0,
                                ];
                            })->values()->toArray()
                        ]);
                    })
                    ->action(function ($record, array $data) {
                        // Guard defense-in-depth: cegah eksekusi jika periode terkunci
                        if ($this->isPeriodLocked()) {
                            Notification::make()->title('Periode Terkunci')->body('Tidak dapat mengubah nilai pada periode yang sudah dibekukan.')->danger()->send();
                            return;
                        }

                        // ✅ PERBAIKAN PERFORMA: Matikan GradeObserver sementara.
                        // Sebelumnya setiap Grade::updateOrCreate memicu Observer
                        // yang menjalankan calculateFinalGrade() per siswa.
                        // 30 siswa = 60+ query. Sekarang: 0 query dari Observer.
                        Grade::withoutEvents(function () use ($record, $data) {
                            foreach ($data['grades_data'] as $item) {
                                $scoreToSave = $item['score'] ?? null;

                                // Jika formatif tipe 2 (Poin), kita konversi Toggle Yes/No menjadi Angka Poin di database
                                if ($record->category === 'formatif_poin') {
                                    // Jika dicentang (true), dapat poin penuh sesuai weight. Jika tidak, dapat 0.
                                    $scoreToSave = !empty($item['is_completed']) ? $record->weight : 0;
                                }

                                Grade::updateOrCreate(
                                    [
                                        'assessment_id' => $record->id,
                                        'student_id' => $item['student_id']
                                    ],
                                    [
                                        'score' => $scoreToSave,
                                        'feedback' => $item['feedback'] ?? null,
                                    ]
                                );
                            }
                        });

                        // ✅ Recalculate final grades SEKALI untuk semua siswa setelah bulk save
                        $assignment = $record->teachingAssignment;
                        $semester = $assignment->academicPeriod->semester;
                        $kktp = $assignment->kktp_or_default;

                        foreach ($data['grades_data'] as $item) {
                            $finalScore = $assignment->calculateFinalGrade($item['student_id']);

                            // ✅ REFACTORED: Gunakan GradeRangeResolver terpusat
                            $gradeLabel = $finalScore > 0
                                ? GradeRangeResolver::resolve($assignment, $finalScore)
                                : null;

                            \App\Models\FinalGrade::updateOrCreate(
                                [
                                    'student_id' => $item['student_id'],
                                    'teaching_assignment_id' => $assignment->id,
                                    'semester' => $semester,
                                ],
                                [
                                    'final_score' => $finalScore > 0 ? $finalScore : null,
                                    'grade_label' => $gradeLabel,
                                ]
                            );
                        }

                        Notification::make()->title('Data Nilai Berhasil Disimpan')->success()->send();
                    }),

                // --- TOMBOL INPUT REMEDIAL ---
                Tables\Actions\Action::make('input_remedial')
                    ->label('Input Remedial')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn () => !$this->isPeriodLocked())
                    ->modalWidth('4xl')
                    ->modalHeading(fn (Assessment $record) => 'Input Nilai Remedial: ' . $record->name)
                    ->modalDescription('Masukkan nilai remedial untuk siswa yang belum tuntas (di bawah KKTP). Nilai asli akan disimpan secara otomatis.')
                    ->modalSubmitActionLabel('Simpan Nilai Remedial')
                    ->form(function (Assessment $record) {
                        return [
                            Forms\Components\Repeater::make('remedial_data')
                                ->label('Siswa di Bawah KKTP')
                                ->schema([
                                    Forms\Components\TextInput::make('student_name')
                                        ->label('Nama Siswa')
                                        ->disabled()
                                        ->dehydrated(false),
                                    Forms\Components\Hidden::make('student_id'),
                                    Forms\Components\Hidden::make('grade_id'),
                                    Forms\Components\TextInput::make('current_score')
                                        ->label('Nilai Saat Ini')
                                        ->disabled()
                                        ->dehydrated(false),
                                    Forms\Components\TextInput::make('remedial_score')
                                        ->label('Nilai Remedial (0-100)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->required(),
                                ])
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columns(4)
                                ->columnSpanFull(),
                        ];
                    })
                    ->mountUsing(function (Forms\Form $form, Assessment $record) {
                        $assignment = $record->teachingAssignment;
                        $kktp = $assignment->kktp_or_default;

                        // Ambil semua nilai untuk asesmen ini yang di bawah KKTP
                        // PENTING: whereNotNull mencegah null scores dievaluasi sebagai true
                        $belowKktpGrades = Grade::where('assessment_id', $record->id)
                            ->whereNotNull('score')
                            ->where('score', '<', $kktp)
                            ->with('student')
                            ->get();

                        if ($belowKktpGrades->isEmpty()) {
                            Notification::make()
                                ->title('Semua Siswa Sudah Tuntas')
                                ->body('Tidak ada siswa dengan nilai di bawah KKTP (' . $kktp . ').')
                                ->info()
                                ->send();
                        }

                        $form->fill([
                            'remedial_data' => $belowKktpGrades->map(fn ($grade) => [
                                'student_id' => $grade->student_id,
                                'grade_id' => $grade->id,
                                'student_name' => $grade->student?->name ?? 'N/A',
                                'current_score' => $grade->score,
                                'remedial_score' => null,
                            ])->values()->toArray(),
                        ]);
                    })
                    ->action(function (Assessment $record, array $data) {
                        // Guard defense-in-depth: cegah eksekusi jika periode terkunci
                        if ($this->isPeriodLocked()) {
                            Notification::make()->title('Periode Terkunci')->body('Tidak dapat mengubah nilai pada periode yang sudah dibekukan.')->danger()->send();
                            return;
                        }

                        $count = 0;
                        $updatedStudentIds = [];

                        // 1. MATIKAN Observer — cegah N+1
                        Grade::withoutEvents(function () use ($data, &$count, &$updatedStudentIds) {
                            foreach ($data['remedial_data'] as $item) {
                                if ($item['remedial_score'] === null) continue;

                                $grade = Grade::find($item['grade_id']);
                                if (!$grade) continue;

                                // Simpan nilai asli hanya pada percobaan remedial pertama.
                                // Gabungkan increment remedial_attempts untuk mencegah observer trigger kedua.
                                $grade->update([
                                    'original_score' => $grade->original_score ?? $grade->score, // Jangan timpa jika sudah ada
                                    'score' => (int) $item['remedial_score'],
                                    'remedial_attempts' => $grade->remedial_attempts + 1,
                                ]);
                                $updatedStudentIds[] = $grade->student_id;
                                $count++;
                            }
                        });

                        // 2. RECALCULATE manual SEKALI setelah semua save selesai
                        $assignment = $record->teachingAssignment;
                        $semester = $assignment->academicPeriod->semester;

                        foreach ($updatedStudentIds as $studentId) {
                            $finalScore = $assignment->calculateFinalGrade($studentId);
                            $gradeLabel = $finalScore > 0
                                ? GradeRangeResolver::resolve($assignment, $finalScore) : null;

                            \App\Models\FinalGrade::updateOrCreate(
                                [
                                    'student_id' => $studentId,
                                    'teaching_assignment_id' => $assignment->id,
                                    'semester' => $semester,
                                ],
                                [
                                    'final_score' => $finalScore > 0 ? $finalScore : null,
                                    'grade_label' => $gradeLabel,
                                ]
                            );
                        }

                        Notification::make()
                            ->title('Nilai Remedial Berhasil Disimpan')
                            ->body("{$count} siswa telah diperbarui. Nilai akhir rapor sudah dihitung ulang secara otomatis.")
                            ->success()
                            ->send();
                    }),

                // --- TOMBOL KALIBRASI REMEDIAL (Anti-Auto-Booster) ---
                // Menaikkan semua nilai di bawah KKTP ke ambang batas KKTP secara otomatis.
                // Nilai asli disimpan untuk audit trail. Berbeda dengan input_remedial
                // yang membutuhkan input manual per siswa.
                Tables\Actions\Action::make('calibrate_remedial')
                    ->label('Kalibrasi Remedial')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Kalibrasi Remedial')
                    ->modalDescription(function (Assessment $record) {
                        $kktp = $record->teachingAssignment->kktp_or_default;
                        $belowCount = Grade::where('assessment_id', $record->id)
                            ->whereNotNull('score')
                            ->where('score', '<', $kktp)
                            ->count();

                        return "Tindakan ini akan menaikkan nilai {$belowCount} siswa ke ambang batas KKTP ({$kktp}). Nilai asli akan disimpan untuk keperluan audit. Apakah Anda yakin?";
                    })
                    ->visible(function (Assessment $record) {
                        // Sembunyikan jika periode terkunci
                        if ($this->isPeriodLocked()) {
                            return false;
                        }

                        $kktp = $record->teachingAssignment->kktp_or_default;

                        // Hanya tampilkan jika ada siswa di bawah KKTP
                        return Grade::where('assessment_id', $record->id)
                            ->whereNotNull('score')
                            ->where('score', '<', $kktp)
                            ->exists();
                    })
                    ->action(function (Assessment $record) {
                        // Guard defense-in-depth: cegah eksekusi jika periode terkunci
                        if ($this->isPeriodLocked()) {
                            Notification::make()->title('Periode Terkunci')->body('Tidak dapat mengubah nilai pada periode yang sudah dibekukan.')->danger()->send();
                            return;
                        }

                        $kktp = $record->teachingAssignment->kktp_or_default;

                        $belowKktpGrades = Grade::where('assessment_id', $record->id)
                            ->whereNotNull('score')
                            ->where('score', '<', $kktp)
                            ->get();

                        if ($belowKktpGrades->isEmpty()) {
                            Notification::make()
                                ->title('Tidak Ada Perubahan')
                                ->body('Semua siswa sudah tuntas.')
                                ->info()
                                ->send();
                            return;
                        }

                        $count = 0;

                        // SATU TRANSAKSI untuk SEMUA: grade update + final grade recalculation
                        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $belowKktpGrades, $kktp, &$count) {
                            // 1. MATIKAN Observer — cegah double fire
                            Grade::withoutEvents(function () use ($belowKktpGrades, $kktp, &$count) {
                                foreach ($belowKktpGrades as $grade) {
                                    // Audit Trail: Simpan nilai asli HANYA pada kalibrasi pertama
                                    if ($grade->original_score === null) {
                                        $grade->original_score = $grade->score;
                                    }

                                    // Kalibrasi: Paksa nilai ke KKTP
                                    $grade->score = $kktp;

                                    // Track: Increment percobaan remedial
                                    $grade->remedial_attempts = ($grade->remedial_attempts ?? 0) + 1;

                                    $grade->save();
                                    $count++;
                                }
                            });

                            // 2. RECALCULATE di DALAM transaksi
                            $assignment = $record->teachingAssignment;
                            $semester = $assignment->academicPeriod->semester;

                            foreach ($belowKktpGrades as $grade) {
                                $finalScore = $assignment->calculateFinalGrade($grade->student_id);
                                $gradeLabel = $finalScore > 0
                                    ? GradeRangeResolver::resolve($assignment, $finalScore)
                                    : null;

                                \App\Models\FinalGrade::updateOrCreate(
                                    [
                                        'student_id' => $grade->student_id,
                                        'teaching_assignment_id' => $assignment->id,
                                        'semester' => $semester,
                                    ],
                                    [
                                        'final_score' => $finalScore > 0 ? $finalScore : null,
                                        'grade_label' => $gradeLabel,
                                    ]
                                );
                            }
                        });

                        Notification::make()
                            ->title('Kalibrasi berhasil')
                            ->body("Nilai {$count} siswa telah disesuaikan ke KKTP ({$kktp}). Nilai akhir rapor sudah dihitung ulang.")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}