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
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
                                    // Spatie sebagai sumber kebenaran role (bukan kolom
                                    // legacy users.role yang bisa keliru pada akun manual).
                                    ->whereHas('roles', fn($q) => $q->where('name', 'student'))
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
                                // Angka-only via regex — JANGAN pakai rule `numeric`, karena
                                // dengan `numeric` Laravel menafsirkan `maxLength(20)` sebagai
                                // "nilai <= 20" (muncul "tidak boleh lebih besar dari 20").
                                // regex menjaga digit-only sekaligus leading-zero tetap utuh.
                                ->rule('regex:/^\d+$/')
                                ->validationMessages(['regex' => 'NISN hanya boleh berisi angka.'])
                                ->unique(ignoreRecord: true)
                                // Wajib: username akun siswa & wali digenerate dari NISN
                                ->required()
                                ->helperText('Akun login siswa (username = NISN) dan wali (WALI_NISN) dibuat otomatis dari nomor ini.')
                                ->maxLength(20),

                            Forms\Components\TextInput::make('nipd')
                                ->label('NIPD')
                                ->placeholder('Nomor Induk Peserta Didik')
                                ->rule('regex:/^\d+$/')
                                ->validationMessages(['regex' => 'NIPD hanya boleh berisi angka.'])
                                ->maxLength(20),

                            Forms\Components\TextInput::make('nik')
                                ->label('NIK')
                                ->placeholder('Nomor Induk Kependudukan')
                                ->rule('regex:/^\d{16}$/')
                                ->validationMessages(['regex' => 'NIK harus 16 digit angka.'])
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
                    // Shared hosting: 50 baris/batch agar tidak timeout/kehabisan RAM.
                    ->chunkSize(50)
                    ->color('primary')
                    ->csvDelimiter(';')
                    ->modalHeading('Impor Data & Akun Siswa Baru')
                    ->modalDescription('Unggah file CSV/Excel berisi data siswa. Sistem akan otomatis membuatkan akun siswa, akun wali, dan profil siswa.')
                    ->visible(fn() => auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')),
            ])
            // Tombol Baris (Muncul di setiap baris Siswa)
            ->actions([
                Tables\Actions\EditAction::make(),
                // PROTEKSI LONGITUDINAL (Audit HIGH-1): sembunyikan Hapus jika siswa
                // sudah pernah terdaftar di kelas (punya jejak nilai/absensi/rapor).
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Student $record): bool => self::hasHistory($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $blocked = $records->filter(fn (Student $r) => self::hasHistory($r));

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->title('Penghapusan dibatalkan')
                                    ->body($blocked->count() . ' siswa memiliki riwayat pendaftaran kelas dan tidak dapat dihapus untuk menjaga data longitudinal.')
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Apakah siswa ini sudah punya riwayat pendaftaran kelas? Menghapusnya akan
     * ikut memusnahkan enrollment, nilai, absensi, dan rapor via FK cascade.
     */
    protected static function hasHistory(Student $record): bool
    {
        return $record->enrollments()->exists();
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