<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule; // Tambahan Import agar aman

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    // Judul Tab di Halaman Edit Kelas
    protected static ?string $title = 'Daftar Siswa';

    /**
     * --- LOGIKA KEAMANAN ---
     * Jika user adalah Guru, maka tab ini otomatis menjadi READ ONLY.
     */
    public function isReadOnly(): bool
    {
        return auth()->user()?->hasRole('teacher') ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('student_id')
                    ->label('Siswa')
                    ->options(Student::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    // --- PERBAIKAN DI SINI ---
                    ->unique(
                        table: 'enrollments',
                        column: 'student_id',
                        modifyRuleUsing: fn($rule, $get) => $rule
                            ->where('academic_period_id', $get('academic_period_id'))
                            ->where('classroom_id', $this->getOwnerRecord()->id),
                        ignoreRecord: true
                    )
                    ->validationMessages([
                        'unique' => 'Siswa ini sudah terdaftar di kelas ini pada periode tersebut.',
                    ]),
                // --------------------------

                Forms\Components\Select::make('academic_period_id')
                    ->label('Tahun Ajaran')
                    ->options(AcademicPeriod::where('is_active', true)->pluck('name', 'id'))
                    ->default(fn() => AcademicPeriod::where('is_active', true)->first()?->id)
                    ->required(),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'moved' => 'Pindah',
                        'dropped_out' => 'DO',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('student.nisn')
                    ->label('NISN')
                    ->searchable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Periode')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'moved' => 'warning',
                        'dropped_out' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'moved' => 'Pindah',
                        'dropped_out' => 'DO',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'moved' => 'Pindah',
                        'dropped_out' => 'DO',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Siswa Manual'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            // --- FITUR BULK ACTION (NAIK KELAS) ---
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('move_class')
                        ->label('Proses Kenaikan / Tinggal Kelas')
                        ->icon('heroicon-o-arrow-right-end-on-rectangle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-academic-cap')
                        ->modalHeading('Pindahkan Siswa')
                        ->modalDescription('Pilih Kelas Tujuan dan Tahun Ajaran Baru.')
                        ->form([
                            // Pilih Tahun Baru
                            Forms\Components\Select::make('new_academic_period_id')
                                ->label('Tahun Ajaran Baru')
                                ->options(AcademicPeriod::all()->pluck('name', 'id'))
                                ->default(fn() => AcademicPeriod::where('is_active', true)->first()?->id)
                                ->required(),

                            // Pilih Kelas Tujuan
                            Forms\Components\Select::make('new_classroom_id')
                                ->label('Kelas Tujuan')
                                ->options(Classroom::all()->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Pilih kelas tujuan (bisa naik kelas atau tinggal kelas).'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $successCount = 0;

                            foreach ($records as $enrollment) {
                                // Cek Duplikasi agar tidak error
                                $exists = Enrollment::where('student_id', $enrollment->student_id)
                                    ->where('classroom_id', $data['new_classroom_id'])
                                    ->where('academic_period_id', $data['new_academic_period_id'])
                                    ->exists();

                                if (!$exists) {
                                    Enrollment::create([
                                        'student_id' => $enrollment->student_id,
                                        'classroom_id' => $data['new_classroom_id'],
                                        'academic_period_id' => $data['new_academic_period_id'],
                                        'status' => 'active',
                                    ]);
                                    $successCount++;
                                }
                            }

                            Notification::make()
                                ->title('Berhasil')
                                ->body("$successCount siswa berhasil dipindahkan.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}