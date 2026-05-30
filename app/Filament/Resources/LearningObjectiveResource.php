<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LearningObjectiveResource\Pages;
use App\Models\LearningObjective;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LearningObjectiveResource extends Resource
{
    protected static ?string $model = LearningObjective::class;

    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Tujuan Pembelajaran (TP)';
    protected static ?int $navigationSort = 3; // Di bawah Kelas Ajar Saya
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    /**
     * --- FILTER PENTING ---
     * Guru hanya boleh melihat dan mengedit TP miliknya sendiri.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check() && auth()->user()->hasRole('teacher')) {
            $subjectIds = \App\Models\TeachingAssignment::where('teacher_id', auth()->user()->teacher?->id)
                ->pluck('subject_id');
            $query->whereIn('subject_id', $subjectIds);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Tujuan Pembelajaran')
                    ->description('TP ini akan menjadi dasar deskripsi otomatis di Rapor.')
                    ->schema([
                        // 1. Teacher ID (Hidden & Otomatis)
                        Forms\Components\Hidden::make('teacher_id')
                            ->default(fn() => auth()->user()->hasRole('teacher') ? auth()->user()->teacher?->id : null),

                        // 2. Tahun Ajaran (Otomatis Aktif & Readonly buat Guru)
                        Forms\Components\Select::make('academic_period_id')
                            ->label('Tahun Ajaran')
                            ->options(fn () => \App\Models\AcademicPeriod::query()
                                ->orderByDesc('start_year')
                                ->orderByDesc('semester')
                                ->get()
                                ->mapWithKeys(function (\App\Models\AcademicPeriod $period) {
                                    $semesterLabel = $period->semester === 'odd' ? 'Ganjil' : 'Genap';

                                    return [
                                        $period->id => "{$period->start_year}/{$period->end_year} {$semesterLabel}",
                                    ];
                                })
                                ->all())
                            ->default(fn() => \App\Models\AcademicPeriod::where('is_active', true)->first()?->id)
                            ->disabled(fn() => auth()->user()->hasRole('teacher')) // Guru tidak perlu ubah tahun
                            ->dehydrated() // Tetap kirim data meski disabled
                            ->required(),

                        // 3. Mata Pelajaran (YANG KITA PERBAIKI)
                        Forms\Components\Select::make('subject_id')
                            ->label('Mata Pelajaran')
                            ->relationship('subject', 'name', modifyQueryUsing: function (Builder $query) {
                                // JIKA YANG LOGIN GURU:
                                if (auth()->user()->hasRole('teacher')) {
                                    $teacherId = auth()->user()->teacher?->id;

                                    // Cari ID Mapel yang diajar oleh Guru ini saja
                                    $assignedSubjectIds = \App\Models\TeachingAssignment::where('teacher_id', $teacherId)
                                        ->pluck('subject_id')
                                        ->toArray();

                                    // Filter Query: Hanya tampilkan mapel miliknya
                                    $query->whereIn('id', $assignedSubjectIds);
                                }
                                // Jika Admin, tampilkan semua (tidak ada filter)
                            })
                            // Fitur Tambahan: Jika cuma punya 1 mapel, otomatis terpilih!
                            ->default(function () {
                                if (auth()->user()->hasRole('teacher')) {
                                    $teacherId = auth()->user()->teacher?->id;
                                    $subjects = \App\Models\TeachingAssignment::where('teacher_id', $teacherId)
                                        ->pluck('subject_id')
                                        ->unique();

                                    // Jika cuma ngajar 1 mapel (Misal: Pancasila doang), langsung pilih.
                                    if ($subjects->count() === 1) {
                                        return $subjects->first();
                                    }
                                }
                                return null;
                            })
                            ->selectablePlaceholder(false) // Paksa pilih salah satu
                            ->searchable()
                            ->preload()
                            ->required(),

                        // 4. Fase
                        Forms\Components\Select::make('phase')
                            ->label('Fase Kurikulum')
                            ->options([
                                'D' => 'Fase D (SMP Kelas 7-9)',
                            ])
                            ->default('D')
                            ->required(),

                        // 5. Tingkat Kelas
                        Forms\Components\Select::make('grade_level')
                            ->label('Untuk Tingkat Kelas')
                            ->options([
                                7 => 'Kelas 7',
                                8 => 'Kelas 8',
                                9 => 'Kelas 9',
                            ])
                            ->required(),

                        // 6. Kode TP
                        Forms\Components\TextInput::make('code')
                            ->label('Kode TP')
                            ->placeholder('Misal: TP.7.1')
                            ->required()
                            ->maxLength(10),

                        // 7. Deskripsi Lengkap
                        Forms\Components\Textarea::make('content')
                            ->label('Deskripsi Lengkap')
                            ->placeholder('Contoh: Peserta didik mampu menganalisis sejarah perumusan Pancasila.')
                            ->rows(3)
                            ->columnSpanFull()
                            ->required(),

                        // 8. Ringkasan (Atribut)
                        Forms\Components\TextInput::make('attribute')
                            ->label('Ringkasan Kompetensi (Untuk Rapor)')
                            ->helperText('Lanjutan kalimat: "Ananda mampu...". Contoh: menganalisis sejarah perumusan Pancasila')
                            ->placeholder('Contoh: menganalisis sejarah perumusan Pancasila')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->sortable()
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Mapel')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('grade_level')
                    ->label('Kelas')
                    ->alignCenter()
                    ->badge(),

                Tables\Columns\TextColumn::make('content')
                    ->label('Deskripsi TP')
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('attribute')
                    ->label('Ringkasan (Rapor)')
                    ->color('gray')
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject_id')
                    ->label('Mapel')
                    ->relationship('subject', 'name'),

                Tables\Filters\SelectFilter::make('grade_level')
                    ->options([7 => 'Kelas 7', 8 => 'Kelas 8', 9 => 'Kelas 9']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLearningObjectives::route('/'),
            'create' => Pages\CreateLearningObjective::route('/create'),
            'edit' => Pages\EditLearningObjective::route('/{record}/edit'),
        ];
    }
}