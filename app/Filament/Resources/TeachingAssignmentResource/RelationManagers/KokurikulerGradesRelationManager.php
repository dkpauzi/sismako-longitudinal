<?php

namespace App\Filament\Resources\TeachingAssignmentResource\RelationManagers;

use App\Models\Enrollment;
use App\Models\KokurikulerGrade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class KokurikulerGradesRelationManager extends RelationManager
{
    protected static string $relationship = 'kokurikulerGrades';
    protected static ?string $title = 'Nilai Akhir Kokurikuler (P5)';
    protected static ?string $icon = 'heroicon-o-document-text';

    /**
     * MUNCULKAN TAB INI HANYA JIKA MAPEL ADALAH KOKURIKULER
     */
    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->subject->is_kokurikuler === true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]); // Kita tidak pakai form default Create/Edit baris per baris
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi Rapor')
                    ->wrap() // Teks panjang akan otomatis turun ke baris baru
                    ->placeholder('Belum ada deskripsi yang diinput.')
                    ->color('gray'),
            ])
            ->headerActions([
                // TOMBOL INPUT MASSAL
                Tables\Actions\Action::make('input_descriptions')
                    ->label('Input / Edit Nilai Siswa')
                    ->icon('heroicon-o-pencil-square')
                    ->modalWidth('4xl')
                    ->form([
                        Forms\Components\Repeater::make('grades_data')
                            ->label('Daftar Siswa & Deskripsi P5')
                            ->schema([
                                Forms\Components\TextInput::make('student_name')
                                    ->label('Nama Siswa')
                                    ->disabled()
                                    ->dehydrated(false) // Jangan kirim field ini ke database
                                    ->columnSpan(1),

                                Forms\Components\Hidden::make('student_id'),

                                Forms\Components\Textarea::make('description')
                                    ->label('Catatan / Deskripsi')
                                    ->placeholder('Misal: Sangat aktif dalam projek kebersihan lingkungan...')
                                    ->rows(2)
                                    ->columnSpan(2),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(3) // 1 kolom nama, 2 kolom textarea
                            ->columnSpanFull()
                    ])
                    ->mountUsing(function (Forms\Form $form, RelationManager $livewire) {
                        $assignment = $livewire->getOwnerRecord();

                        // 1. Ambil siswa yang terdaftar aktif di kelas dan tahun ajaran tersebut
                        $students = Enrollment::where('classroom_id', $assignment->classroom_id)
                            ->where('academic_period_id', $assignment->academic_period_id)
                            ->where('status', 'active')
                            ->with('student')
                            ->get()
                            ->sortBy('student.name');

                        // 2. Ambil nilai deskripsi yang sudah ada di database
                        $existingGrades = KokurikulerGrade::where('teaching_assignment_id', $assignment->id)
                            ->pluck('description', 'student_id');

                        // 3. Gabungkan data siswa dengan nilai lamanya (jika ada) ke dalam repeater
                        $form->fill([
                            'grades_data' => $students->map(fn($s) => [
                                'student_id' => $s->student_id,
                                'student_name' => $s->student->name,
                                'description' => $existingGrades[$s->student_id] ?? null
                            ])->values()->toArray()
                        ]);
                    })
                    ->action(function (array $data, RelationManager $livewire) {
                        $assignment = $livewire->getOwnerRecord();

                        foreach ($data['grades_data'] as $item) {
                            // Simpan jika kolom deskripsi diisi atau sengaja dikosongkan (update)
                            if (array_key_exists('description', $item)) {
                                KokurikulerGrade::updateOrCreate(
                                    [
                                        'teaching_assignment_id' => $assignment->id,
                                        'student_id' => $item['student_id']
                                    ],
                                    [
                                        'description' => $item['description']
                                    ]
                                );
                            }
                        }
                        Notification::make()->title('Data Kokurikuler Berhasil Disimpan')->success()->send();
                    }),
            ])
            ->actions([
                // Kosongkan action baris per baris agar guru terbiasa pakai tombol Massal di atas
            ]);
    }
}