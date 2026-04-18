<?php
// app/Filament/Resources/RaporResource/Pages/ViewRapor.php

namespace App\Filament\Resources\RaporResource\Pages;

use App\Filament\Resources\RaporResource;
use App\Models\AttendanceSummary;
use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Models\TeachingAssignment;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use App\Services\DescriptionGeneratorService;
use App\Services\RaporExportService;

class ViewRapor extends ViewRecord
{
    protected static string $resource = RaporResource::class;

    protected static string $view = 'filament.resources.rapor-resource.pages.view-rapor';
    //protected static string $view = 'filament.pages.view-rapor';

    /**
     * Siapkan semua data yang dibutuhkan view Blade.
     * Semua query berat dilakukan di sini, bukan di Blade,
     * agar view tetap bersih dan mudah dibaca.
     */
    public function getViewData(): array
    {
        /** @var ClassHomeroom $homeroom */
        $homeroom = $this->record;

        $classroomId = $homeroom->classroom_id;
        $academicPeriodId = $homeroom->academic_period_id;
        $semester = $homeroom->academicPeriod->semester;

        // 1. Ambil semua siswa aktif di kelas ini, urut abjad
        $enrollments = Enrollment::where('classroom_id', $classroomId)
            ->where('academic_period_id', $academicPeriodId)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.name');

        $studentIds = $enrollments->pluck('student_id');

        // 2. Ambil semua SK Mengajar yang aktif di kelas ini pada periode ini
        //    Kecuali mapel Kokurikuler (P5) — ditampilkan terpisah
        $teachingAssignments = TeachingAssignment::where('classroom_id', $classroomId)
            ->where('academic_period_id', $academicPeriodId)
            ->with(['subject'])
            ->get();

        // ✅ PERBAIKAN: Gunakan filter() karena is_kokurikuler adalah accessor (method),
        // bukan kolom database. Collection->where() tidak bisa resolve accessor.
        $akademikAssignments = $teachingAssignments->filter(fn($ta) => !$ta->subject->is_kokurikuler);
        $kokurikulerAssignments = $teachingAssignments->filter(fn($ta) => $ta->subject->is_kokurikuler);

        // 3. Ambil semua final_grades sekaligus (hindari N+1)
        $finalGrades = FinalGrade::whereIn('student_id', $studentIds)
            ->whereIn('teaching_assignment_id', $teachingAssignments->pluck('id'))
            ->where('semester', $semester)
            ->get()
            ->groupBy('student_id'); // Struktur: [student_id => Collection of FinalGrade]

        // 4. Ambil semua attendance_summaries sekaligus
        $attendanceSummaries = AttendanceSummary::whereIn('student_id', $studentIds)
            ->whereIn('teaching_assignment_id', $akademikAssignments->pluck('id'))
            ->where('semester', $semester)
            ->get()
            ->groupBy('student_id');

        // 5. Hitung progress input nilai per Guru Mapel
        $progressGuruMapel = [];
        $totalSiswaDiKelas = $enrollments->count();

        foreach ($akademikAssignments as $ta) {
            $gradedCount = 0;
            if ($totalSiswaDiKelas > 0) {
                foreach ($enrollments as $enrollment) {
                    $studentGrades = $finalGrades[$enrollment->student_id] ?? collect();
                    $grade = $studentGrades->where('teaching_assignment_id', $ta->id)->first();
                    if ($grade && $grade->final_score !== null) {
                        $gradedCount++;
                    }
                }
            }
            
            $progressGuruMapel[] = [
                'teacher' => $ta->teacher->name,
                'subject' => $ta->subject->name,
                'graded_count' => $gradedCount,
                'total_students' => $totalSiswaDiKelas,
                'percentage' => $totalSiswaDiKelas > 0 ? round(($gradedCount / $totalSiswaDiKelas) * 100) : 0,
            ];
        }

        return [
            'homeroom' => $homeroom,
            'enrollments' => $enrollments,
            'akademikAssignments' => $akademikAssignments,
            'kokurikulerAssignments' => $kokurikulerAssignments,
            'finalGrades' => $finalGrades,
            'attendanceSummaries' => $attendanceSummaries,
            'progressGuruMapel' => collect($progressGuruMapel)->sortBy('percentage')->values(),
            'semester' => $semester,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_kelas')
                ->label('Export Rapor Semua Siswa')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    $service = new RaporExportService();
                    $filePath = $service->exportWholeClass($this->record);

                    return response()->download($filePath)->deleteFileAfterSend(true);
                }),

