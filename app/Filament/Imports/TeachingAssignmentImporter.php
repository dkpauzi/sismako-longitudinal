<?php

namespace App\Filament\Imports;

use App\Models\TeachingAssignment;
use App\Models\AcademicPeriod;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Classroom;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class TeachingAssignmentImporter extends Importer
{
    protected static ?string $model = TeachingAssignment::class;

    public static function getColumns(): array
    {
        return [
            // KEMBALI DIGABUNG
            ImportColumn::make('tahun_ajaran')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('2025/2026')
                ->fillRecordUsing(fn() => null), // <-- INI PAHLAWANNYA

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

            ImportColumn::make('metode_penilaian')
                ->rules(['nullable', 'in:average,weighting,percentage'])
                ->example('average')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('kktp')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0', 'max:100'])
                ->example('75')
                ->fillRecordUsing(fn() => null),
        ];
    }

    public function resolveRecord(): ?TeachingAssignment
    {
        $data = $this->data;

        // 1. Cari Academic Period dengan Memotong String
        $tahunAjaran = trim($data['tahun_ajaran'] ?? '');
        $semesterInput = trim($data['semester'] ?? '');
        $semester = in_array(strtolower($semesterInput), ['ganjil', 'odd']) ? 'odd' : 'even';

        // Memotong "2025/2026" untuk mengambil "2025" saja
        $startYear = explode('/', $tahunAjaran)[0] ?? null;

        $period = AcademicPeriod::where('start_year', $startYear)
            ->where('semester', $semester)
            ->first();

        if (!$period) {
            throw new \Filament\Actions\Imports\Exceptions\RowImportFailedException("Tahun Ajaran/Semester '{$tahunAjaran} {$semesterInput}' tidak ditemukan.");
        }

        // 2. Cari Guru
        $guru = trim($data['guru'] ?? '');
        $teacher = Teacher::whereRaw('LOWER(name) = ?', [strtolower($guru)])->first();

        if (!$teacher) {
            throw new \Filament\Actions\Imports\Exceptions\RowImportFailedException("Guru '{$guru}' tidak ditemukan di database.");
        }

        // 3. Cari Mapel
        $mapel = trim($data['mata_pelajaran'] ?? '');
        $subject = Subject::whereRaw('LOWER(name) = ?', [strtolower($mapel)])->first();

        if (!$subject) {
            throw new \Filament\Actions\Imports\Exceptions\RowImportFailedException("Mapel '{$mapel}' tidak ditemukan di database.");
        }

        // 4. Cari Kelas
        $kelas = trim($data['kelas'] ?? '');
        $classroom = Classroom::whereRaw('LOWER(name) = ?', [strtolower($kelas)])->first();

        if (!$classroom) {
            throw new \Filament\Actions\Imports\Exceptions\RowImportFailedException("Kelas '{$kelas}' tidak ditemukan di database.");
        }

        // Jika semua ketemu, masukkan data
        $assignment = TeachingAssignment::firstOrNew([
            'academic_period_id' => $period->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
        ]);

        $assignment->grading_formula = trim($data['metode_penilaian'] ?? 'average');
        $assignment->kktp = !empty($data['kktp']) ? (int) $data['kktp'] : 75;

        return $assignment;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Proses import SK Mengajar telah selesai. ' . number_format($import->successful_rows) . ' baris berhasil dimasukkan.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' Namun, ada ' . number_format($failedRowsCount) . ' baris yang gagal diimpor. Silakan unduh file log error untuk melihat detailnya.';
        }

        return $body;
    }
}