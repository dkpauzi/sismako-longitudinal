<?php
// app/Filament/Resources/SchoolSettingResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolSettingResource\Pages;
use App\Models\SchoolSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SchoolSettingResource extends Resource
{
    protected static ?string $model = SchoolSetting::class;
    protected static ?string $navigationGroup = 'Manajemen Sistem';
    protected static ?string $navigationLabel = 'Pengaturan Sekolah';
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
                    ->schema([
                        Forms\Components\TextInput::make('school_name')
                            ->label('Nama Sekolah')
                            ->required(),

                        Forms\Components\TextInput::make('npsn')
                            ->label('NPSN'),

                        Forms\Components\TextInput::make('principal_name')
                            ->label('Nama Kepala Sekolah'),

                        Forms\Components\TextInput::make('principal_nip')
                            ->label('NIP Kepala Sekolah'),

                        Forms\Components\Textarea::make('address')
                            ->label('Alamat')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telepon')
                            ->tel(),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email(),
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
                        Forms\Components\Toggle::make('show_score_sd')
                            ->label('Tampilkan Nilai Angka di Rapor SD?')
                            ->helperText(
                            'Aktifkan untuk menampilkan kolom Nilai Akhir di rapor SD (Fase A Opsi 2, Fase B-C). ' .
                                'Nonaktifkan untuk format naratif murni (Fase A Opsi 1).'
                            )
                            ->default(true)
                            ->onColor('success'),
                    ]),
                    
                    
            ]);
            
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('school_name')
                    ->label('Nama Sekolah')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('principal_name')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchoolSettings::route('/'),
            'create' => Pages\CreateSchoolSetting::route('/create'),
            'edit' => Pages\EditSchoolSetting::route('/{record}/edit'),
        ];
    }
}