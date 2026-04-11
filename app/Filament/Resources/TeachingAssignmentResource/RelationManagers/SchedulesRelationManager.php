<?php

namespace App\Filament\Resources\TeachingAssignmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $title = 'Jadwal Mata Pelajaran'; // Judul Tab

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Waktu & Tempat')
                    ->schema([
                        // 1. Pilih Hari
                        Forms\Components\Select::make('day')
                            ->label('Hari')
                            ->options([
                                'Senin' => 'Senin',
                                'Selasa' => 'Selasa',
                                'Rabu' => 'Rabu',
                                'Kamis' => 'Kamis',
                                'Jumat' => 'Jumat',
                                'Sabtu' => 'Sabtu',
                                'Minggu' => 'Minggu', // Jika ada ekskul minggu
                            ])
                            ->required()
                            ->native(false),

                        // 2. Jam Mulai & Selesai (Samping-sampingan)
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->label('Jam Mulai')
                                    ->seconds(false) // Hilangkan detik agar simpel
                                    ->required(),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label('Jam Selesai')
                                    ->seconds(false)
                                    ->required()
                                    ->after('start_time'), // Validasi: Selesai harus setelah Mulai
                            ]),

                        // 3. Ruangan & Catatan (Opsional)
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('room')
                                    ->label('Ruangan (Opsional)')
                                    ->placeholder('Contoh: Lab Komputer, Aula')
                                    ->maxLength(50),

                                Forms\Components\TextInput::make('note')
                                    ->label('Catatan (Opsional)')
                                    ->placeholder('Contoh: Pakai Baju Olahraga')
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('day')
            ->columns([
                Tables\Columns\TextColumn::make('day')
                    ->label('Hari')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Senin' => 'info',
                        'Jumat' => 'success',
                        'Minggu' => 'danger',
                        default => 'primary',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Mulai')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Selesai')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('room')
                    ->label('Ruangan')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(30)
                    ->color('gray'),
            ])
            ->defaultSort('day') // Bisa disesuaikan logikanya nanti
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Jadwal'),
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