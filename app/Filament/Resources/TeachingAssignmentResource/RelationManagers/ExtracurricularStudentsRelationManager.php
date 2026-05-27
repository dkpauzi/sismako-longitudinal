<?php

namespace App\Filament\Resources\TeachingAssignmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ExtracurricularStudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'studentSubjectEnrollments';

    protected static ?string $title = 'Siswa Ekstrakurikuler';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->isExtracurricular();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('student_id')
                    ->label('Siswa')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'), // Attach only, shouldn't change student on edit

                Forms\Components\Select::make('predicate')
                    ->label('Predikat')
                    ->options([
                        'Sangat Baik' => 'Sangat Baik',
                        'Baik' => 'Baik',
                        'Cukup' => 'Cukup',
                        'Kurang' => 'Kurang',
                    ])
                    ->required(),

                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi/Narasi')
                    ->rows(3)
                    ->maxLength(65535)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('student.nisn')
                    ->label('NISN')
                    ->searchable(),

                Tables\Columns\TextColumn::make('predicate')
                    ->label('Predikat')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Sangat Baik' => 'success',
                        'Baik' => 'info',
                        'Cukup' => 'warning',
                        'Kurang' => 'danger',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Siswa')
                    ->modalHeading('Tambah Siswa Ekstrakurikuler'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Input Nilai Ekskul')
                    ->modalHeading('Input Nilai Ekstrakurikuler'),
                Tables\Actions\DeleteAction::make()
                    ->label('Hapus Siswa'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
