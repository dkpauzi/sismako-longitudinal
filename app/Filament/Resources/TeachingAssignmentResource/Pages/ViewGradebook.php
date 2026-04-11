<?php

namespace App\Filament\Resources\TeachingAssignmentResource\Pages;

use App\Filament\Resources\TeachingAssignmentResource;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use App\Models\Enrollment;

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

        return [
            'students' => $students,
            'sumatif' => $sumatif,
            'formatifPoin' => $formatifPoin,
            'formatifDeskripsi' => $formatifDeskripsi,
        ];
    }
}