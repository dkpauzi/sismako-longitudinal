<?php
// app/Filament/Resources/RaporResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\RaporResource\Pages;
use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RaporResource extends Resource
{
    // Resource ini tidak terikat ke satu model tunggal.
    // Kita gunakan ClassHomeroom sebagai anchor karena
    // Wali Kelas hanya melihat kelas yang dipegangnya.
    protected static ?string $model = ClassHomeroom::class;

    protected static ?string $navigationLabel = 'Rekap Rapor';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    public static function getNavigationGroup(): ?string
    {
        return auth()->check() && auth()->user()->hasRole('teacher') ? 'Saya sebagai Wali Kelas' : 'Akademik';
    }

    // Hanya Wali Kelas dan Admin yang bisa mengakses
    public static function shouldRegisterNavigation(): bool
    {
        if (!auth()->check())
            return false;

        $user = auth()->user();

        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return true;
        }

        // Guru hanya melihat menu ini jika dia adalah Wali Kelas
        if ($user->hasRole('teacher')) {
            return ClassHomeroom::where('teacher_id', $user->teacher?->id)
                ->where('is_current', true)
                ->exists();
        }

        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['classroom', 'academicPeriod', 'teacher'])
            ->whereHas('academicPeriod', fn($q) => $q->where('is_active', true));

        // Guru hanya melihat kelas yang dia pegang sebagai Wali Kelas
        if (auth()->check() && auth()->user()->hasRole('teacher')) {
            $query->where('teacher_id', auth()->user()->teacher?->id)
                ->where('is_current', true);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Tahun Ajaran')
                    ->sortable(),

                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Wali Kelas')
                    ->sortable()
                    // Sembunyikan kolom ini jika yang login adalah Wali Kelas itu sendiri
                    ->visible(fn() => !auth()->user()->hasRole('teacher')),

                // Jumlah siswa aktif di kelas ini
                Tables\Columns\TextColumn::make('classroom.active_students_count')
                    ->label('Jumlah Siswa')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(
                        fn($record) =>
                        Enrollment::where('classroom_id', $record->classroom_id)
                            ->where('academic_period_id', $record->academic_period_id)
                            ->where('status', 'active')
                            ->count() . ' siswa'
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('lihat_rapor')
                    ->label('Lihat Rekap')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(
                        fn(ClassHomeroom $record): string =>
                        static::getUrl('view', ['record' => $record])
                    ),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRapors::route('/'),
            'view' => Pages\ViewRapor::route('/{record}'),
        ];
    }
}