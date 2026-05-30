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
                // Strictly optional. Teachers don't need to fill this in.
                ->rules(['nullable'])
                ->example('Budi Santoso, S.Pd (Opsional, Gunakan Nama Guru)'),

            ImportColumn::make('grade_level')
                ->numeric()
                ->rules(['nullable', 'integer'])
                ->example('7'),

            ImportColumn::make('phase')
                ->rules(['nullable', 'string'])
                ->example('D (Pilihan: A, B, C, D, E, F)'),

            ImportColumn::make('code')
                ->rules(['nullable', 'string'])
                ->example('TP.1.1'),

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

    public function resolveRecord(): ?LearningObjective
    {
        $data = $this->data;
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
