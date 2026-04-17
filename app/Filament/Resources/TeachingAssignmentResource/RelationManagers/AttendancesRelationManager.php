<?php

namespace App\Filament\Resources\TeachingAssignmentResource\RelationManagers;

use App\Models\Attendance;
use App\Models\Enrollment;
use Filament\Forms;
use Filament\Forms\Form; // <--- WAJIB ADA agar (Form $form) terbaca
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Jurnal Absensi';
    protected static ?string $icon = 'heroicon-o-calendar-days';

    public function getAvailableDates(): array
    {
        $assignment = $this->getOwnerRecord();
        $period = $assignment->academicPeriod;

        if (!$period->start_date || !$period->end_date)
            return [];

        $teachingDays = $assignment->schedules->pluck('day')->toArray();
        if (empty($teachingDays))
            return [];

        $dayMap = [
            'Senin' => 'Monday',
            'Selasa' => 'Tuesday',
            'Rabu' => 'Wednesday',
            'Kamis' => 'Thursday',
            'Jumat' => 'Friday',
            'Sabtu' => 'Saturday',
            'Minggu' => 'Sunday'
        ];

        $targetDays = array_map(fn($d) => $dayMap[$d] ?? null, $teachingDays);

        // ✅ PERBAIKAN: Gunakan CarbonPeriod (lebih bersih dari while loop manual)
        return collect(\Carbon\CarbonPeriod::create($period->start_date, $period->end_date))
            ->filter(fn($date) => in_array($date->format('l'), $targetDays))
            ->mapWithKeys(fn($date) => [
                $date->format('Y-m-d') => $date->translatedFormat('d F Y (l)')
            ])
            ->toArray();
    }

    // --- FORM YANG TADI ERROR ---
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Karena kita sudah 'use' di atas, kita tidak perlu tulis Forms\Components\... lagi
                // Cukup langsung nama komponennya:

                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required(),

                Select::make('student_id')
                    ->label('Siswa')
                    ->relationship('student', 'name')
                    ->disabled(),

                Select::make('status')
                    ->options([
                        'present' => 'Hadir',
                        'permit' => 'Izin',
                        'sick' => 'Sakit',
                        'alpha' => 'Alpha',
                        'holiday' => 'Libur',
                    ])
                    ->required(),

                Textarea::make('note')
                    ->label('Catatan'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Keterangan')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'present' => 'success',
                        'permit' => 'warning',
                        'sick' => 'info',
                        'alpha' => 'danger',
                        'holiday' => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'present' => 'Hadir',
                        'permit' => 'Izin',
                        'sick' => 'Sakit',
                        'alpha' => 'Alpha',
                        'holiday' => 'Libur',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(20),
            ])
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->form([
                        DatePicker::make('filter_date')
                            ->label('Pilih Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['filter_date'],
                            fn(Builder $query, $date): Builder => $query->whereDate('date', $date),
                        );
                    }),

                Tables\Filters\SelectFilter::make('student_id')
                    ->label('Filter Siswa')
                    ->searchable()
                    ->options(function (RelationManager $livewire) {
                        $assignment = $livewire->getOwnerRecord();
                        return Enrollment::query()
                            ->where('classroom_id', $assignment->classroom_id)
                            ->where('academic_period_id', $assignment->academic_period_id)
                            ->with('student')
                            ->get()
                            ->pluck('student.name', 'student.id');
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'present' => 'Hadir',
                        'permit' => 'Izin',
                        'sick' => 'Sakit',
                        'alpha' => 'Alpha',
                        'holiday' => 'Libur',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('take_attendance')
                    ->label('Isi Absensi Kelas')
                    ->icon('heroicon-o-pencil-square')
                    ->modalWidth('4xl')
                    ->form([
                        Select::make('date')
                            ->label('Pilih Tanggal Pertemuan')
                            ->options(fn() => $this->getAvailableDates())
                            ->searchable()
                            ->required()
                            ->helperText('Hanya menampilkan tanggal sesuai jadwal mengajar semester ini.'),

                        Toggle::make('is_holiday')
                            ->label('Hari ini Libur / Tidak ada KBM?')
                            ->live()
                            ->default(false),

                        TextInput::make('holiday_note')
                            ->label('Keterangan Libur')
                            ->visible(fn(Get $get) => $get('is_holiday'))
                            ->required(fn(Get $get) => $get('is_holiday')),

                        Repeater::make('students_attendance')
                            ->label('Daftar Kehadiran')
                            ->visible(fn(Get $get) => !$get('is_holiday'))
                            ->schema([
                                TextInput::make('student_name')
                                    ->label('Nama Siswa')
                                    ->disabled()
                                    ->dehydrated(false),
                                Hidden::make('student_id'),
                                Radio::make('status')
                                    ->label('')
                                    ->options([
                                        'present' => 'Hadir',
                                        'permit' => 'Izin',
                                        'sick' => 'Sakit',
                                        'alpha' => 'Alpha',
                                    ])
                                    ->inline()
                                    ->default('present')
                                    ->required(),
                                TextInput::make('note')->label('Catatan')->placeholder('Ket. Tambahan'),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(3),
                    ])
                    ->mountUsing(function (Forms\Form $form, RelationManager $livewire) {
                        $assignment = $livewire->getOwnerRecord();

                        $students = Enrollment::query()
                            ->where('classroom_id', $assignment->classroom_id)
                            ->where('academic_period_id', $assignment->academic_period_id)
                            ->where('status', 'active')
                            ->with('student')
                            ->get()
                            ->sortBy('student.name')
                            ->map(fn($enrollment) => [
                                'student_id' => $enrollment->student_id,
                                'student_name' => $enrollment->student->name,
                                'status' => 'present',
                            ])
                            ->toArray();

                        $form->fill(['students_attendance' => $students]);
                    })
                    ->action(function (array $data, RelationManager $livewire) {
                        $assignment = $livewire->getOwnerRecord();
                        $date = $data['date'];

                        if ($data['is_holiday']) {
                            $studentIds = Enrollment::query()
                                ->where('classroom_id', $assignment->classroom_id)
                                ->where('academic_period_id', $assignment->academic_period_id)
                                ->where('status', 'active')
                                ->pluck('student_id');

                            foreach ($studentIds as $studentId) {
                                Attendance::updateOrCreate(
                                    ['teaching_assignment_id' => $assignment->id, 'student_id' => $studentId, 'date' => $date],
                                    ['status' => 'holiday', 'note' => $data['holiday_note']]
                                );
                            }
                            Notification::make()->title('Dicatat sebagai Libur')->success()->send();
                            return;
                        }

                        foreach ($data['students_attendance'] as $item) {
                            Attendance::updateOrCreate(
                                ['teaching_assignment_id' => $assignment->id, 'student_id' => $item['student_id'], 'date' => $date],
                                ['status' => $item['status'], 'note' => $item['note'] ?? null]
                            );
                        }
                        Notification::make()->title('Data Absensi Tersimpan')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}