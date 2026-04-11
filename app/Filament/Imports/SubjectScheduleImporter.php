<?php

namespace App\Filament\Imports;

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
                ->rules(['required', 'in:Ganjil,Genap,odd,even'])
                ->example('Ganjil')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('guru')
                ->requiredMapping()
                ->rules(['required'])
                ->example('Yuli Asman, S.Sos')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('mata_pelajaran')
                ->requiredMapping()
                ->rules(['required'])
                ->example('Pendidikan Pancasila')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('kelas')
                ->requiredMapping()
                ->rules(['required'])
                ->example('Kelas 7.1')
                ->fillRecordUsing(fn() => null),

            // --- DATA INTI JADWAL ---
            ImportColumn::make('hari')
                ->requiredMapping()
                ->rules(['required', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu'])
                ->example('Senin')
                ->fillRecordUsing(fn() => null), // Kita handle manual di resolveRecord

            ImportColumn::make('jam_mulai')
                ->requiredMapping()
                ->rules(['required', 'date_format:H:i'])
                ->example('07:30')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('jam_selesai')
                ->requiredMapping()
                ->rules(['required', 'date_format:H:i', 'after:jam_mulai'])
                ->example('08:50')
                ->fillRecordUsing(fn() => null),
        ];
    }

    public function resolveRecord(): ?SubjectSchedule
    {
        $data = $this->data;

        // 1. CARI PERIODE AKADEMIK
        $tahunAjaran = trim($data['tahun_ajaran'] ?? '');
        $semesterInput = trim($data['semester'] ?? '');
        $semester = in_array(strtolower($semesterInput), ['ganjil', 'odd']) ? 'odd' : 'even';
        $startYear = explode('/', $tahunAjaran)[0] ?? null;

        $period = AcademicPeriod::where('start_year', $startYear)->where('semester', $semester)->first();
        if (!$period) {
            throw new RowImportFailedException("Tahun Ajaran/Semester '{$tahunAjaran} {$semesterInput}' tidak ditemukan.");
        }

        // 2. CARI MASTER DATA (GURU, MAPEL, KELAS)
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

        // 3. CARI SK MENGAJAR (INDUKNYA)
        $assignment = TeachingAssignment::where('academic_period_id', $period->id)
            ->where('teacher_id', $teacher->id)
            ->where('subject_id', $subject->id)
            ->where('classroom_id', $classroom->id)
            ->first();

        // JIKA SK MENGAJAR BELUM DIBUAT/DIIMPORT, TOLAK JADWALNYA!
        if (!$assignment) {
            throw new RowImportFailedException("SK Mengajar untuk Guru '{$guru}' mengajar '{$mapel}' di '{$kelas}' BELUM ADA. Silakan import SK Mengajarnya terlebih dahulu.");
        }

        // 4. MASUKKAN DATA JADWAL (Cegah Duplikat Jadwal di Hari dan Jam yang sama)
        $hari = ucfirst(strtolower(trim($data['hari'] ?? '')));
        $jamMulai = trim($data['jam_mulai'] ?? '');

        $schedule = SubjectSchedule::firstOrNew([
            'teaching_assignment_id' => $assignment->id,
            'day' => $hari,
            'start_time' => $jamMulai,
        ]);

        $schedule->end_time = trim($data['jam_selesai'] ?? '');

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