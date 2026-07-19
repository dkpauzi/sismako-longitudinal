<?php
// app/Filament/Resources/LessonJournalResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\LessonJournalResource\Pages;
use App\Models\LessonJournal;
use App\Models\TeachingAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\AcademicPeriod;

class LessonJournalResource extends Resource
{
    protected static ?string $model = LessonJournal::class;

    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Jurnal KBM';
    protected static ?string $modelLabel = 'Jurnal KBM';
    protected static ?string $pluralModelLabel = 'Jurnal KBM';
    protected static ?int $navigationSort = 7;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin', 'teacher']) ?? false;
    }

    /**
     * Blokir pembuatan jurnal baru jika tidak ada periode aktif.
     */
    public static function canCreate(): bool
    {
        // Admin bypass
        if (auth()->user()?->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        return AcademicPeriod::where('is_active', true)->exists();
    }

    /**
     * Blokir edit jurnal dari periode yang sudah dibekukan.
     * Ini memblokir baik tombol Edit maupun akses URL /edit langsung.
     */
    public static function canEdit(Model $record): bool
    {
        // Admin bypass
        if (auth()->user()?->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        // Blokir jika jurnal terkunci
        if ($record->status === 'locked') {
            return false;
        }

        // Blokir jika periode tidak aktif
        return (bool) $record->teachingAssignment?->academicPeriod?->is_active;
    }

    /**
     * Guru hanya melihat jurnal dari kelas yang diajarnya sendiri.
     * Admin melihat semua jurnal.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['teachingAssignment.subject', 'teachingAssignment.classroom', 'teachingAssignment.academicPeriod']);

        if (auth()->check() && auth()->user()->hasRole('teacher')) {
            $teacher = auth()->user()->teacher;

            if ($teacher) {
                $query->whereHas('teachingAssignment', function (Builder $q) use ($teacher) {
                    $q->where('teacher_id', $teacher->id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pertemuan')
                    ->schema([

                        // Guru memilih dari SK Mengajar miliknya sendiri
                        Forms\Components\Select::make('teaching_assignment_id')
                            ->label('Kelas & Mata Pelajaran')
                            ->options(function () {
                                $query = TeachingAssignment::with(['subject', 'classroom', 'academicPeriod'])
                                    ->whereHas('academicPeriod', fn($q) => $q->where('is_active', true));

                                // Filter: Guru hanya melihat kelasnya
                                if (auth()->user()->hasRole('teacher')) {
                                    $query->where('teacher_id', auth()->user()->teacher?->id);
                                }

                                return $query->get()->mapWithKeys(
                                    fn($ta) =>
                                    [$ta->id => "{$ta->subject->name} — {$ta->classroom->name}"]
                                );
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (!$state)
                                    return;

                                // Auto-isi nomor pertemuan berikutnya
                                $lastMeeting = LessonJournal::where('teaching_assignment_id', $state)
                                    ->max('meeting_number') ?? 0;

                                $set('meeting_number', $lastMeeting + 1);
                            }),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('date')
                                    ->label('Tanggal Pertemuan')
                                    ->default(now())
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d F Y'),

                                Forms\Components\TextInput::make('meeting_number')
                                    ->label('Pertemuan Ke-')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->helperText('Otomatis terisi, bisa diubah jika perlu.'),
                            ]),

                        Forms\Components\TextInput::make('topic')
                            ->label('Materi / Topik Hari Ini')
                            ->placeholder('Contoh: Sistem Persamaan Linear Dua Variabel')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan / Refleksi Guru (Opsional)')
                            ->placeholder('Contoh: Siswa antusias, namun perlu remedial untuk soal nomor 3.')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('status')
                            ->label('Status Jurnal')
                            ->options([
                                'draft' => 'Draft — masih bisa diedit',
                                'done' => 'Selesai — pertemuan sudah berlangsung',
                                'locked' => 'Dikunci — tidak bisa diedit lagi',
                            ])
                            ->default('draft')
                            ->required()
                            // Hanya Admin yang bisa mengunci jurnal
                            ->disabled(
                                fn(?LessonJournal $record) =>
                                $record?->status === 'locked' &&
                                !auth()->user()->hasRole('super_admin')
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('meeting_number')
                    ->label('Ke-')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('teachingAssignment.subject.name')
                    ->label('Mata Pelajaran')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('teachingAssignment.classroom.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('topic')
                    ->label('Materi')
                    ->limit(50)
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'done' => 'success',
                        'locked' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'done' => 'Selesai',
                        'locked' => 'Dikunci',
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('teaching_assignment_id')
                    ->label('Kelas & Mapel')
                    ->options(function () {
                        $query = TeachingAssignment::with(['subject', 'classroom'])
                            ->whereHas('academicPeriod', fn($q) => $q->where('is_active', true));

                        if (auth()->user()->hasRole('teacher')) {
                            $query->where('teacher_id', auth()->user()->teacher?->id);
                        }

                        return $query->get()->mapWithKeys(
                            fn($ta) =>
                            [$ta->id => "{$ta->subject->name} — {$ta->classroom->name}"]
                        );
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'done' => 'Selesai',
                        'locked' => 'Dikunci',
                    ]),
            ])
            ->actions([
                // Tombol "Isi Absensi" — membawa guru langsung ke absensi
                // yang terhubung dengan jurnal ini
                Tables\Actions\Action::make('fill_attendance')
                    ->label('Isi Absensi')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('success')
                    ->visible(fn(LessonJournal $record) => $record->status !== 'locked')
                    ->url(
                        fn(LessonJournal $record): string =>
                        TeachingAssignmentResource::getUrl('view', [
                            'record' => $record->teaching_assignment_id,
                        ]) . '#attendances'
                    ),

                Tables\Actions\Action::make('mark_done')
                    ->label('Tandai Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->visible(fn(LessonJournal $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Pertemuan Selesai?')
                    ->modalDescription('Status jurnal akan diubah menjadi "Selesai". Anda masih bisa mengeditnya.')
                    ->action(fn(LessonJournal $record) => $record->update(['status' => 'done'])),

                Tables\Actions\EditAction::make()
                    ->visible(function (LessonJournal $record) {
                        if ($record->status === 'locked') return false;

                        // Cek periode aktif (teacher-only guard)
                        if (auth()->user()->hasAnyRole(['super_admin', 'admin'])) return true;
                        return (bool) $record->teachingAssignment?->academicPeriod?->is_active;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(
                        fn(LessonJournal $record) =>
                        $record->status !== 'locked' &&
                        !auth()->user()->hasRole('teacher')
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => !auth()->user()->hasRole('teacher')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLessonJournals::route('/'),
            'create' => Pages\CreateLessonJournal::route('/create'),
            'edit' => Pages\EditLessonJournal::route('/{record}/edit'),
        ];
    }
}