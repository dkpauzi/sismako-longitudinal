<?php

namespace App\Services;

use App\Models\AttendanceSummary;
use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Models\KokurikulerGrade;
use App\Models\StudentSubjectEnrollment;
use App\Models\TeachingAssignment;
use App\Services\SchoolIdentityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class RaporExportService
{
    public function getRaporData(ClassHomeroom $homeroom, int $studentId): array
    {
        $classroom = $homeroom->classroom;
        $period = $homeroom->academicPeriod;
        $semester = $period->semester;

        $enrollment = Enrollment::where('classroom_id', $classroom->id)
            ->where('academic_period_id', $period->id)
            ->where('student_id', $studentId)
            ->with('student')
            ->firstOrFail();

        $student = $enrollment->student;

        // Fetch teaching assignments (akademik only)
        $akademikAssignments = TeachingAssignment::where('classroom_id', $classroom->id)
            ->where('academic_period_id', $period->id)
            ->whereHas('subject', fn($q) => $q->where('type', '!=', 'kokurikuler')->where('type', '!=', 'extracurricular'))
            ->with('subject')
            ->get();

        // Final Grades (must include final_score and narrative_description)
        $finalGrades = FinalGrade::where('student_id', $studentId)
            ->whereIn('teaching_assignment_id', $akademikAssignments->pluck('id'))
            ->where('semester', $semester)
            ->get()
            ->keyBy('teaching_assignment_id');

        // Nilai Kokurikuler (P5) — SELURUH projek pada periode ini.
        // Skema kokurikuler_grades sengaja tanpa unique constraint karena satu
        // siswa bisa mengikuti banyak projek P5 per semester; ->first() lama
        // membuang projek ke-2 dst. dari rapor (Audit 3.7).
        $kokurikulerGrades = KokurikulerGrade::where('student_id', $studentId)
            ->where('academic_period_id', $period->id)
            ->orderBy('created_at')
            ->get();

        // Ekstrakurikuler — WAJIB dikunci ke periode rapor ini.
        // Tanpa filter periode, ekskul tahun ajaran lampau ikut tercetak
        // di rapor tahun berjalan setelah siswa naik kelas (Audit 3.7).
        $ekstrakurikuler = StudentSubjectEnrollment::where('student_id', $studentId)
            ->whereHas('teachingAssignment', fn($q) => $q->where('academic_period_id', $period->id))
            ->whereHas('teachingAssignment.subject', fn($q) => $q->where('type', 'extracurricular'))
            ->with('teachingAssignment.subject')
            ->get();

        // Rekap Absensi — dikunci ke periode rapor via teaching assignment.
        // Kolom `semester` hanya menyimpan paritas ganjil/genap; tanpa kunci
        // periode, rekap "Ganjil" 2024/2025 ikut terjumlah ke rapor Ganjil
        // 2025/2026 dan menggelembungkan total sakit/izin/alpha (Audit 3.7).
        $attendance = AttendanceSummary::where('student_id', $studentId)
            ->where('semester', $semester)
            ->whereHas('teachingAssignment', fn($q) => $q->where('academic_period_id', $period->id))
            ->get();

        $totalSakit = $attendance->sum('sick');
        $totalIzin = $attendance->sum('permit');
        $totalAlpha = $attendance->sum('alpha');

        // Tentukan status resmi rapor berdasarkan role.
        // Siswa & Wali Siswa hanya menerima "rapor bayangan" (tidak resmi).
        $isOfficial = true;
        if (auth()->check() && auth()->user()->hasAnyRole(['student', 'guardian'])) {
            $isOfficial = false;
        }

        $schoolIdentity = app(SchoolIdentityService::class)->getIdentity();

        return [
            'homeroom' => $homeroom,
            'classroom' => $classroom,
            'period' => $period,
            'semester' => $semester,
            // Label tampilan Indonesia untuk blade rapor. `$semester` mentah
            // ('odd'/'even') tetap dipakai untuk query DB; jangan dilokalkan di sana.
            'semesterLabel' => $semester === 'odd' ? 'Ganjil' : 'Genap',
            'student' => $student,
            'akademikAssignments' => $akademikAssignments,
            'finalGrades' => $finalGrades,
            'kokurikulerGrades' => $kokurikulerGrades,
            'ekstrakurikuler' => $ekstrakurikuler,
            'totalSakit' => $totalSakit,
            'totalIzin' => $totalIzin,
            'totalAlpha' => $totalAlpha,
            'isOfficial' => $isOfficial,
            'schoolIdentity' => $schoolIdentity,
        ];
    }

    public function exportPdf(ClassHomeroom $homeroom, int $studentId)
    {
        $data = $this->getRaporData($homeroom, $studentId);

        $pdf = Pdf::loadView('exports.rapor-print', $data)
                  ->setPaper('a4', 'portrait');

        $filename = $this->safeFilename($data['student']->name, 'pdf');

        // WAJIB StreamedResponse — Livewire/Filament hanya mengenali unduhan dari
        // StreamedResponse/BinaryFileResponse. Response biasa akan di-JSON-serialize
        // oleh Livewire → "Malformed UTF-8" & unduhan gagal senyap (Issue #2/#3).
        // Aman memori: output PDF di-echo streaming, bukan dirakit ganda.
        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function exportWord(ClassHomeroom $homeroom, int $studentId)
    {
        $data = $this->getRaporData($homeroom, $studentId);

        $view = View::make('exports.rapor-print', $data)->render();
        $filename = $this->safeFilename($data['student']->name, 'doc');

        // Sama seperti PDF: harus StreamedResponse agar Livewire memicu unduhan.
        return response()->streamDownload(
            fn () => print($view),
            $filename,
            ['Content-Type' => 'application/vnd.ms-word'],
        );
    }

    /**
     * Rakit nama file unduhan yang aman lintas-OS (spasi/karakter khusus pada
     * nama siswa bisa merusak header Content-Disposition di sebagian browser).
     */
    private function safeFilename(string $studentName, string $ext): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', trim($studentName));
        $slug = trim($slug, '_') ?: 'siswa';

        return "Rapor_{$slug}.{$ext}";
    }
}