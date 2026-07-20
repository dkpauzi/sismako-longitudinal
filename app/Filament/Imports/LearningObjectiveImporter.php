<?php

namespace App\Filament\Imports;

use App\Models\LearningObjective;
use App\Models\AcademicPeriod;
use App\Models\Teacher;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Auth;

class LearningObjectiveImporter extends Importer
{
    protected static ?string $model = LearningObjective::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('subject')
                ->requiredMapping()
                ->relationship(resolveUsing: 'name')
                ->rules(['required'])
                ->example('Pendidikan Pancasila (Gunakan Nama Mapel)'),

            ImportColumn::make('teacher')
                ->relationship(resolveUsing: 'name')
                // Opsional. Guru tidak wajib mengisi kolom ini.
                ->rules(['nullable'])
                ->example('Budi Santoso, S.Pd (Opsional, Gunakan Nama Guru)'),

            ImportColumn::make('grade_level')
                ->numeric()
                // Batasan SMP: hanya kelas 7, 8, 9.
                ->rules(['nullable', 'integer', 'in:7,8,9'])
                ->example('7 (Pilihan: 7, 8, 9)'),

            ImportColumn::make('phase')
                // Batasan SMP: hanya Fase D.
                ->rules(['nullable', 'in:D'])
                ->example('D (SMP hanya Fase D)'),

            ImportColumn::make('code')
                ->rules(['nullable', 'string'])
                ->example('MTK-7-1-TP1 (Format: MAPEL-KELAS-SEMESTER-NOMOR)'),

            ImportColumn::make('content')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('Menganalisis sistem pencernaan manusia'),

            ImportColumn::make('attribute')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('Sistem Pencernaan Manusia'),
        ];
    }

    /**
     * Apakah baris CSV benar-benar kosong (ghost row dari Excel)?
     * Semua kolom inti (subject/code/content/attribute) blank → baris hantu.
     * Diekstrak sebagai static agar bisa diuji unit tanpa pipeline Filament.
     */
    public static function isEmptyRow(array $data): bool
    {
        return blank($data['subject'] ?? null)
            && blank($data['code'] ?? null)
            && blank($data['content'] ?? null)
            && blank($data['attribute'] ?? null);
    }

    public function resolveRecord(): ?LearningObjective
    {
        $data = $this->data;

        // Lewati baris HANTU secara diam-diam. Filament v3 memanggil resolveRecord()
        // SEBELUM validateData(); return null → baris di-skip (tidak dihitung gagal,
        // tidak disimpan). Ini menghapus false-positive "Kolom subject wajib diisi"
        // dari baris kosong Excel, sementara baris valid tetap diimpor & baris yang
        // benar-benar cacat (sebagian terisi) tetap divalidasi seperti biasa.
        if (static::isEmptyRow($data)) {
            return null;
        }

        $user = Auth::user();
        
        $teacherId = null;

        if ($user && $user->hasRole('teacher')) {
            // If user is a Teacher, ignore Excel and auto-inject their teacher ID
            $teacherId = $user->teacher?->id;
        } else {
            // If Admin/Super Admin, use the resolved teacher from Excel
            $resolvedTeacherId = $data['teacher'] ?? null; // Filament resolves relationships into foreign keys
            if ($resolvedTeacherId) {
                $teacherId = $resolvedTeacherId;
            } else {
                // Set explicitly to null instead of a random fallback
                $teacherId = null;
            }
        }

        // Retrieve active academic period to automatically assign
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        return LearningObjective::firstOrNew([
            'subject_id' => $data['subject'],
            'code' => $data['code'] ?? null,
            'content' => $data['content'],
        ])->fill([
            'teacher_id' => $teacherId,
            'academic_period_id' => $activePeriod?->id ?? 1,
            'grade_level' => $data['grade_level'] ?? null,
            'phase' => $data['phase'] ?? 'D',
            'attribute' => $data['attribute'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import Tujuan Pembelajaran selesai. ' . number_format($import->successful_rows) . ' baris berhasil diimpor.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal diimpor.';
        }

        return $body;
    }
}
