<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule; // Tambahan Import agar aman

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
                    ->options(function (Forms\Get $get, ?Enrollment $record) {
                        $periodId = $get('academic_period_id')
                            ?? AcademicPeriod::where('is_active', true)->first()?->id;

                        if (!$periodId) {
                            return [];
                        }

                        return Student::query()
                            ->whereDoesntHave('enrollments', fn($q) => $q
                                ->where('academic_period_id', $periodId)
                                ->when($record, fn($qq) => $qq->where('id', '!=', $record->id)))
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
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
                Tables\Actions\DeleteAction::make(),
            ])
            // --- FITUR BULK ACTION (NAIK KELAS) ---
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('move_class')
                        ->label('Proses Kenaikan / Tinggal Kelas')
                        ->icon('heroicon-o-arrow-right-end-on-rectangle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-academic-cap')
                        ->modalHeading('Pindahkan Siswa')
                        ->modalDescription('Pilih Kelas Tujuan dan Tahun Ajaran Baru.')
                        ->form([
                            // Pilih Tahun Baru
                            Forms\Components\Select::make('new_academic_period_id')
                                ->label('Tahun Ajaran Baru')
                                ->options(AcademicPeriod::getSelectOptions())
                                ->default(fn() => AcademicPeriod::where('is_active', true)->first()?->id)
                                ->required(),

                            // Pilih Kelas Tujuan
                            Forms\Components\Select::make('new_classroom_id')
                                ->label('Kelas Tujuan')
                                ->options(Classroom::all()->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Pilih kelas tujuan (bisa naik kelas atau tinggal kelas).'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $successCount = 0;
                            $skipped = [];

                            // Atomik: gagal di tengah -> seluruh batch di-rollback
                            // (mencegah partial write pada operasi kenaikan kelas massal).
                            DB::transaction(function () use ($records, $data, &$successCount, &$skipped) {
                                foreach ($records as $enrollment) {
                                    // Constraint DB: UNIQUE(student_id, academic_period_id).
                                    // Cek HANYA (siswa + periode tujuan) — kelas TIDAK relevan;
                                    // siswa yang sudah punya kelas apa pun di periode tujuan dilewati.
                                    $exists = Enrollment::where('student_id', $enrollment->student_id)
                                        ->where('academic_period_id', $data['new_academic_period_id'])
                                        ->exists();

                                    if ($exists) {
                                        $skipped[] = $enrollment->student?->name ?? "ID {$enrollment->student_id}";
                                        continue;
                                    }

                                    Enrollment::create([
                                        'student_id' => $enrollment->student_id,
                                        'classroom_id' => $data['new_classroom_id'],
                                        'academic_period_id' => $data['new_academic_period_id'],
                                        'status' => 'active',
                                    ]);
                                    $successCount++;
                                }
                            });

                            $body = "{$successCount} siswa berhasil dipindahkan.";
                            if ($skipped !== []) {
                                $shown = implode(', ', array_slice($skipped, 0, 5));
                                $more = count($skipped) > 5 ? ' +' . (count($skipped) - 5) . ' lainnya' : '';
                                $body .= ' Dilewati karena sudah terdaftar di periode tujuan: ' . $shown . $more . '.';
                            }

                            $notification = Notification::make()
                                ->title('Proses Kenaikan Kelas Selesai')
                                ->body($body);

                            $skipped === [] ? $notification->success() : $notification->warning();
                            $notification->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}