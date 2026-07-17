<?php
// app/Filament/Resources/SchoolSettingResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolSettingResource\Pages;
use App\Models\SchoolProfile;
use App\Models\SchoolSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SchoolSettingResource extends Resource
{
    protected static ?string $model = SchoolSetting::class;
    protected static ?string $navigationGroup = 'Manajemen Sistem';
    protected static ?string $navigationLabel = 'Pengaturan Sekolah';
    protected static ?string $modelLabel = 'Pengaturan';
    protected static ?string $pluralModelLabel = 'Pengaturan Sekolah';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    // Sembunyikan dari menu navigasi jika bukan admin
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin')
            || auth()->user()?->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Sekolah')
                    ->description('Identitas sekolah mengikuti data pada menu "Tentang Kami" (School Profile) sebagai sumber utama.')
                    ->schema([
                        Forms\Components\Placeholder::make('identity_source')
                            ->label('Sumber Data')
                            ->content('Data identitas diambil dari School Profile (Web Sekolah > Tentang Kami).')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('current_school_name')
                            ->label('Nama Sekolah')
                            ->content(fn() => SchoolProfile::query()->first()?->name ?? '-'),

                        Forms\Components\Placeholder::make('current_principal_name')
                            ->label('Nama Kepala Sekolah')
                            ->content(fn() => SchoolProfile::query()->first()?->principal_name ?? '-'),
                    ])->columns(2),

                Forms\Components\Section::make('Pengaturan Akademik')
                    ->description('Nilai default ini akan otomatis digunakan saat SK Mengajar baru dibuat.')
                    ->schema([
                        Forms\Components\TextInput::make('default_kkm')
                            ->label('KKTP Default (Berlaku untuk semua mapel baru)')
                            ->helperText(
                                'Nilai ini menjadi KKTP awal setiap SK Mengajar yang dibuat. ' .
                                'Guru atau Admin bisa mengubahnya per kelas di menu SK Mengajar.'
                            )
                            ->numeric()
                            ->default(75)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('/ 100')
                            ->required(),
                    ]),
                    
                    
            ]);
            
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('schoolProfile.name')
                    ->label('Nama Sekolah')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('schoolProfile.principal_name')
                    ->label('Kepala Sekolah'),

                Tables\Columns\TextColumn::make('default_kkm')
                    ->label('KKTP Default')
                    ->badge()
                    ->color('info')
                    ->suffix('/ 100'),
            ])
            ->paginated(false)
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('schoolProfile');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchoolSettings::route('/'),
            'edit' => Pages\EditSchoolSetting::route('/{record}/edit'),
        ];
    }
}