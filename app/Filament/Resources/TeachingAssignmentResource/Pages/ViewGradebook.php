<?php

namespace App\Filament\Resources\TeachingAssignmentResource\Pages;

use App\Filament\Resources\TeachingAssignmentResource;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Actions\Action;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Services\DescriptionGeneratorService;

class ViewGradebook extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TeachingAssignmentResource::class;

    protected static string $view = 'filament.resources.teaching-assignment-resource.pages.view-gradebook';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Buku Nilai: ' . $this->record->subject->name . ' - ' . $this->record->classroom->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_narasi')
                ->label('Auto-Generate Deskripsi Rapor')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Generate Deskripsi Otomatis?')
                ->modalDescription(
                    'Sistem akan menyusun narasi rapor untuk semua siswa berdasarkan nilai ' .
                    'dan capaian Tujuan Pembelajaran (TP). Deskripsi manual yang sudah ada akan ditimpa.'
                )
                ->action(function () {
                    $assignment = $this->record;
                    $semester = $assignment->academicPeriod->semester;
                    
                    // Ambil siswa yang terdaftar di kelas
                    $studentIds = Enrollment::where('classroom_id', $assignment->classroom_id)
                        ->where('academic_period_id', $assignment->academic_period_id)
                        ->where('status', 'active')
                        ->pluck('student_id');

                    // Cegah generate jika belum ada nilai / assignment belum diload relasinya dengan baik
                    // Untuk optimalisasi, kita tetap manfaatkan Service
                    $service = new DescriptionGeneratorService();
                    
                    $count = 0;
                    foreach ($studentIds as $studentId) {
                        $narrative = $service->generate($assignment, $studentId);

                        // Lewat snapshot(): guard penguncian/override terpusat (Audit 3.6).
                        $finalGrade = FinalGrade::snapshot(
                            $studentId,
                            $assignment->id,
                            $semester,
                            ['narrative_description' => $narrative]
                        );

                        // snapshot() mengembalikan record existing tanpa perubahan bila
                        // terkunci/override — baris seperti itu tidak dihitung sebagai update.
                        if ($finalGrade && !$finalGrade->is_locked && !$finalGrade->is_manual_override) {
                            $count++;
                        }
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Berhasil')
                        ->body("Deskripsi rapor untuk {$count} siswa berhasil digenerate otomatis.")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        // 1. Ambil Siswa yang Aktif di Kelas ini
        $students = Enrollment::where('classroom_id', $this->record->classroom_id)
            ->where('academic_period_id', $this->record->academic_period_id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.name');

        // 2. Ambil semua Asesmen, urutkan berdasarkan tanggal
        // Kita eager-load grades agar sistem tidak lemot saat menampilkan tabel
        $assessments = $this->record->assessments()->with('grades')->orderBy('date')->get();

        // 3. Kelompokkan Asesmen agar kolom tabel rapi
        $sumatif = $assessments->whereIn('category', ['sumatif_lingkup_materi', 'sumatif_akhir_semester']);
        $formatifPoin = $assessments->where('category', 'formatif_poin');
        $formatifDeskripsi = $assessments->where('category', 'formatif_deskripsi');

        // 4. ✅ PERBAIKAN N+1: Pre-compute nilai akhir di PHP, bukan per-row di Blade.
        //    Sebelumnya calculateFinalGrade() dipanggil per siswa di dalam @forelse loop,
        //    menyebabkan N query tambahan (30 siswa = 30 query). Sekarang dihitung sekali di sini.
        $finalGrades = [];
        foreach ($students as $enrollment) {
            $finalGrades[$enrollment->student_id] = $this->record->calculateFinalGrade(
                $enrollment->student_id
            );
        }

        return [
            'students' => $students,
            'sumatif' => $sumatif,
            'formatifPoin' => $formatifPoin,
            'formatifDeskripsi' => $formatifDeskripsi,
            'finalGrades' => $finalGrades,
        ];
    }
}