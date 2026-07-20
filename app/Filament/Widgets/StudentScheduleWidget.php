<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\HasIndonesianDayFilter;
use App\Models\SubjectSchedule;
use App\Models\AcademicPeriod;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class StudentScheduleWidget extends BaseWidget
{
    use HasIndonesianDayFilter;

    protected static ?string $heading = 'Jadwal Mata Pelajaran';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()?->hasAnyRole(['student', 'guardian']);
    }

    public function table(Table $table): Table
    {
        // Resolve student: siswa → langsung, wali → anak pertama
        $user = auth()->user();
        $student = null;
        $classroomId = 0;
        $activePeriodId = 0;

        if ($user?->hasRole('student')) {
            $student = $user->student;
        } elseif ($user?->hasRole('guardian')) {
            $student = $user->guardianStudents()->first();
        }

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
                    // ✅ EAGER LOADING: Mencegah N+1 query saat render kolom relasi
                    ->with(['teachingAssignment.subject', 'teachingAssignment.teacher', 'teachingAssignment.classroom'])
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

                // getStateUsing + instructorDisplayName(): pembina ekskul eksternal
                // (teacher_id null) tetap tampil (dulu kosong). teacher sudah di-eager-load.
                Tables\Columns\TextColumn::make('instructor')
                    ->label('Guru')
                    ->getStateUsing(fn(SubjectSchedule $record) => $record->teachingAssignment?->instructorDisplayName() ?? '—')
                    ->searchable(query: fn($query, string $search) => $query->whereHas(
                        'teachingAssignment',
                        fn($q) => $q->where('external_instructor_name', 'like', "%{$search}%")
                            ->orWhereHas('teacher', fn($t) => $t->where('name', 'like', "%{$search}%"))
                    )),

                Tables\Columns\TextColumn::make('teachingAssignment.classroom.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                $this->getDayFilter(), // ✅ DRY: Menggunakan trait HasIndonesianDayFilter
            ])
            ->paginated(false)
            ->searchable(false);
    }
}
