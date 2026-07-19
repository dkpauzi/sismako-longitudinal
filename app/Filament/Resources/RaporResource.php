<?php
// app/Filament/Resources/RaporResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\RaporResource\Pages;
use App\Models\ClassHomeroom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RaporResource extends Resource
{
    // Resource ini tidak terikat ke satu model tunggal.
    // Kita gunakan ClassHomeroom sebagai anchor karena
    // Wali Kelas hanya melihat kelas yang dipegangnya.
    protected static ?string $model = ClassHomeroom::class;

    protected static ?string $navigationLabel = 'Rekap Rapor';
    protected static ?string $modelLabel = 'Rapor';
    protected static ?string $pluralModelLabel = 'Rekap Rapor';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    public static function getNavigationGroup(): ?string
    {
        return auth()->check() && auth()->user()->hasRole('teacher') ? 'Saya sebagai Wali Kelas' : 'Akademik';
    }

    // Hanya Wali Kelas dan Admin yang bisa mengakses
    public static function shouldRegisterNavigation(): bool
    {
        if (!auth()->check())
            return false;

        $user = auth()->user();

        // Staf pemantau (punya permission view_rapor di seeder): Admin, Kepala
        // Sekolah, dan Guru BK. Sebelumnya nav disembunyikan meski permission ada
        // (permission shadowing, Audit MED). Kini nav selaras dengan hak akses.
        if ($user->hasAnyRole(['super_admin', 'admin', 'headmaster', 'guru_bk'])) {
            return true;
        }

        // Guru hanya melihat menu ini jika ia (pernah) menjadi Wali Kelas.
        if ($user->hasRole('teacher')) {
            return ClassHomeroom::where('teacher_id', $user->teacher?->id)
                ->exists();
        }

        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['classroom', 'academicPeriod', 'teacher'])
            // ✅ PERBAIKAN N+1: hitung "siswa aktif" via subquery berkorelasi di
            // level SQL, bukan Enrollment::count() per baris (getStateUsing).
            // Korelasi ke academic_period_id baris ClassHomeroom memastikan
            // hanya siswa pada TAHUN AJARAN kelas tersebut yang dihitung.
            ->withCount(['enrollments as active_students_count' => fn($q) => $q
                ->where('enrollments.status', 'active')
                ->whereColumn('enrollments.academic_period_id', 'class_homerooms.academic_period_id')]);
        // CATATAN (Audit MED-1): filter is_active DIHAPUS agar rekap rapor tahun
        // ajaran lampau tetap bisa dibuka & dicetak ulang (read-only). Proteksi
        // penulisan pada periode non-aktif ditegakkan di ViewRapor (aksi mutasi
        // disembunyikan), sehingga histori tak perlu diaktifkan ulang (yang akan
        // mencairkan freeze) hanya untuk sekadar mencetak.

        // Guru melihat kelas yang ia pegang/pernah pegang sebagai Wali Kelas
        // di seluruh tahun ajaran (bukan hanya yang sedang menjabat), agar
        // rapor historis kelas asuhannya tetap terjangkau.
        if (auth()->check() && auth()->user()->hasRole('teacher')) {
            $query->where('teacher_id', auth()->user()->teacher?->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Tahun Ajaran')
                    ->sortable(),

                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Wali Kelas')
                    ->sortable()
                    // Sembunyikan kolom ini jika yang login adalah Wali Kelas itu sendiri
                    ->visible(fn() => !auth()->user()->hasRole('teacher')),

                // Jumlah siswa aktif di kelas ini.
                // Membaca atribut hasil withCount berkorelasi (0 query tambahan per baris).
                Tables\Columns\TextColumn::make('active_students_count')
                    ->label('Jumlah Siswa')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn ($state): string => (int) ($state ?? 0) . ' siswa'),
            ])
            ->actions([
                Tables\Actions\Action::make('lihat_rapor')
                    ->label('Lihat Rekap')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(
                        fn(ClassHomeroom $record): string =>
                        static::getUrl('view', ['record' => $record])
                    ),
            ])
            ->emptyStateHeading('Data Rekap Rapor Belum Siap')
            ->emptyStateDescription('Penetapan Wali Kelas aktif pada Tahun Ajaran aktif belum tersedia. Silakan lengkapi data akademik terlebih dahulu.')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRapors::route('/'),
            'view' => Pages\ViewRapor::route('/{record}'),
        ];
    }
}