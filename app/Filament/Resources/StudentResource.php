<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Biodata Siswa')
                    ->schema([
                        Forms\Components\TextInput::make('nisn')
                            ->label('NISN')
                            ->numeric() // Pastikan cuma angka
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('gender')
                            ->label('Jenis Kelamin')
                            ->options([
                                'L' => 'Laki-laki',
                                'P' => 'Perempuan',
                            ])
                            ->required(),

                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Tanggal Lahir'),

                        Forms\Components\Select::make('status')
                            ->label('Status Siswa')
                            ->options([
                                'active' => 'Aktif',
                                'graduated' => 'Lulus',
                                'moved' => 'Pindah Sekolah',
                                'dropped_out' => 'Putus Sekolah',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Domisili')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('gender')
                    ->label('L/P')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'L' => 'info',    // Biru untuk Laki-laki
                        'P' => 'danger',  // Merah/Pink untuk Perempuan
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'graduated' => 'primary',
                        'moved', 'dropped_out' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'graduated' => 'Lulus',
                        'moved' => 'Pindah',
                        'dropped_out' => 'DO',
                        default => $state,
                    }),
            ])
            ->filters([
                // Filter cepat untuk cari siswa aktif saja
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'graduated' => 'Lulus',
                        'moved' => 'Pindah',
                    ]),
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
            RelationManagers\EnrollmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
