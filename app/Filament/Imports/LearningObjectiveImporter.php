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
                ->rules(['required']),

            ImportColumn::make('teacher')
                ->relationship(resolveUsing: 'name')
                // Strictly optional. Teachers don't need to fill this in.
                ->rules(['nullable']),

            ImportColumn::make('grade_level')
                ->numeric()
                ->rules(['nullable', 'integer']),

            ImportColumn::make('phase')
                ->rules(['nullable', 'string', 'in:A,B,C,D,E,F']),

            ImportColumn::make('code')
                ->rules(['nullable', 'string']),

            ImportColumn::make('content')
                ->requiredMapping()
                ->rules(['required', 'string']),

            ImportColumn::make('attribute')
                ->requiredMapping()
                ->rules(['required', 'string']),
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
                // Graceful fallback for Admin if they leave it blank.
                // We fallback to the first teacher (or you should make `teacher_id` nullable in DB)
                $fallbackTeacher = Teacher::first();
                $teacherId = $fallbackTeacher ? $fallbackTeacher->id : null;
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
