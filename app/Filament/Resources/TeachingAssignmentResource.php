<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeachingAssignmentResource\Pages;
use App\Filament\Resources\TeachingAssignmentResource\RelationManagers;
use App\Models\TeachingAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

/**
 * Resource untuk mengelola SK Mengajar (Teaching Assignments).
 * File ini mengatur antarmuka tabel, form tambah/edit, dan relasi tab di halaman detail.
 */
class TeachingAssignmentResource extends Resource
{
    protected static ?string $model = TeachingAssignment::class;

    protected static ?string $navigationLabel = 'Kelas Ajar Saya';
    protected static ?string $modelLabel = 'Kelas Ajar';
    protected static ?string $pluralModelLabel = 'Kelas Ajar';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole(['super_admin', 'admin', 'teacher']) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return auth()->check() && auth()->user()->hasRole('teacher') ? 'Saya sebagai Guru Mapel' : 'Akademik';
    }

    /**
     * Membatasi query agar Guru hanya bisa melihat kelas yang diajarkannya sendiri.
     * Super Admin tetap bisa melihat semua kelas secara keseluruhan.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['academicPeriod', 'teacher', 'subject', 'classroom', 'schedules']);

        // Filter: Guru hanya melihat kelasnya sendiri
        if (auth()->check() && auth()->user()->hasRole('teacher')) {
            $teacher = auth()->user()->teacher;
            if ($teacher) {
                $query->where('teacher_id', $teacher->id);
            } else {
                $query->whereRaw('1 = 0'); // Jika user adalah guru tapi profilnya belum dibuat, kosongkan tabel
            }
        }

        return $query;
    }

    /**
     * Apakah pilihan SK saat ini efektif berjenis ekstrakurikuler?
     * Prioritas: override subject_type di form → tipe global mapel terpilih.
     * Dipakai untuk menampilkan field pembina eksternal & melonggarkan
     * kewajiban Guru internal.
     */
    protected static function isExtracurricularSelection(Forms\Get $get): bool
    {
        if ($get('subject_type') === 'extracurricular') {
            return true;
        }

        if (filled($get('subject_type'))) {
            return false; // override eksplisit ke tipe lain
        }

        $subjectId = $get('subject_id');

        return $subjectId
            ? \App\Models\Subject::find($subjectId)?->type === 'extracurricular'
            : false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // =========================================================
                // BAGIAN 1: INFORMASI PENUGASAN (Header SK Mengajar)
                // =========================================================
                Forms\Components\Section::make('Informasi Penugasan')
                    ->description('Data SK Mengajar (Hanya Admin yang boleh ubah)')
                    ->schema([
                        Forms\Components\Select::make('academic_period_id')
                            ->label('Tahun Ajaran')
                            ->relationship(
                                name: 'academicPeriod',
                                modifyQueryUsing: fn(Builder $query) => $query->orderBy('start_year', 'desc')
                            )
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->start_year}/{$record->end_year} " . ($record->semester == 'odd' ? 'Ganjil' : 'Genap'))
                            ->disabled(fn() => !auth()->user()->hasRole('super_admin'))
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('teacher_id')
                            ->label('Guru')
                            ->relationship('teacher', 'name')
                            ->disabled(fn() => !auth()->user()->hasRole('super_admin'))
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->live()
                            // Guru internal WAJIB, KECUALI untuk ekskul yang diisi
                            // pembina eksternal (tanpa akun) — lihat field di bawah.
                            ->required(fn(Forms\Get $get) => ! (
                                self::isExtracurricularSelection($get) && filled($get('external_instructor_name'))
                            ))
                            ->helperText(fn(Forms\Get $get) => self::isExtracurricularSelection($get)
                                ? 'Untuk pembina eksternal (tanpa akun), kosongkan ini dan isi "Pembina Eksternal".'
                                : null),

                        // Pembina ekskul dari LUAR sekolah: tanpa akun, tanpa NIP.
                        Forms\Components\TextInput::make('external_instructor_name')
                            ->label('Pembina Eksternal (tanpa akun)')
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->visible(fn(Forms\Get $get) => self::isExtracurricularSelection($get))
                            ->disabled(fn() => !auth()->user()->hasRole('super_admin'))
                            ->dehydrated()
                            ->helperText('Isi jika pembina ekskul berasal dari luar sekolah. Kosongkan bila pembina adalah Guru internal.'),

                        Forms\Components\Select::make('subject_id')
                            ->label('Mata Pelajaran')
                            ->relationship('subject', 'name')
                            ->disabled(fn() => !auth()->user()->hasRole('super_admin'))
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->live() // PENTING: Memicu render ulang agar pengaturan nilai di bawah bisa muncul/hilang
                            ->required(),

                        Forms\Components\Select::make('classroom_id')
                            ->label('Kelas')
                            ->relationship('classroom', 'name')
                            ->disabled(fn() => !auth()->user()->hasRole('super_admin'))
                            ->dehydrated()
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('subject_type')
                            ->label('Override Tipe Mapel (Opsional)')
                            ->options([
                                'mandatory' => 'Wajib — Otomatis semua siswa',
                                'kokurikuler' => 'Kokurikuler — Otomatis semua siswa',
                                'elective' => 'Pilihan — Siswa didaftarkan manual',
                                'extracurricular' => 'Ekskul — Siswa didaftarkan manual',
                            ])
                            ->nullable()
                            ->native(false)
                            ->live()
                            ->placeholder('Ikuti tipe dari Mata Pelajaran (default)')
                            ->helperText(
                                'Kosongkan untuk mengikuti tipe mapel yang sudah disetting. ' .
                                'Isi jika mapel ini perlu tipe berbeda untuk kelas ini saja. ' .
                                'Contoh: Fisika jadi Pilihan di kelas SMA IPS.'
                            )
                            ->disabled(fn() => !auth()->user()->hasRole('super_admin'))
                            ->dehydrated(),
                    ])
                    ->columns(2),

                // =========================================================
                // BAGIAN 2: PENGATURAN NILAI RAPOR (Dinamis)
                // =========================================================
                Forms\Components\Section::make('Pengaturan Pengolahan Nilai Rapor')
                    ->description('Pilih strategi pengolahan nilai akhir untuk rapor.')
                    ->visible(function (Forms\Get $get) {
                        $subjectId = $get('subject_id');

                        // Tampilkan default jika mapel belum dipilih (saat Create)
                        if (!$subjectId) {
                            return true;
                        }

                        // Sembunyikan blok pengaturan nilai ini jika Mapel adalah Kokurikuler (P5)
                        // karena P5 tidak memakai angka / rumus matematika
                        $subject = \App\Models\Subject::find($subjectId);
                        return $subject ? $subject->is_kokurikuler === false : true;
                    })
                    ->schema([
                        Forms\Components\Select::make('grading_formula')
                            ->label('Metode Pengolahan Nilai')
                            ->options([
                                'average' => '1. Opsi Rata-Rata (Average)',
                                'weighting' => '2. Opsi Pembobotan (Weighted)',
                                'percentage' => '3. Opsi Persentase (Threshold/KKTP)',
                            ])
                            ->default('average')
                            ->helperText(new HtmlString("
                                <ul class='list-disc pl-4 text-sm text-gray-500'>
                                    <li><b>Rata-Rata:</b> Nilai akhir adalah rata-rata murni dari seluruh asesmen.</li>
                                    <li><b>Pembobotan:</b> Nilai dikali bobot masing-masing TP (Misal: Materi sulit bobotnya lebih besar).</li>
                                    <li><b>Persentase:</b> Dihitung dari jumlah TP yang TUNTAS (Nilai > KKTP).</li>
                                </ul>
                            "))
                            ->live() // Memicu re-render agar form KKTP bisa merespon
                            ->required(),

                        // --- INPUT KKTP ---
                        Forms\Components\TextInput::make('kktp')
                            ->label('Nilai KKTP (Kriteria Ketercapaian Tujuan Pembelajaran)')
                            ->helperText(
                                fn(Forms\Get $get) => $get('grading_formula') === 'percentage'
                                ? 'Nilai minimal agar TP dianggap Tuntas. Juga digunakan untuk menentukan predikat rapor (A/B/C/D).'
                                : 'Digunakan untuk menentukan predikat rapor: A ≥ 90, B ≥ KKTP, C ≥ KKTP-15, D < KKTP-15.'
                            )
                            ->numeric()
                            ->default(fn() => \App\Models\SchoolSetting::first()?->default_kkm ?? 75)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('/ 100')
                            ->disabled(false) // KKTP boleh diedit siapa saja yang punya akses ke record ini
                            ->required(),

                        // --- BOOSTER NILAI FORMATIF ---
                        Forms\Components\Select::make('booster_mode')
                            ->label('Booster Nilai Formatif')
                            ->options([
                                'none' => 'Nonaktif — nilai formatif tidak menambah',
                                'weight' => 'Bobot Persen — nilai_formatif × %',
                                'point' => 'Poin Tetap — per formatif terisi',
                            ])
                            ->default('none')
                            ->helperText('Menambahkan kontribusi nilai formatif ke skor sumatif (berlaku di nilai akhir & deskripsi rapor).')
                            ->live()
                            ->required(),

                        Forms\Components\TextInput::make('booster_value')
                            ->label(fn(Forms\Get $get) => $get('booster_mode') === 'point'
                                ? 'Poin per formatif terisi'
                                : 'Persentase per nilai formatif (%)')
                            ->helperText(fn(Forms\Get $get) => match ($get('booster_mode')) {
                                'weight' => 'Kontribusi tiap formatif diakumulasi & dibatasi maksimal 100. Disarankan nilai kecil (5–10%).',
                                'point' => 'Contoh: 2 → tiap formatif yang terisi menambah 2 poin.',
                                default => null,
                            })
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn(Forms\Get $get) => $get('booster_mode') !== 'none')
                            ->required(fn(Forms\Get $get) => $get('booster_mode') !== 'none'),
                    ])
                    ->collapsed(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Mata Pelajaran')
                    ->sortable()
                    ->weight('bold')
                    ->searchable()
                    ->description(
                        fn(TeachingAssignment $record): string =>
                        match ($record->getEffectiveType()) {
                            'kokurikuler' => 'Kokurikuler (P5)',
                            'elective' => 'Mapel Pilihan',
                            'extracurricular' => 'Ekstrakurikuler',
                            default => '',
                        }
                    ),
                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                // Tampilkan maksimal 2 hari pertama dari jadwal yang terhubung
                Tables\Columns\TextColumn::make('schedules.day')
                    ->label('Hari')
                    ->badge()
                    ->separator(',')
                    ->limitList(2),

                // Sembunyikan nama guru jika yang login adalah guru itu sendiri agar tampilan ringkas
                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Guru')
                    ->visible(fn() => !auth()->user()->hasRole('teacher'))
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                // --- 1. TOMBOL DOWNLOAD REFERENSI CSV ---
                Tables\Actions\Action::make('download_referensi')
                    ->label('Download Data Referensi')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn() => auth()->user()->hasRole('super_admin'))
                    ->action(function () {
                        $teachers = \App\Models\Teacher::orderBy('name')->pluck('name')->toArray();
                        $subjects = \App\Models\Subject::orderBy('name')->pluck('name')->toArray();
                        $classrooms = \App\Models\Classroom::orderBy('name')->pluck('name')->toArray();

                        $periods = \App\Models\AcademicPeriod::orderBy('start_year', 'desc')
                            ->get()
                            ->map(fn($p) => $p->start_year . '/' . $p->end_year)
                            ->unique() // Agar tidak dobel 2025/2026 karena ada ganjil/genap
                            ->toArray();

                        // Reset index array agar mulai dari 0 berurutan
                        $periods = array_values($periods);

                        $maxLength = max(count($teachers), count($subjects), count($classrooms), count($periods));

                        return response()->streamDownload(function () use ($teachers, $subjects, $classrooms, $periods, $maxLength) {
                            $file = fopen('php://output', 'w');
                            fputcsv($file, ['Daftar Tahun Ajaran', 'Daftar Nama Guru', 'Daftar Mata Pelajaran', 'Daftar Kelas'], ';');

                            for ($i = 0; $i < $maxLength; $i++) {
                                fputcsv($file, [
                                    $periods[$i] ?? '',
                                    $teachers[$i] ?? '',
                                    $subjects[$i] ?? '',
                                    $classrooms[$i] ?? '',
                                ], ';');
                            }
                            fclose($file);
                        }, 'Referensi_Data_SK_Mengajar.csv');
                    }),

                // --- 2. TOMBOL IMPORT CSV BAWAAN FILAMENT ---
                Tables\Actions\ImportAction::make()
                    ->label('Import SK Mengajar')
                    ->importer(\App\Filament\Imports\TeachingAssignmentImporter::class)
                    ->chunkSize(50)
                    ->color('primary')
                    // --- MEMBUATNYA TERPISAH DI EXCEL ---
                    ->csvDelimiter(';')
                    ->modalHeading('Import Data SK Mengajar')
                    // Catatan: modalDescription sengaja Dihapus agar tombol 'Download sample CSV' muncul
                    ->visible(fn() => auth()->user()->hasRole('super_admin')),

                // --- 3. TOMBOL IMPORT JADWAL PELAJARAN ---
                Tables\Actions\ImportAction::make('import_jadwal')
                    ->label('Import Jadwal')
                    ->icon('heroicon-o-calendar-days')
                    ->importer(\App\Filament\Imports\SubjectScheduleImporter::class)
                    ->chunkSize(50)
                    ->color('warning') // Menggunakan warna oranye/kuning agar mudah dibedakan
                    ->csvDelimiter(';')
                    ->modalHeading('Import Data Jadwal Pelajaran')
                    ->visible(fn() => auth()->user()->hasRole('super_admin')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // --- 3. TOMBOL BUKU NILAI KUSTOM ---
                Tables\Actions\Action::make('gradebook')
                    ->label('Buku Nilai')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->url(fn(TeachingAssignment $record): string => TeachingAssignmentResource::getUrl('gradebook', ['record' => $record]))
                    // Hanya tampil jika mata pelajaran BUKAN P5/Kokurikuler
                    ->visible(fn(TeachingAssignment $record) => !$record->subject->is_kokurikuler),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // PROTEKSI LONGITUDINAL (Audit HIGH-1): batalkan hapus jika SK
                    // Mengajar sudah punya nilai akhir / asesmen. Cascade akan
                    // memusnahkan final_grades, grades, absensi, dan jadwal mapel ini.
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $blocked = $records->filter(fn (TeachingAssignment $r) => self::hasHistory($r));

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->title('Penghapusan dibatalkan')
                                    ->body($blocked->count() . ' SK Mengajar sudah memiliki nilai/asesmen dan tidak dapat dihapus untuk menjaga data longitudinal.')
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ])->visible(fn() => !auth()->user()->hasRole('teacher')),
            ]);
    }

    /**
     * Apakah SK Mengajar ini sudah memiliki data turunan (nilai akhir/asesmen)
     * yang akan ikut termusnahkan via FK cascade bila dihapus?
     */
    protected static function hasHistory(TeachingAssignment $record): bool
    {
        return $record->finalGrades()->exists()
            || $record->assessments()->exists();
    }

    /**
     * Daftarkan Tab Relasi Bawah (Relation Managers) yang akan muncul saat View/Edit SK Mengajar
     */
    public static function getRelations(): array
    {
        $relations = [
            RelationManagers\AttendancesRelationManager::class,       // Tab Absensi (Muncul terus)
            RelationManagers\AssessmentsRelationManager::class,       // Tab Rencana Nilai Akademik (Dinonaktifkan via UI jika P5)
            RelationManagers\NarrativeTemplatesRelationManager::class, // Tab Template Narasi (Override per kelas)
            RelationManagers\ExtracurricularStudentsRelationManager::class, // Tab Siswa Ekstrakurikuler
        ];

        // Tampilkan Tab Atur Jadwal HANYA JIKA user BUKAN guru (Karena Admin yang berhak atur jadwal)
        if (!auth()->user()?->hasRole('teacher')) {
            array_unshift($relations, RelationManagers\SchedulesRelationManager::class);
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeachingAssignments::route('/'),
            'create' => Pages\CreateTeachingAssignment::route('/create'),
            'edit' => Pages\EditTeachingAssignment::route('/{record}/edit'),
            'view' => Pages\ViewTeachingAssignment::route('/{record}'),
            // Halaman Kustom Buku Nilai
            'gradebook' => Pages\ViewGradebook::route('/{record}/gradebook'),
        ];
    }
}