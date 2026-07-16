<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\Student;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentResource extends Resource
{
    // --- KONFIGURASI NAVIGASI ---
    protected static ?string $model = Student::class;
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Data Siswa';
    protected static ?string $modelLabel = 'Siswa';
    protected static ?string $pluralModelLabel = 'Data Siswa';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    /**
     * --- FORM INPUT DATA ---
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // BAGIAN 1: AKUN USER (LOGIN)
                Forms\Components\Section::make('Akun & Login')
                    // Saat CREATE, akun siswa (username = NISN) dan akun wali
                    // (username = WALI_{NISN}) digenerate OTOMATIS oleh
                    // CreateStudent::handleRecordCreation — konsisten dengan
                    // StudentImporter dan aturan 1 siswa = 1 akun wali.
                    // Section ini hanya untuk penautan ulang manual saat EDIT.
                    ->description('Akun login digenerate otomatis saat siswa dibuat. Gunakan bagian ini hanya untuk penautan ulang manual.')
                    ->hiddenOn('create')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Akun User')
                            ->relationship(
                                name: 'user',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn(Builder $query, $record) => $query
                                    ->where('role', 'student')
                                    ->where(
                                        fn($q) =>
                                        $q->whereDoesntHave('student')
                                            ->orWhere('id', $record?->user_id)
                                    )
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn(Set $set, $state) => $state ? $set('name', User::find($state)?->name) : null)
                            ->helperText('Buat akun role "Student" di menu Users terlebih dahulu.'),
                    ])->collapsible(),

                // BAGIAN 2: DATA PRIBADI
                Forms\Components\Section::make('Biodata Pribadi')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            // Identitas Nomor
                            Forms\Components\TextInput::make('name')
                                ->label('Nama Lengkap')
                                ->required()
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('nisn')
                                ->label('NISN')
                                ->placeholder('Nomor Induk Siswa Nasional')
                                ->rules(['numeric']) // Validasi hanya boleh angka, tapi inputnya tetap text
                                ->unique(ignoreRecord: true)
                                // Wajib: username akun siswa & wali digenerate dari NISN
                                ->required()
                                ->helperText('Akun login siswa (username = NISN) dan wali (WALI_NISN) dibuat otomatis dari nomor ini.')
                                ->maxLength(20),

                            Forms\Components\TextInput::make('nipd')
                                ->label('NIPD')
                                ->placeholder('Nomor Induk Peserta Didik')
                                ->rules(['numeric'])
                                ->maxLength(20),

                            Forms\Components\TextInput::make('nik')
                                ->label('NIK')
                                ->placeholder('Nomor Induk Kependudukan')
                                ->rules(['numeric'])
                                ->minLength(16)
                                ->maxLength(16),

                            // Data Diri
                            Forms\Components\Select::make('gender')
                                ->label('Jenis Kelamin')
                                ->options([
                                    'L' => 'Laki-laki',
                                    'P' => 'Perempuan',
                                ])
                                ->required(),

                            Forms\Components\Select::make('religion')
                                ->label('Agama')
                                ->options([
                                    'Islam' => 'Islam',
                                    'Kristen' => 'Kristen',
                                    'Katolik' => 'Katolik',
                                    'Hindu' => 'Hindu',
                                    'Buddha' => 'Buddha',
                                    'Konghucu' => 'Konghucu',
                                ]),

                            Forms\Components\TextInput::make('place_of_birth')
                                ->label('Tempat Lahir'),

                            Forms\Components\DatePicker::make('date_of_birth')
                                ->label('Tanggal Lahir')
                                ->native(false)
                                ->displayFormat('d F Y'),

                            Forms\Components\Textarea::make('address')
                                ->label('Alamat Domisili')
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                    ]),

                // BAGIAN 3: DATA ORANG TUA
                Forms\Components\Section::make('Data Orang Tua')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('father_name')
                                ->label('Nama Ayah'),

                            Forms\Components\TextInput::make('mother_name')
                                ->label('Nama Ibu'),
                        ]),
                    ])->collapsible(),

                // BAGIAN 4: STATUS
                Forms\Components\Section::make('Status Akademik')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Kesiswaan')
                            ->options([
                                'active' => 'Aktif',
                                'graduated' => 'Lulus',
                                'moved' => 'Pindah Sekolah',
                                'dropped_out' => 'Putus Sekolah (DO)',
                                'deceased' => 'Meninggal',
                            ])
                            ->default('active')
                            ->required(),
                    ]),
            ]);
    }

    /**
     * --- TABEL DATA ---
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable()
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('nipd')
                    ->label('NIPD')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(Student $record): string => $record->nipd ? "NIPD: {$record->nipd}" : ''),

                // MAGIC COLUMN: Kelas Saat Ini
                Tables\Columns\TextColumn::make('currentClassroom.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('primary')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('gender')
                    ->label('L/P')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'L' => 'info',
                        'P' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'graduated' => 'Lulus',
                        'moved' => 'Pindah',
                        'dropped_out' => 'DO',
                        'deceased' => 'Meninggal',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'graduated' => 'warning',
                        'moved', 'dropped_out', 'deceased' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Siswa Aktif',
                        'graduated' => 'Alumni',
                        'moved' => 'Pindahan',
                    ]),
                Tables\Filters\SelectFilter::make('gender')
                    ->label('Jenis Kelamin')
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ]),
            ])
            // --- BAGIAN TOMBOL HEADER (Kanan Atas Tabel) ---
            ->headerActions([
                // 1. TOMBOL DOWNLOAD DRAFT EXCEL
                Tables\Actions\Action::make('download_draft')
                    ->label('Download Draft CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success') // Warna hijau agar mencolok
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $file = fopen('php://output', 'w');

                            // Tulis Baris Header (Judul Kolom)
                            fputcsv($file, [
                                'nisn',
                                'nama_siswa',
                                'jenis_kelamin',
                                'tanggal_lahir',
                                'kelas_sekarang'
                            ], ';');

                            // Tulis Baris Contoh
                            fputcsv($file, [
                                '0012345678',
                                'Budi Santoso',
                                'L',
                                '2010-08-17',
                                'Kelas 7.1'
                            ], ';');

                            fclose($file);
                        }, 'Draft_Import_Data_Siswa.csv');
                    }),

                // 2. TOMBOL IMPOR SISWA (Smart Importer)
                Tables\Actions\ImportAction::make('import_siswa')
                    ->label('Impor Data Siswa')
                    ->icon('heroicon-o-users')
                    ->importer(\App\Filament\Imports\StudentImporter::class)
                    ->color('primary')
                    ->csvDelimiter(';')
                    ->modalHeading('Impor Data & Akun Siswa Baru')
                    ->modalDescription('Unggah file CSV/Excel berisi data siswa. Sistem akan otomatis membuatkan akun siswa, akun wali, dan profil siswa.')
                    ->visible(fn() => auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')),
            ])
            // Tombol Baris (Muncul di setiap baris Siswa)
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
            RelationManagers\EnrollmentsRelationManager::class,
            RelationManagers\FinalGradesRelationManager::class,
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