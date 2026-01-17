<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassroomResource\Pages;
use App\Filament\Resources\ClassroomResource\RelationManagers;
use App\Models\Classroom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Kelas')
                    ->placeholder('Contoh: 7A')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('grade_level')
                    ->label('Tingkat Pendidikan')
                    ->options([
                        // Jenjang PAUD
                        0 => 'PAUD / TK (Nol Besar)',

                        // Jenjang SD
                        1 => 'Kelas 1 (SD)',
                        2 => 'Kelas 2 (SD)',
                        3 => 'Kelas 3 (SD)',
                        4 => 'Kelas 4 (SD)',
                        5 => 'Kelas 5 (SD)',
                        6 => 'Kelas 6 (SD)',

                        // Jenjang SMP
                        7 => 'Kelas 7 (SMP)',
                        8 => 'Kelas 8 (SMP)',
                        9 => 'Kelas 9 (SMP)',

                        // Jenjang SMA
                        10 => 'Kelas 10 (SMA/SMK)',
                        11 => 'Kelas 11 (SMA/SMK)',
                        12 => 'Kelas 12 (SMA/SMK)',
                    ])
                    ->searchable() // Tambahkan ini agar mudah mencari angkanya
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kelas')
                    ->searchable()
                    ->sortable(), // Agar bisa diurutkan A-Z

                Tables\Columns\TextColumn::make('grade_level')
                    ->label('Tingkat')
                    ->sortable()
                    ->badge() // Biar tampilannya keren ada warnanya
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        '0' => 'PAUD',
                        default => 'Kelas ' . $state,
                    })
                    ->color(fn($record): string => match (true) {
                        $record->grade_level == 0 => 'warning',  // Kuning (PAUD)
                        $record->grade_level <= 6 => 'danger',   // Merah (SD)
                        $record->grade_level <= 9 => 'info',     // Biru (SMP)
                        $record->grade_level <= 12 => 'gray',    // Abu-abu (SMA)
                        default => 'primary',
                    }),
            ])
            ->filters([
                // Nanti kita bisa tambah filter berdasarkan tingkat
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ClassHomeroomsRelationManager::class,
            RelationManagers\StudentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassrooms::route('/'),
            'create' => Pages\CreateClassroom::route('/create'),
            'edit' => Pages\EditClassroom::route('/{record}/edit'),
        ];
    }
}
