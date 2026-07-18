<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use App\Models\AcademicPeriod;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    // Judul Tab di Halaman Edit Kelas
    protected static ?string $title = 'Daftar Siswa';

    /**
     * --- LOGIKA KEAMANAN ---
     * Jika user adalah Guru, maka tab ini otomatis menjadi READ ONLY.
     */
    public function isReadOnly(): bool
    {
        return auth()->user()?->hasRole('teacher') ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Periode dipilih lebih dulu agar daftar siswa bisa difilter reaktif.
                Forms\Components\Select::make('academic_period_id')
                    ->label('Tahun Ajaran')
                    ->options(AcademicPeriod::where('is_active', true)->get()->mapWithKeys(fn($p) => [$p->id => $p->name]))
                    ->default(fn() => AcademicPeriod::where('is_active', true)->first()?->id)
                    ->live()
                    ->required(),

                Forms\Components\Select::make('student_id')
                    ->label('Siswa')
                    // Hanya siswa yang BELUM punya kelas pada periode terpilih.
                    // Constraint DB: UNIQUE(student_id, academic_period_id) —
                    // 1 siswa hanya boleh 1 kelas per periode, apa pun kelasnya.
                    //
                    // ✅ SHARED HOSTING: pencarian LAZY (server-side, dibatasi 50 baris).
                    // Mengganti ->options()+->preload() yang menghidrasi SELURUH tabel
                    // siswa (termasuk alumni) ke memori setiap form dibuka.
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search, Forms\Get $get, ?Enrollment $record) {
                        $periodId = $get('academic_period_id')
                            ?? AcademicPeriod::where('is_active', true)->first()?->id;

                        if (!$periodId) {
                            return [];
                        }

                        return Student::query()
                            ->where('name', 'like', "%{$search}%")
                            ->whereDoesntHave('enrollments', fn($q) => $q
                                ->where('academic_period_id', $periodId)
                                ->when($record, fn($qq) => $qq->where('id', '!=', $record->id)))
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id');
                    })
                    // Menampilkan label siswa yang sudah terpilih (mode edit) tanpa
                    // perlu memuat ulang seluruh daftar.
                    ->getOptionLabelUsing(fn ($value) => Student::find($value)?->name)
                    ->required()
                    // Validasi aplikasi HARUS sama dimensinya dengan constraint DB:
                    // (student_id + academic_period_id), TANPA classroom_id.
                    ->unique(
                        table: 'enrollments',
                        column: 'student_id',
                        modifyRuleUsing: fn($rule, $get) => $rule
                            ->where('academic_period_id', $get('academic_period_id')),
                        ignoreRecord: true
                    )
                    ->validationMessages([
                        'unique' => 'Siswa ini sudah terdaftar di kelas lain pada periode tersebut.',
                    ]),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    // Opsi WAJIB sama dengan ENUM di migrasi enrollments:
                    // active, promoted, retained, graduated, dropped.
                    ->options([
                        'active' => 'Aktif',
                        'promoted' => 'Naik Kelas',
                        'retained' => 'Tinggal Kelas',
                        'graduated' => 'Lulus',
                        'dropped' => 'Keluar',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('student.nisn')
                    ->label('NISN')
                    ->searchable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Periode')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    // Nilai mengikuti ENUM migrasi enrollments:
                    // active, promoted, retained, graduated, dropped.
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'promoted' => 'info',
                        'retained' => 'warning',
                        'graduated' => 'primary',
                        'dropped' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'promoted' => 'Naik Kelas',
                        'retained' => 'Tinggal Kelas',
                        'graduated' => 'Lulus',
                        'dropped' => 'Keluar',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'promoted' => 'Naik Kelas',
                        'retained' => 'Tinggal Kelas',
                        'graduated' => 'Lulus',
                        'dropped' => 'Keluar',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Siswa Manual'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // PROTEKSI LONGITUDINAL (Audit HIGH-1): sembunyikan Hapus jika
                // enrollment ini adalah asal promosi atau sudah punya nilai.
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (\App\Models\Enrollment $record): bool => $record->hasLongitudinalHistory()),
            ])
            // Kenaikan kelas TIDAK dilakukan dari sini. Satu-satunya jalur mutasi
            // enrollment adalah PromotionService (halaman Proses Kenaikan Kelas),
            // yang berjalan dalam DB::transaction, menutup enrollment lama, dan
            // mengisi promoted_from_enrollment_id sebagai rantai riwayat
            // untuk grafik longitudinal.
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $blocked = $records->filter(fn (\App\Models\Enrollment $r) => $r->hasLongitudinalHistory());

                            if ($blocked->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Penghapusan dibatalkan')
                                    ->body($blocked->count() . ' pendaftaran memiliki jejak longitudinal (nilai/rantai promosi) dan tidak dapat dihapus.')
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }
}