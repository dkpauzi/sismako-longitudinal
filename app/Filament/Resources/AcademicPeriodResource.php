<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AcademicPeriodResource\Pages;
use App\Models\AcademicPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Helper untuk Editor agar mengenali tipe data Carbon
 * * @property int $id
 * @property int $start_year
 * @property int $end_year
 * @property string $semester
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_active
 * @property-read string $name
 */

class AcademicPeriodResource extends Resource
{
    protected static ?string $model = AcademicPeriod::class;

    protected static ?string $navigationGroup = 'Manajemen Sistem';
    protected static ?string $navigationLabel = 'Tahun Ajaran';
    protected static ?string $modelLabel = 'Tahun Ajaran';
    protected static ?string $pluralModelLabel = 'Tahun Ajaran';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Periode')
                    ->description('Atur tahun ajaran akademik sekolah.')
                    ->schema([
                        // INPUT TAHUN & SEMESTER (DIPISAH)
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('start_year')
                                    ->label('Tahun Mulai')
                                    ->numeric()
                                    ->default(date('Y'))
                                    ->minValue(2000)
                                    ->maxValue(2100)
                                    ->required()
                                    ->live(onBlur: true)
                                    // Auto-fill Tahun Selesai (+1 dari Tahun Mulai)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $set('end_year', (int) $state + 1);
                                        }
                                    }),

                                Forms\Components\TextInput::make('end_year')
                                    ->label('Tahun Selesai')
                                    ->numeric()
                                    ->default(date('Y') + 1)
                                    ->required(),

                                Forms\Components\Select::make('semester')
                                    ->label('Semester')
                                    ->options([
                                        'odd' => 'Ganjil',
                                        'even' => 'Genap',
                                    ])
                                    ->default('odd')
                                    ->required(),
                            ]),

                        // TANGGAL PELAKSANAAN
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Tanggal Mulai KBM')
                                    ->default(now())
                                    ->required(),

                                Forms\Components\DatePicker::make('end_date')
                                    ->label('Tanggal Selesai KBM')
                                    ->default(now()->addMonths(6))
                                    ->required(),
                            ]),

                        // SWITCH AKTIF
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktifkan Periode Ini?')
                            ->helperText('Jika diaktifkan, periode aktif sebelumnya otomatis akan non-aktif.')
                            ->onColor('success')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Kolom Name (Virtual Accessor)
                Tables\Columns\TextColumn::make('name')
                    ->label('Tahun Ajaran')
                    ->getStateUsing(fn(AcademicPeriod $record) => $record->name) // Panggil Accessor
                    ->description(fn(AcademicPeriod $record) => "Mulai: {$record->start_date->format('d M Y')}")
                    ->weight('bold')
                    ->sortable(['start_year', 'semester']) // Sort berdasarkan kolom asli
                    ->searchable(['start_year', 'end_year']),

                Tables\Columns\TextColumn::make('semester')
                    ->label('Sem')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'odd' => 'info',
                        'even' => 'warning',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'odd' => 'Ganjil',
                        'even' => 'Genap',
                    }),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('Berakhir')
                    ->date('d M Y')
                    ->sortable(),

                // Status Badge
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(bool $state): string => $state ? 'AKTIF' : 'Non-Aktif')
                    ->color(fn(bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('start_year', 'desc') // Urutkan dari tahun terbaru
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicPeriods::route('/'),
            'create' => Pages\CreateAcademicPeriod::route('/create'),
            'edit' => Pages\EditAcademicPeriod::route('/{record}/edit'),
        ];
    }
}