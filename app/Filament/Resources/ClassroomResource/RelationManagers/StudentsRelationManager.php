<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments'; // Nama fungsi di Model Classroom tadi

    protected static ?string $title = 'Daftar Siswa'; // Judul Tab

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('student_id')
                    ->label('Siswa')
                    ->options(\App\Models\Student::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('academic_period_id')
                    ->label('Tahun Ajaran')
                    ->options(\App\Models\AcademicPeriod::where('is_active', true)->pluck('name', 'id'))
                    ->default(fn() => \App\Models\AcademicPeriod::where('is_active', true)->first()?->id)
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'promoted' => 'Naik Kelas',
                        'retained' => 'Tinggal Kelas',
                        'transferred' => 'Pindah',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('student.name')
            ->columns([
                Tables\Columns\TextColumn::make('student.nisn')
                    ->label('NISN')
                    ->searchable(),

                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Periode'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'promoted' => 'info',
                        'retained' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                // Filter untuk melihat siswa aktif saja
                Tables\Filters\SelectFilter::make('academic_period_id')
                    ->label('Filter Periode')
                    ->relationship('academicPeriod', 'name'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Siswa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}