<?php

namespace App\Filament\Widgets;

// --- PERBAIKAN: Gunakan Model yang Benar (SubjectSchedule) ---
use App\Models\SubjectSchedule;
// -------------------------------------------------------------

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TeacherScheduleWidget extends BaseWidget
{
    protected static ?string $heading = 'Jadwal Mengajar Saya';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        // Menggunakan ?-> agar aman saat dijalankan di terminal
        return auth()->check() && auth()->user()?->hasRole('teacher');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // --- PERBAIKAN: Gunakan SubjectSchedule::query() ---
                SubjectSchedule::query()
                    ->whereHas('teachingAssignment', function (Builder $query) {
                        // Ambil ID Teacher dengan aman (Default 0 jika null)
                        $teacherId = auth()->user()->teacher?->id ?? 0;
                        $query->where('teacher_id', $teacherId);
                    })
                    // Urutkan Hari (Senin -> Minggu)
                    ->orderByRaw("FIELD(day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
                    ->orderBy('start_time')
            )
            ->columns([
                Tables\Columns\TextColumn::make('day')
                    ->label('Hari')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Senin' => 'info',
                        'Jumat' => 'success',
                        'Sabtu', 'Minggu' => 'danger',
                        default => 'primary',
                    }),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Jam')
                    ->time('H:i')
                    // Menggunakan SubjectSchedule di type hint
                    ->description(fn(SubjectSchedule $record) => $record->end_time ? 's.d. ' . \Carbon\Carbon::parse($record->end_time)->format('H:i') : ''),

                Tables\Columns\TextColumn::make('teachingAssignment.subject.name')
                    ->label('Mata Pelajaran')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('teachingAssignment.classroom.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('room')
                    ->label('Ruangan')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(20)
                    ->color('gray'),
            ])
            ->paginated(false);
    }
}