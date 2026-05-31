<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherResource\Pages;
use App\Models\Teacher;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeacherResource extends Resource
{
    // --- KONFIGURASI NAVIGASI ---
    protected static ?string $model = Teacher::class;
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Data Guru';
    protected static ?string $modelLabel = 'Guru';
    protected static ?string $pluralModelLabel = 'Data Guru';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase'; // Icon Tas Kerja
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * --- FORM INPUT DATA ---
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // KELOMPOK 1: KONEKSI AKUN (USER)
                Forms\Components\Section::make('Akun & Login')
                    ->description('Hubungkan profil guru ini dengan akun login pengguna.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Pilih Akun User')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query, $record) => $query
                                    ->where('role', 'teacher')
                                    ->where(
                                        fn($q) =>
                                        $q->whereDoesntHave('teacher')
                                            ->orWhere('id', $record?->user_id)
                                    )
                            )
                            ->searchable()
                            ->preload()
                            ->live() // Agar bisa trigger auto-fill
                            ->afterStateUpdated(function (Set $set, $state) {
                                // LOGIKA AUTO-FILL
                                if ($state) {
                                    $user = User::find($state);
                                    if ($user) {
                                        $set('name', $user->name);
                                        $set('email', $user->email);
                                    }
                                }
                            })
                            ->helperText('Pastikan User sudah dibuat di menu Users dengan role "Teacher".'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Guru Aktif')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger'),
                    ])->collapsible(),

                // KELOMPOK 2: BIODATA UTAMA
                Forms\Components\Section::make('Identitas Guru')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('nip')
                                ->label('NIP')
                                ->placeholder('Nomor Induk Pegawai')
                                ->unique(ignoreRecord: true)
                                ->maxLength(50),

                            Forms\Components\TextInput::make('name')
                                ->label('Nama Lengkap (dengan Gelar)')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\Select::make('gender')
                                ->label('Jenis Kelamin')
                                ->options([
                                    'L' => 'Laki-laki',
                                    'P' => 'Perempuan',
                                ]),

                            Forms\Components\DatePicker::make('date_of_birth')
                                ->label('Tanggal Lahir')
                                ->native(false)
                                ->displayFormat('d F Y'),

                            Forms\Components\TextInput::make('place_of_birth')
                                ->label('Tempat Lahir'),

                            Forms\Components\TextInput::make('phone')
                                ->label('No. Telepon / WA')
                                ->tel()
                                ->maxLength(20),

                            Forms\Components\TextInput::make('email')
                                ->label('Email Pribadi')
                                ->email()
                                ->maxLength(255),

                            Forms\Components\Textarea::make('address')
                                ->label('Alamat Lengkap')
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),
                    ]),

                // KELOMPOK 3: DATA KEPEGAWAIAN (DUK)
                Forms\Components\Section::make('Data Kepegawaian & Pendidikan')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([ // 3 Kolom biar padat
                            Forms\Components\Select::make('employment_status')
                                ->label('Status Kepegawaian')
                                ->options([
                                    'PNS' => 'PNS',
                                    'PPPK' => 'PPPK',
                                    'GTT' => 'Guru Tidak Tetap / Honorer',
                                    'GTY' => 'Guru Tetap Yayasan',
                                ]),

                            Forms\Components\TextInput::make('position')
                                ->label('Jabatan')
                                ->placeholder('Contoh: Guru Mapel, Kepala Sekolah'),

                            Forms\Components\DatePicker::make('assignment_date')
                                ->label('TMT Mulai Dinas')
                                ->native(false)
                                ->displayFormat('d F Y'),

                            Forms\Components\TextInput::make('rank')
                                ->label('Pangkat')
                                ->placeholder('Contoh: Pembina, Ahli Pertama'),

                            Forms\Components\TextInput::make('grade')
                                ->label('Golongan')
                                ->placeholder('Contoh: IV/a, IX'),

                            Forms\Components\TextInput::make('degree')
                                ->label('Pendidikan Terakhir')
                                ->placeholder('Contoh: S1, S2'),

                            Forms\Components\TextInput::make('major')
                                ->label('Jurusan')
                                ->placeholder('Contoh: Pend. Biologi')
                                ->columnSpan(2), // Agak lebar

                            Forms\Components\TextInput::make('university')
                                ->label('Asal Kampus'),
                        ]),
                    ])->collapsible(),
            ]);
    }

    /**
     * --- TABEL DATA ---
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Guru')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Teacher $record): string => $record->position ?? 'Guru'), // Jabatan jadi subtitle

                // Kolom Pangkat/Golongan
                Tables\Columns\TextColumn::make('grade')
                    ->label('Gol')
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),

                // Kolom Wali Kelas (MAGIC COLUMN)
                Tables\Columns\TextColumn::make('current_classroom.name')
                    ->label('Wali Kelas')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-home-modern')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('employment_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PNS' => 'success',
                        'PPPK' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->icon('heroicon-m-phone')
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan default biar tidak penuh
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employment_status')
                    ->label('Filter Status')
                    ->options([
                        'PNS' => 'PNS',
                        'PPPK' => 'PPPK',
                        'GTT' => 'Honorer',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif / Pensiun'),
            ])

            ->headerActions([
                // 1. TOMBOL DOWNLOAD DRAFT EXCEL GURU
                Tables\Actions\Action::make('download_draft')
                    ->label('Download Draft CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $file = fopen('php://output', 'w');

                            // Baris Header
                            fputcsv($file, [
                                'nip',
                                'nama_guru',
                                'email',
                                'no_hp',
                                'jenis_kelamin',
                                'tempat_lahir',
                                'tanggal_lahir',
                                'alamat',
                                'gelar_pendidikan',
                                'jurusan',
                                'asal_kampus',
                                'tahun_lulus',
                                'status_pegawai',
                                'jabatan',
                                'golongan',
                                'pangkat',
                                'mulai_dinas'
                            ], ';');

                            // Contoh Baris 1: Guru PNS (Punya NIP)
                            fputcsv($file, [
                                '198001012005011001',
                                'Ahmad Kurniawan, S.Pd.',
                                'ahmad@sekolah.com',
                                '081234567890',
                                'L',
                                'Bandung',
                                '1980-01-01',
                                'Jl. Merdeka No. 10',
                                'S.Pd',
                                'Pendidikan Matematika',
                                'Universitas Pendidikan Indonesia',
                                '2005',
                                'PNS',
                                'Guru Madya',
                                'IV/a',
                                'Pembina',
                                '2005-01-01'
                            ], ';');

                            // Contoh Baris 2: Guru Honorer (Tanpa NIP, Wajib isi Email)
                            fputcsv($file, [
                                '',
                                'Siti Amalia, S.Pd',
                                'siti.amalia@sekolah.sch.id',
                                '081298765432',
                                'P',
                                'Jakarta',
                                '1992-05-15',
                                'Jl. Sudirman No. 45',
                                'S.Pd',
                                'Pendidikan Bahasa Inggris',
                                'Universitas Negeri Jakarta',
                                '2015',
                                'Honorer',
                                'Guru Pertama',
                                '',
                                '',
                                '2016-01-07'
                            ], ';');

                            fclose($file);
                        }, 'Draft_Import_Data_Guru.csv');
                    }),

                // 2. TOMBOL IMPORT GURU
                Tables\Actions\ImportAction::make('import_guru')
                    ->label('Import Data Guru')
                    ->icon('heroicon-o-users')
                    ->importer(\App\Filament\Imports\TeacherImporter::class)
                    ->color('primary')
                    ->csvDelimiter(';')
                    ->modalHeading('Import Data & Akun Guru Baru')
                    ->visible(fn() => auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')),
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

    // ✅ PERBAIKAN N+1: Eager load relasi yang dipakai accessor di tabel.
    // Sebelumnya getCurrentClassroom accessor menjalankan query per baris.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'user',
                'classHomerooms' => fn($q) => $q->where('is_current', true)->with('classroom'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Bisa tambah relation manager di sini nanti
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