            Action::make('export_per_siswa')
                ->label('Export Per Siswa')
                ->icon('heroicon-o-user')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\Select::make('student_id')
                        ->label('Pilih Siswa')
                        ->options(function () {
                            return \App\Models\Enrollment::where('classroom_id', $this->record->classroom_id)
                                ->where('academic_period_id', $this->record->academic_period_id)
                                ->where('status', 'active')
                                ->with('student')
                                ->get()
                                ->pluck('student.name', 'student.id');
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $service = new RaporExportService();
                    $filePath = $service->exportSingleStudent($this->record, $data['student_id']);

                    return response()->download($filePath)->deleteFileAfterSend(true);
                }),
            Action::make('kunci_semua')
                ->label('Kunci Semua Nilai')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Kunci Semua Nilai Rapor?')
                ->modalDescription(
                    'Setelah dikunci, nilai tidak akan berubah meskipun guru menginput nilai baru. ' .
                    'Lakukan ini hanya saat rapor siap dicetak.'
                )
                ->action(function () {
                    $homeroom = $this->record;
                    $semester = $homeroom->academicPeriod->semester;
                    $studentIds = Enrollment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->where('status', 'active')
                        ->pluck('student_id');

                    $assignmentIds = TeachingAssignment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->pluck('id');

                    FinalGrade::whereIn('student_id', $studentIds)
                        ->whereIn('teaching_assignment_id', $assignmentIds)
                        ->where('semester', $semester)
                        ->update([
                            'is_locked' => true,
                            'locked_at' => now(),
                        ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Semua nilai berhasil dikunci')
                        ->success()
                        ->send();
                }),

            Action::make('buka_kunci')
                ->label('Buka Kunci Nilai')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Buka Kunci Nilai Rapor?')
                ->modalDescription('Nilai akan bisa diperbarui kembali oleh guru.')
                ->action(function () {
                    $homeroom = $this->record;
                    $semester = $homeroom->academicPeriod->semester;
                    $studentIds = Enrollment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->where('status', 'active')
                        ->pluck('student_id');

                    $assignmentIds = TeachingAssignment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->pluck('id');

                    FinalGrade::whereIn('student_id', $studentIds)
                        ->whereIn('teaching_assignment_id', $assignmentIds)
                        ->where('semester', $semester)
                        ->update([
                            'is_locked' => false,
                            'locked_at' => null,
                        ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Kunci nilai berhasil dibuka')
                        ->warning()
                        ->send();
                }),
            Action::make('generate_narasi')
                ->label('Generate Semua Deskripsi')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Generate Deskripsi Otomatis?')
                ->modalDescription(
                    'Sistem akan membuat narasi rapor untuk semua siswa di semua mapel ' .
                    'berdasarkan data nilai dan TP yang sudah diinput. ' .
                    'Deskripsi yang sudah ditulis manual AKAN DITIMPA. Lanjutkan?'
                )
                ->action(function () {
                    $homeroom = $this->record;
                    $semester = $homeroom->academicPeriod->semester;
                    $service = new DescriptionGeneratorService();

                    // Ambil semua siswa aktif di kelas ini
                    $studentIds = Enrollment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->where('status', 'active')
                        ->pluck('student_id');

                    // Ambil semua SK Mengajar akademik (bukan P5) di kelas ini
                    // ✅ PERBAIKAN N+1: Pre-load assessments + grades + learningObjectives
                    // agar DescriptionGeneratorService tidak perlu query ulang per siswa.
                    $assignments = TeachingAssignment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->whereHas('subject', fn($q) => $q->where('is_kokurikuler', false))
                        ->with([
                            'subject',
                            'academicPeriod',
                            'assessments.learningObjectives',
                            'assessments.grades' => fn($q) => $q->whereIn('student_id', $studentIds)->whereNotNull('score'),
                        ])
                        ->get();

                    $count = 0;

                    foreach ($assignments as $assignment) {
                        foreach ($studentIds as $studentId) {
                            $narrative = $service->generate($assignment, $studentId);

                            FinalGrade::updateOrCreate(
                                [
                                    'student_id' => $studentId,
                                    'teaching_assignment_id' => $assignment->id,
                                    'semester' => $semester,
                                ],
                                [
                                    'narrative_description' => $narrative,
                                ]
                            );

                            $count++;
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->title("Berhasil generate {$count} deskripsi")
                        ->body('Semua narasi rapor sudah dibuat. Silakan review sebelum dikunci.')
                        ->success()
                        ->send();
                }),
        ];
    }
}