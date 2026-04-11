<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    // --- KONFIGURASI NAVIGASI ---
    protected static ?string $navigationGroup = 'Manajemen Sistem';
    protected static ?string $navigationLabel = 'Manajemen Akun';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-users'; // Ikon grup pengguna

    /**
     * --- FORM INPUT DATA (CREATE & EDIT) ---
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),

                // --- TAMBAHAN BARU: INPUT USERNAME ---
                // Digunakan untuk login Siswa (NISN) atau Guru (NIP)
                Forms\Components\TextInput::make('username')
                    ->label('Username (NISN / NIP)')
                    ->required()
                    ->unique(ignoreRecord: true) // Mencegah duplikasi username
                    ->maxLength(255),
                // -------------------------------------

                // --- PEMBARUAN: EMAIL MENJADI OPSIONAL ---
                Forms\Components\TextInput::make('email')
                    ->label('Alamat Email')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->nullable() // Hapus kewajiban (required) agar email boleh kosong
                    ->maxLength(255),
                // -----------------------------------------

                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn(string $context): bool => $context === 'create') // Wajib diisi hanya saat membuat akun baru
                    ->revealable(), // Tombol mata untuk melihat password

                // --- INTEGRASI SPATIE SHIELD ---
                Forms\Components\Select::make('roles')
                    ->label('Role / Hak Akses')
                    ->relationship('roles', 'name') // Mengambil dari tabel roles otomatis
                    ->multiple() // User bisa punya lebih dari 1 role
                    ->preload()
                    ->searchable(),
                // -------------------------------

                Forms\Components\Toggle::make('is_active')
                    ->label('Akun Aktif')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger')
                    ->helperText('Matikan toggle ini untuk memblokir akses login user tanpa menghapus datanya.'),
            ]);
    }

    /**
     * --- TABEL DATA (LIST PENGGUNA) ---
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // --- TAMBAHAN BARU: KOLOM USERNAME DI TABEL ---
                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->badge() // Ditampilkan dalam bentuk kotak label agar rapi
                    ->color('gray'),
                // ----------------------------------------------

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Disembunyikan default agar tabel tidak sesak

                // --- KOLOM ROLE (Integrasi Shield) ---
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'danger',
                        'teacher' => 'warning',
                        'student' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),
                // -------------------------------------

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status Aktif')
                    ->boolean(),
            ])
            ->filters([
                // Filter dropdown untuk menyaring user berdasarkan Role
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Saring berdasarkan Role')
                    ->relationship('roles', 'name'),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}