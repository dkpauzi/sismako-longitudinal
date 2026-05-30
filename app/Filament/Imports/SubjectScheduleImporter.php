<?php

namespace App\Filament\Imports;

use Carbon\Carbon;
use Exception;
use App\Models\SubjectSchedule;
use App\Models\TeachingAssignment;
use App\Models\AcademicPeriod;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Classroom;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class SubjectScheduleImporter extends Importer
{
    protected static ?string $model = SubjectSchedule::class;

    /**
     * Daftar hari yang valid (sesuai enum di migration subject_schedules).
     */
    private const VALID_DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public static function getColumns(): array
    {
        return [
            // --- DATA PENCARIAN SK MENGAJAR ---
            ImportColumn::make('tahun_ajaran')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('2025/2026')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('semester')
                ->requiredMapping()
                ->rules(['required'])
                ->example('Ganjil (Pilihan: Ganjil, Genap)')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('guru')
                ->requiredMapping()
                ->rules(['required'])
                ->example('Yuli Asman, S.Sos (Gunakan Nama Guru)')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('mata_pelajaran')
                ->requiredMapping()
                ->rules(['required'])
                ->example('Pendidikan Pancasila (Gunakan Nama Mapel)')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('kelas')
                ->requiredMapping()
                ->rules(['required'])
                ->example('Kelas 7.1 (Gunakan Nama Kelas)')
                ->fillRecordUsing(fn() => null),

            // --- DATA INTI JADWAL ---
            ImportColumn::make('hari')
                ->requiredMapping()
                ->rules(['required'])
                ->example('Senin (Pilihan: Senin, Selasa, Rabu, Kamis, Jumat, Sabtu, Minggu)')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('jam_mulai')
                ->requiredMapping()
                ->rules(['required'])
                ->example('07:30 (Format HH:MM)')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('jam_selesai')
                ->requiredMapping()
                ->rules(['required'])
                ->example('08:50 (Format HH:MM)')
                ->fillRecordUsing(fn() => null),
        ];
    }

    /**
     * Triple-Layer Time Sanitization.
     * Menangani: DateTimeInterface (OpenSpout), Excel fractional float, dan String H:i / H:i:s.
     */
    private function sanitizeTime(mixed $rawTime, string $columnLabel): ?string
    {
        if (empty($rawTime)) {
            return null;
        }

        try {
            // Scenario A: OpenSpout/Excel mengirim objek DateTime
            if ($rawTime instanceof \DateTimeInterface) {
                return $rawTime->format('H:i:s');
            }

            // Scenario B: Excel fractional float (jam disimpan sebagai fraksi dari 24 jam)
            // Contoh: 07:30 = 0.3125, 13:00 = 0.541667
            if (is_numeric($rawTime) && (float) $rawTime >= 0 && (float) $rawTime < 1) {
                $totalSeconds = round((float) $rawTime * 86400);
                $hours = intdiv((int) $totalSeconds, 3600);
                $minutes = intdiv((int) $totalSeconds % 3600, 60);
                $seconds = (int) $totalSeconds % 60;
                return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            }

            // Scenario C: String biasa ("07:30", "07:30:00", "7:30")
            $cleanTime = trim((string) $rawTime);

            // Coba parsing sebagai H:i:s atau H:i
            $parsed = Carbon::createFromFormat('H:i:s', $cleanTime);
            if (!$parsed) {
                $parsed = Carbon::createFromFormat('H:i', $cleanTime);
            }

            if ($parsed) {
                return $parsed->format('H:i:s');
            }

            // Fallback terakhir: Carbon::parse umum
            return Carbon::parse($cleanTime)->format('H:i:s');
        } catch (Exception $e) {
            throw new RowImportFailedException("Gagal: Format waktu '{$rawTime}' pada kolom '{$columnLabel}' tidak dikenali. Gunakan format HH:MM (Contoh: 07:30).");
        }
    }

    public function resolveRecord(): ?SubjectSchedule
    {
        $data = $this->data;

        // ═══════════════════════════════════════════════════════════
        // 1. CARI PERIODE AKADEMIK
        // ═══════════════════════════════════════════════════════════
        $tahunAjaran = trim($data['tahun_ajaran'] ?? '');
        $semesterInput = trim($data['semester'] ?? '');
        $semester = in_array(strtolower($semesterInput), ['ganjil', 'odd']) ? 'odd' : 'even';
        $startYear = explode('/', $tahunAjaran)[0] ?? null;

        $period = AcademicPeriod::where('start_year', $startYear)->where('semester', $semester)->first();
        if (!$period) {
            throw new RowImportFailedException("Tahun Ajaran/Semester '{$tahunAjaran} {$semesterInput}' tidak ditemukan.");
        }

        // ═══════════════════════════════════════════════════════════
        // 2. CARI MASTER DATA (GURU, MAPEL, KELAS) — case-insensitive
        // ═══════════════════════════════════════════════════════════
        $guru = trim($data['guru'] ?? '');
        $teacher = Teacher::whereRaw('LOWER(name) = ?', [strtolower($guru)])->first();
        if (!$teacher)
            throw new RowImportFailedException("Guru '{$guru}' tidak ditemukan.");

        $mapel = trim($data['mata_pelajaran'] ?? '');
        $subject = Subject::whereRaw('LOWER(name) = ?', [strtolower($mapel)])->first();
        if (!$subject)
            throw new RowImportFailedException("Mapel '{$mapel}' tidak ditemukan.");

        $kelas = trim($data['kelas'] ?? '');
        $classroom = Classroom::whereRaw('LOWER(name) = ?', [strtolower($kelas)])->first();
        if (!$classroom)
            throw new RowImportFailedException("Kelas '{$kelas}' tidak ditemukan.");

        // ═══════════════════════════════════════════════════════════
        // 3. CARI SK MENGAJAR (INDUKNYA)
        // ═══════════════════════════════════════════════════════════
        $assignment = TeachingAssignment::where('academic_period_id', $period->id)
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $subject->id)
            ->where('classroom_id', $classroom->id)
            ->first();

        // JIKA SK MENGAJAR BELUM DIBUAT/DIIMPORT, TOLAK JADWALNYA!
        if (!$assignment) {
            throw new RowImportFailedException("SK Mengajar untuk Guru '{$guru}' mengajar '{$mapel}' di '{$kelas}' BELUM ADA. Silakan import SK Mengajarnya terlebih dahulu.");
        }

        // ═══════════════════════════════════════════════════════════
        // 4. VALIDASI ENUM HARI (STRICT)
        // ═══════════════════════════════════════════════════════════
        $hari = ucfirst(strtolower(trim($data['hari'] ?? '')));
        if (!in_array($hari, self::VALID_DAYS)) {
            throw new RowImportFailedException("Hari '{$hari}' tidak valid. Gunakan salah satu dari: " . implode(', ', self::VALID_DAYS));
        }

        // ═══════════════════════════════════════════════════════════
        // 5. SANITASI FORMAT WAKTU (Triple-Layer)
        // ═══════════════════════════════════════════════════════════
        $jamMulai = $this->sanitizeTime($data['jam_mulai'] ?? null, 'Jam Mulai');
        $jamSelesai = $this->sanitizeTime($data['jam_selesai'] ?? null, 'Jam Selesai');

        if (!$jamMulai || !$jamSelesai) {
            throw new RowImportFailedException("Jam Mulai dan Jam Selesai wajib diisi.");
        }

        // ═══════════════════════════════════════════════════════════
        // 6. MASUKKAN DATA JADWAL (Cegah Duplikat Jadwal di Hari dan Jam yang sama)
        // ═══════════════════════════════════════════════════════════
        $schedule = SubjectSchedule::firstOrNew([
            'teaching_assignment_id' => $assignment->id,
            'day' => $hari,
            'start_time' => $jamMulai,
        ]);

        $schedule->end_time = $jamSelesai;

        return $schedule;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Proses import Jadwal Pelajaran telah selesai. ' . number_format($import->successful_rows) . ' baris berhasil dimasukkan.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' Namun, ada ' . number_format($failedRowsCount) . ' baris yang gagal diimpor.';
        }

        return $body;
    }
}