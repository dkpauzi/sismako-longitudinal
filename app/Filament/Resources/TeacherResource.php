<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherResource\Pages;
use App\Filament\Resources\TeacherResource\RelationManagers;
use App\Models\Teacher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Set;
use App\Models\User;

class TeacherResource extends Resource
{
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Guru'; // Label Menu
    protected static ?int $navigationSort = 3;
    protected static ?string $model = Teacher::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // BAGIAN 1: HUBUNGKAN KE AKUN USER (Penting!)
                Forms\Components\Section::make('Hubungkan Akun')
                    ->description('Pilih akun user yang bisa login sebagai guru ini.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship(
                                'user',
                                'name',
                                modifyQueryUsing: fn(Builder $query, $record) => $query
                                    // 1. Filter Role: Hanya tampilkan yang role-nya 'teacher'
                                    ->where('role', 'teacher')

                                    // 2. Filter Unik: Jangan tampilkan user yang sudah dipakai guru lain
                                    ->where(
                                        fn($q) => $q
                                            ->whereDoesntHave('teacher') // Cari user yang TIDAK punya data di tabel teachers
                                            ->orWhere('id', $record?->user_id) // TAPI munculkan user milik record ini (saat mode Edit)
                                    )
                            )
                            ->label('Akun Login (User)')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Buat dulu akun di menu Users, lalu pilih di sini.')
                            ->live() // <--- 1. Bikin form jadi "Hidup" (Reaktif)
                            ->afterStateUpdated(function (Set $set, $state) {
                                // 2. Logika Autofill
                                if ($state) {
                                    $user = User::find($state);
                                    if ($user) {
                                        // Copas Nama & Email dari User ke Form Guru
                                        $set('name', $user->name);
                                        $set('email', $user->email);
                                    }
                                }
                            }),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true),
                    ]),

                // BAGIAN 2: BIODATA GURU (Kode dari Anda)
                Forms\Components\Section::make('Identitas Guru')
                    ->schema([
                        Forms\Components\TextInput::make('nip')
                            ->label('NIP')
                            ->helperText('Nomor Induk Pegawai (Opsional)')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('Email Pribadi')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->label('Nomor Telepon/WA')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Lengkap')
                            ->columnSpanFull(), // Agar memanjang ke samping
                    ])->columns(2), // Tampilan 2 kolom biar rapi
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Guru')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'), // Cetak tebal biar jelas

                Tables\Columns\TextColumn::make('email')
                    ->icon('heroicon-m-envelope')
                    ->copyable(), // Fitur keren: bisa diklik copy

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon'),
            ])
            ->filters([
                //
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeachers::route('/'),
            'create' => Pages\CreateTeacher::route('/create'),
            'edit' => Pages\EditTeacher::route('/{record}/edit'),
        ];
    }
}
