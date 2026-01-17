<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClassHomeroomsRelationManager extends RelationManager
{
    protected static string $relationship = 'classHomerooms';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('academic_period_id')
                    ->label('Tahun Ajaran')
                    ->options(\App\Models\AcademicPeriod::where('is_active', true)->pluck('name', 'id'))
                    ->required(),

                Forms\Components\Select::make('teacher_id')
                    ->label('Guru / Wali Kelas')
                    ->options(\App\Models\Teacher::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Toggle::make('is_current')
                    ->label('Masih Menjabat?')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('teacher.name')
            ->columns([
                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Periode'),

                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Nama Wali Kelas')
                    ->icon('heroicon-m-user'),

                Tables\Columns\IconColumn::make('is_current')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Atur Wali Kelas'),
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
}
