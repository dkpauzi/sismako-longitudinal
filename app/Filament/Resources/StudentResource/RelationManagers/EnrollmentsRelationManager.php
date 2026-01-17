<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('academic_period_id')
                    ->label('Tahun Ajaran')
                    ->options(\App\Models\AcademicPeriod::where('is_active', true)->pluck('name', 'id')) // Hanya tampilkan periode aktif
                    ->required(),

                Forms\Components\Select::make('classroom_id')
                    ->label('Kelas')
                    ->options(\App\Models\Classroom::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'promoted' => 'Naik Kelas',
                        'retained' => 'Tinggal Kelas',
                        'transferred' => 'Pindah/Mutasi',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Tahun Ajaran')
                    ->sortable(),

                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'promoted' => 'info',
                        'retained' => 'danger',
                        'transferred' => 'warning',
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Masukkan ke Kelas'), // Ganti label tombol,
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
