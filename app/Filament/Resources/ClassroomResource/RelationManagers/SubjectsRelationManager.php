<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjectSchedules'; // Sesuai nama fungsi di Model Classroom

    protected static ?string $title = 'Jadwal Mapel';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('academic_period_id')
                    ->label('Tahun Ajaran')
                    ->options(\App\Models\AcademicPeriod::where('is_active', true)->pluck('name', 'id'))
                    ->default(fn() => \App\Models\AcademicPeriod::where('is_active', true)->first()?->id)
                    ->required(),

                Forms\Components\Select::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->options(\App\Models\Subject::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('teacher_id')
                    ->label('Guru Pengampu')
                    ->options(\App\Models\Teacher::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('day')
                    ->label('Hari')
                    ->options([
                        'Senin' => 'Senin',
                        'Selasa' => 'Selasa',
                        'Rabu' => 'Rabu',
                        'Kamis' => 'Kamis',
                        'Jumat' => 'Jumat',
                        'Sabtu' => 'Sabtu',
                    ])
                    ->required(),
                Forms\Components\TimePicker::make('start_time')
                    ->label('Jam Mulai')
                    ->seconds(false) // Sembunyikan detik agar rapi
                    ->required(),

                Forms\Components\TimePicker::make('end_time')
                    ->label('Jam Selesai')
                    ->seconds(false)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject.name')
            ->columns([
                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Mapel')
                    ->sortable(),

                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Guru')
                    ->sortable(),

                Tables\Columns\TextColumn::make('day')
                    ->label('Hari')
                    ->badge()
                    ->color('info'), // Warna biru muda

                // Menggabungkan Jam Mulai - Selesai dalam satu kolom
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Waktu')
                    ->formatStateUsing(
                        fn($record) =>
                        \Carbon\Carbon::parse($record->start_time)->format('H:i') . ' - ' .
                        \Carbon\Carbon::parse($record->end_time)->format('H:i')
                    ),

                Tables\Columns\TextColumn::make('room')
                    ->label('Ruangan')
                    ->placeholder('-'), // Strip jika kosong
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Mapel'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}