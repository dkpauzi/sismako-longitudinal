<?php
// app/Filament/Resources/StudentSubjectEnrollmentResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentSubjectEnrollmentResource\Pages;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\StudentSubjectEnrollment;
use App\Models\TeachingAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentSubjectEnrollmentResource extends Resource
{
    protected static ?string $model = StudentSubjectEnrollment::class;

    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Mapel Pilihan Siswa (SMA)';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Hanya tampil jika ada kelas SMA (grade_level >= 10)
    public static function shouldRegisterNavigation(): bool
    {
        return Classroom::where('grade_level', '>=', 10)->exists();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pendaftaran Mapel Pilihan')
                    ->schema([
                        Forms\Components\Select::make('student_id')
                            ->label('Siswa')
                            ->options(function () {
                                $activePeriod = AcademicPeriod::where('is_active', true)->first();
                                if (!$activePeriod) return [];

                                return Enrollment::where('academic_period_id', $activePeriod->id)
                                    ->whereHas('classroom', fn($q) => $q->where('grade_level', '>=', 10))
                                    ->with('student')
                                    ->get()
                                    ->pluck('student.name', 'student_id');
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('teaching_assignment_id')
                            ->label('Mata Pelajaran & Kelas')
                            ->options(function () {
                                $activePeriod = AcademicPeriod::where('is_active', true)->first();
                                if (!$activePeriod) return [];

                                return TeachingAssignment::where('academic_period_id', $activePeriod->id)
                                    ->whereHas('classroom', fn($q) => $q->where('grade_level', '>=', 10))
                                    ->whereHas('subject', fn($q) => $q->where('is_kokurikuler', false))
                                    ->with(['subject', 'classroom'])
                                    ->get()
                                    ->mapWithKeys(fn($ta) =>
                                        [$ta->id => "{$ta->subject->name} — {$ta->classroom->name}"]
                                    );
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('note')
                            ->label('Keterangan Peminatan')
                            ->placeholder('Cth: Peminatan IPA, Lintas Minat')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('teachingAssignment.subject.name')
                    ->label('Mata Pelajaran')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('teachingAssignment.classroom.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Peminatan')
                    ->placeholder('-')
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('teaching_assignment_id')
                    ->label('Mata Pelajaran')
                    ->options(function () {
                        $activePeriod = AcademicPeriod::where('is_active', true)->first();
                        if (!$activePeriod) return [];

                        return TeachingAssignment::where('academic_period_id', $activePeriod->id)
                            ->whereHas('classroom', fn($q) => $q->where('grade_level', '>=', 10))
                            ->with(['subject', 'classroom'])
                            ->get()
                            ->mapWithKeys(fn($ta) =>
                                [$ta->id => "{$ta->subject->name} — {$ta->classroom->name}"]
                            );
                    }),
            ])
            ->headerActions([
                // Import massal
                Tables\Actions\ImportAction::make()
                    ->label('Import Mapel Pilihan')
                    ->importer(\App\Filament\Imports\StudentSubjectEnrollmentImporter::class)
                    ->csvDelimiter(';')
                    ->modalHeading('Import Mapel Pilihan Siswa SMA'),

                // Bulk assign — semua siswa satu kelas ke satu mapel
                Tables\Actions\Action::make('bulk_assign')
                    ->label('Daftarkan Satu Kelas')
                    ->icon('heroicon-o-user-group')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('classroom_id')
                            ->label('Kelas')
                            ->options(
                                Classroom::where('grade_level', '>=', 10)
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('teaching_assignment_id')
                            ->label('Mata Pelajaran')
                            ->options(function (Forms\Get $get) {
                                if (!$get('classroom_id')) return [];
                                $activePeriod = AcademicPeriod::where('is_active', true)->first();

                                return TeachingAssignment::where('academic_period_id', $activePeriod?->id)
                                    ->where('classroom_id', $get('classroom_id'))
                                    ->whereHas('subject', fn($q) => $q->where('is_kokurikuler', false))
                                    ->with('subject')
                                    ->get()
                                    ->pluck('subject.name', 'id');
                            })
                            ->required(),

                        Forms\Components\TextInput::make('note')
                            ->label('Keterangan Peminatan')
                            ->nullable(),
                    ])
                    ->action(function (array $data) {
                        $activePeriod = AcademicPeriod::where('is_active', true)->first();

                        $studentIds = Enrollment::where('classroom_id', $data['classroom_id'])
                            ->where('academic_period_id', $activePeriod?->id)
                            ->where('status', 'active')
                            ->pluck('student_id');

                        $count = 0;
                        foreach ($studentIds as $studentId) {
                            StudentSubjectEnrollment::updateOrCreate(
                                [
                                    'student_id'             => $studentId,
                                    'teaching_assignment_id' => $data['teaching_assignment_id'],
                                ],
                                ['note' => $data['note'] ?? null]
                            );
                            $count++;
                        }

                        Notification::make()
                            ->title("{$count} siswa berhasil didaftarkan ke mapel")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStudentSubjectEnrollments::route('/'),
            'create' => Pages\CreateStudentSubjectEnrollment::route('/create'),
            'edit'   => Pages\EditStudentSubjectEnrollment::route('/{record}/edit'),
        ];
    }
}