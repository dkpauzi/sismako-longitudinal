<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExtracurricularGradeResource\Pages;
use App\Models\StudentSubjectEnrollment;
use App\Models\TeachingAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * NILAI EKSTRAKURIKULER — input gaya P5 (predikat + narasi, TANPA olah angka).
 *
 * Menu mandiri agar Admin/Wali Kelas mudah mendaftarkan siswa & mengisi nilai
 * ekskul (setara kemudahan menu Nilai P5), TANPA harus masuk ke SK Mengajar.
 * Model tetap StudentSubjectEnrollment (subsistem ekskul yang sudah ada), jadi
 * hak akses otomatis mengikuti StudentSubjectEnrollmentPolicy:
 *   - Admin/super_admin: penuh (mendaftarkan & menilai).
 *   - Wali Kelas AKTIF dari kelas siswa: hanya MENILAI (update) — dijamin policy.
 *   - Guru non-wali / siswa / wali murid: tidak melihat menu (viewAny ditolak).
 *
 * Pembina eksternal (tanpa akun) ditangani di level SK Mengajar
 * (teaching_assignments.external_instructor_name), bukan di sini.
 */
class ExtracurricularGradeResource extends Resource
{
    protected static ?string $model = StudentSubjectEnrollment::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Nilai Ekstrakurikuler';
    protected static ?string $modelLabel = 'Nilai Ekstrakurikuler';
    protected static ?string $pluralModelLabel = 'Nilai Ekstrakurikuler';
    protected static ?int $navigationSort = 8;

    /**
     * Batasi resource ke enrollment berjenis EKSTRAKURIKULER saja
     * (subject_type override, atau tipe global mapel = extracurricular).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('teachingAssignment', function (Builder $q) {
                $q->where('subject_type', 'extracurricular')
                    ->orWhere(function (Builder $o) {
                        $o->whereNull('subject_type')
                            ->whereHas('subject', fn (Builder $s) => $s->where('type', 'extracurricular'));
                    });
            })
            ->with(['student', 'teachingAssignment.subject', 'teachingAssignment.classroom', 'teachingAssignment.academicPeriod', 'teachingAssignment.teacher']);
    }

    /** Opsi dropdown SK ekskul, berlabel manusiawi (mapel · kelas · pembina · TA). */
    protected static function extracurricularAssignmentOptions(): array
    {
        return TeachingAssignment::query()
            ->where(function (Builder $q) {
                $q->where('subject_type', 'extracurricular')
                    ->orWhere(function (Builder $o) {
                        $o->whereNull('subject_type')
                            ->whereHas('subject', fn (Builder $s) => $s->where('type', 'extracurricular'));
                    });
            })
            ->with(['subject', 'classroom', 'academicPeriod', 'teacher'])
            ->get()
            ->mapWithKeys(fn (TeachingAssignment $ta) => [
                $ta->id => sprintf(
                    '%s · %s · %s (%s)',
                    $ta->subject?->name ?? 'Ekskul',
                    $ta->classroom?->name ?? '-',
                    $ta->instructorDisplayName(),
                    $ta->academicPeriod?->name ?? '-',
                ),
            ])
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('teaching_assignment_id')
                ->label('Ekstrakurikuler')
                ->options(fn () => static::extracurricularAssignmentOptions())
                ->searchable()
                ->required()
                ->disabledOn('edit'),

            Forms\Components\Select::make('student_id')
                ->label('Siswa')
                ->relationship('student', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->disabledOn('edit'), // daftarkan saat create; jangan pindah siswa saat menilai

            Forms\Components\Select::make('predicate')
                ->label('Predikat')
                ->options([
                    'Sangat Baik' => 'Sangat Baik',
                    'Baik' => 'Baik',
                    'Cukup' => 'Cukup',
                    'Kurang' => 'Kurang',
                ])
                ->native(false)
                ->required(),

            Forms\Components\Textarea::make('description')
                ->label('Deskripsi / Narasi')
                ->placeholder('Cth: Ananda aktif dan menunjukkan kepemimpinan yang baik dalam kegiatan...')
                ->rows(4)
                ->maxLength(65535)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('teachingAssignment.subject.name')
                    ->label('Ekstrakurikuler')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('teachingAssignment.classroom.name')
                    ->label('Kelas'),

                Tables\Columns\TextColumn::make('instructor')
                    ->label('Pembina')
                    ->getStateUsing(fn (StudentSubjectEnrollment $r) => $r->teachingAssignment?->instructorDisplayName() ?? '—'),

                Tables\Columns\TextColumn::make('predicate')
                    ->label('Predikat')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Sangat Baik' => 'success',
                        'Baik' => 'info',
                        'Cukup' => 'warning',
                        'Kurang' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('Belum dinilai'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Narasi')
                    ->limit(50),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Input Nilai'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada siswa terdaftar di ekstrakurikuler')
            ->emptyStateDescription('Klik "Daftarkan Siswa" untuk menambahkan siswa ke ekstrakurikuler.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExtracurricularGrades::route('/'),
            'create' => Pages\CreateExtracurricularGrade::route('/create'),
            'edit' => Pages\EditExtracurricularGrade::route('/{record}/edit'),
        ];
    }
}
