<?php
// app/Filament/Imports/StudentSubjectEnrollmentImporter.php

namespace App\Filament\Imports;

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class StudentSubjectEnrollmentImporter extends Importer
{
    protected static ?string $model = StudentSubjectEnrollment::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nisn')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('0012345678')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('kode_mapel')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('MTK')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('kelas')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('Kelas 10.1')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('peminatan')
                ->rules(['nullable', 'string'])
                ->example('IPA')
                ->fillRecordUsing(fn() => null),
        ];
    }

    public function resolveRecord(): ?StudentSubjectEnrollment
    {
        $data   = $this->data;
        $period = AcademicPeriod::where('is_active', true)->first();

        if (!$period) {
            throw new RowImportFailedException('Tidak ada tahun ajaran aktif.');
        }

        // Cari siswa berdasarkan NISN
        $student = Student::where('nisn', trim($data['nisn']))->first();
        if (!$student) {
            throw new RowImportFailedException("Siswa dengan NISN '{$data['nisn']}' tidak ditemukan.");
        }

        // Cari subject berdasarkan kode
        $subject = Subject::where('code', strtoupper(trim($data['kode_mapel'])))->first();
        if (!$subject) {
            throw new RowImportFailedException("Mapel dengan kode '{$data['kode_mapel']}' tidak ditemukan.");
        }

        // Cari teaching assignment
        $assignment = TeachingAssignment::where('academic_period_id', $period->id)
            ->where('subject_id', $subject->id)
            ->whereHas('classroom', fn($q) =>
                $q->whereRaw('LOWER(name) = ?', [strtolower(trim($data['kelas']))])
            )
            ->first();

        if (!$assignment) {
            throw new RowImportFailedException(
                "SK Mengajar untuk mapel '{$data['kode_mapel']}' di kelas '{$data['kelas']}' tidak ditemukan."
            );
        }

        return StudentSubjectEnrollment::updateOrCreate(
            [
                'student_id'             => $student->id,
                'teaching_assignment_id' => $assignment->id,
            ],
            ['note' => trim($data['peminatan'] ?? '')]
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = number_format($import->successful_rows) . ' mapel pilihan berhasil didaftarkan.';
        if ($failed = $import->getFailedRowsCount()) {
            $body .= " {$failed} baris gagal.";
        }
        return $body;
    }
}