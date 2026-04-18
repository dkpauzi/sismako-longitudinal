<?php

namespace App\Filament\Widgets;

use App\Models\SubjectSchedule;
use App\Models\AcademicPeriod;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class StudentScheduleWidget extends BaseWidget
{
    protected static ?string $heading = 'Jadwal Mata Pelajaran';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()?->hasRole('student');
    }

    public function table(Table $table): Table
    {
        $student = auth()->user()?->student;
        $classroomId = 0;
        $activePeriodId = 0;

        if ($student) {
            $activePeriod = AcademicPeriod::where('is_active', true)->first();
            if ($activePeriod) {
                $activePeriodId = $activePeriod->id;
                $enrollment = $student->enrollments()
                    ->where('academic_period_id', $activePeriod->id)
                    ->first();
                if ($enrollment) {
                    $classroomId = $enrollment->classroom_id;
                }
            }
        }

        return $table
            ->query(
                SubjectSchedule::query()
                    ->whereHas('teachingAssignment', function (Builder $query) use ($classroomId, $activePeriodId) {
                        $query->where('classroom_id', $classroomId)
                              ->where('academic_period_id', $activePeriodId);
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
                    ->description(fn(SubjectSchedule $record) => $record->end_time ? 's.d. ' . \Carbon\Carbon::parse($record->end_time)->format('H:i') : ''),

                Tables\Columns\TextColumn::make('teachingAssignment.subject.name')
                    ->label('Mata Pelajaran')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('teachingAssignment.teacher.name')
                    ->label('Guru')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('teachingAssignment.classroom.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('success'),
            ])
            ->paginated(false) // Tampilkan semua jadwal
            ->searchable(false); // Widget di dashboard biasanya tidak perlu search kompleks
    }
}
