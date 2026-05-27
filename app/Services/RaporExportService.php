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
    private function getRaporData(ClassHomeroom $homeroom, int $studentId): array
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

        // Kokurikuler Grades (for the P5 narrative)
        $kokurikulerGrade = KokurikulerGrade::where('student_id', $studentId)
            ->where('academic_period_id', $period->id)
            ->first();

        // Ekstrakurikuler
        $ekstrakurikuler = StudentSubjectEnrollment::where('student_id', $studentId)
            ->whereHas('teachingAssignment.subject', fn($q) => $q->where('type', 'extracurricular'))
            ->with('teachingAssignment.subject')
            ->get();
        
        // Attendance Summary
        $attendance = AttendanceSummary::where('student_id', $studentId)
            ->where('semester', $semester)
            ->get();

        $totalSakit = $attendance->sum('sick');
        $totalIzin = $attendance->sum('permit');
        $totalAlpha = $attendance->sum('alpha');

        // Determine Official Status based on roles
        $isOfficial = true;
        if (auth()->check() && auth()->user()->hasAnyRole(['student', 'guardian', 'Siswa', 'Wali Siswa'])) {
            $isOfficial = false;
        }

        $schoolIdentity = app(SchoolIdentityService::class)->getIdentity();

        return [
            'homeroom' => $homeroom,
            'classroom' => $classroom,
            'period' => $period,
            'semester' => $semester,
            'student' => $student,
            'akademikAssignments' => $akademikAssignments,
            'finalGrades' => $finalGrades,
            'kokurikulerGrade' => $kokurikulerGrade,
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

        $filename = 'Rapor_' . $data['student']->name . '.pdf';

        return $pdf->download($filename);
    }

    public function exportWord(ClassHomeroom $homeroom, int $studentId)
    {
        $data = $this->getRaporData($homeroom, $studentId);
        
        $view = View::make('exports.rapor-print', $data)->render();
        $filename = 'Rapor_' . $data['student']->name . '.doc';

        return response($view)
            ->header('Content-Type', 'application/vnd.ms-word')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}