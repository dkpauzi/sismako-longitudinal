<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassroomResource\Pages;
use App\Filament\Resources\ClassroomResource\RelationManagers;
use App\Models\Classroom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Ruang Kelas';
    protected static ?string $modelLabel = 'Kelas';
    protected static ?string $pluralModelLabel = 'Ruang Kelas';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Kelas')
                    ->description('Masukan identitas kelas dan tingkatannya.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Kelas')
                                    ->placeholder('Contoh: 7.1, 8.2')
                                    ->required()
                                    ->maxLength(255)
                                    // Menggunakan Closure (fn) agar aman & lazy load
                                    ->disabled(fn() => auth()->user()?->hasRole('teacher')),

                                Forms\Components\Select::make('grade_level')
                                    ->label('Tingkat Pendidikan')
                                    ->options(self::getGradeOptions())
                                    ->searchable()
                                    ->required()
                                    ->native(false)
                                    ->disabled(fn() => auth()->user()?->hasRole('teacher')),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kelas')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('grade_level')
                    ->label('Tingkat')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        '0' => 'PAUD / TK',
                        default => 'Kelas ' . $state,
                    })
                    ->color(fn($state): string => match (true) {
                        $state == 0 => 'warning',
                        $state <= 6 => 'danger',
                        $state <= 9 => 'info',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('grade_level', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('grade_level')
                    ->label('Filter Tingkat')
                    ->options(self::getGradeOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * --- PERBAIKAN DI SINI ---
     * Menggunakan tanda tanya (?->) agar tidak error saat 'artisan serve'
     */
    public static function getRelations(): array
    {
        $relations = [
            RelationManagers\EnrollmentsRelationManager::class,
        ];

        // Jika user BELUM LOGIN (null) atau user BUKAN guru, tampilkan tab ini.
        // Tanda '?->' mencegah error jika user() bernilai null.
        if (!auth()->user()?->hasRole('teacher')) {
            $relations[] = RelationManagers\ClassHomeroomsRelationManager::class;
        }

        return $relations;
    }

    public static function getEloquentQuery(): Builder
    {
        // ✅ PERBAIKAN N+1: Eager load relasi yang dipakai accessor di tabel.
        // Sebelumnya getCurrentHomeroomTeacher & activeStudentsCount
        // menjalankan query per baris di tabel.
        $query = parent::getEloquentQuery()
            ->with(['classHomerooms' => fn($q) => $q->where('is_current', true)->with('teacher')])
            ->withCount(['enrollments as active_students_count' => fn($q) =>
                $q->where('status', 'active')
                  ->whereHas('academicPeriod', fn($q2) => $q2->where('is_active', true))
            ]);

        // Gunakan auth()->check() untuk memastikan ada user login sebelum cek role
        if (auth()->check() && auth()->user()->hasRole('teacher')) {
            $teacher = auth()->user()->teacher;

            if ($teacher) {
                $query->whereHas('classHomerooms', function ($q) use ($teacher) {
                    $q->where('teacher_id', $teacher->id)
                        ->where('is_current', true);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassrooms::route('/'),
            'create' => Pages\CreateClassroom::route('/create'),
            'edit' => Pages\EditClassroom::route('/{record}/edit'),
            'view' => Pages\ViewClassroom::route('/{record}'), // <-- Tambahkan ini
        ];
    }

    public static function getGradeOptions(): array
    {
        return [
            0 => 'PAUD / TK (Nol Besar)',
            1 => 'Kelas 1 (SD)',
            2 => 'Kelas 2 (SD)',
            3 => 'Kelas 3 (SD)',
            4 => 'Kelas 4 (SD)',
            5 => 'Kelas 5 (SD)',
            6 => 'Kelas 6 (SD)',
            7 => 'Kelas 7 (SMP)',
            8 => 'Kelas 8 (SMP)',
            9 => 'Kelas 9 (SMP)',
            10 => 'Kelas 10 (SMA/SMK)',
            11 => 'Kelas 11 (SMA/SMK)',
            12 => 'Kelas 12 (SMA/SMK)',
        ];
    }
}