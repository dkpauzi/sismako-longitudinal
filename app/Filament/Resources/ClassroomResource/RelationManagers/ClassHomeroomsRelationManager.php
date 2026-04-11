<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use App\Models\AcademicPeriod;
use App\Models\Teacher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ClassHomeroomsRelationManager extends RelationManager
{
    protected static string $relationship = 'classHomerooms'; // Sesuai fungsi di Model Classroom

    protected static ?string $title = 'Riwayat Wali Kelas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('teacher_id')
                    ->label('Pilih Guru')
                    ->options(Teacher::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\Select::make('academic_period_id')
                    ->label('Tahun Ajaran')
                    ->options(AcademicPeriod::all()->pluck('name', 'id'))
                    ->default(fn() => AcademicPeriod::where('is_active', true)->first()?->id)
                    ->required(),

                Forms\Components\Toggle::make('is_current')
                    ->label('Set Sebagai Wali Kelas Aktif')
                    ->helperText('Jika diaktifkan, wali kelas lama di tahun ajaran ini akan otomatis non-aktif.')
                    ->default(true)
                    ->onColor('success'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Periode')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Nama Wali Kelas')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('teacher.nip')
                    ->label('NIP')
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_current')
                    ->label('Status Aktif')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tunjuk Wali Kelas'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('academic_period_id', 'desc'); // Yang terbaru paling atas
    }
}