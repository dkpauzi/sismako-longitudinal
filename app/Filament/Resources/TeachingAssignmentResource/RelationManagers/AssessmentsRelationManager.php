<?php

namespace App\Filament\Resources\TeachingAssignmentResource\RelationManagers;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
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
        return $ownerRecord->subject->is_kokurikuler === false;
    }

    /**
     * Helper: Hitung total bobot yang sudah ada di DB (Hanya untuk Sumatif)
     * Formatif sama sekali tidak dihitung dalam kuota 100%.
     */
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
                                ->where('teacher_id', $assignment->teacher_id)
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
                        Assessment::where('teaching_assignment_id', $livewire->getOwnerRecord()->id)->where('category', 'formatif_poin')->exists()
                    ),

                Tables\Columns\TextColumn::make('date')->date('d M Y'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Buat Rencana Nilai')
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
                Tables\Actions\DeleteAction::make(),

                // --- TOMBOL INPUT NILAI MASSAL (BERUBAH DINAMIS) ---
                Tables\Actions\Action::make('input_grades')
                    ->label('Input Nilai')
                    ->icon('heroicon-o-pencil-square')
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
                        Notification::make()->title('Data Nilai Berhasil Disimpan')->success()->send();
                    }),
            ]);
    }
}