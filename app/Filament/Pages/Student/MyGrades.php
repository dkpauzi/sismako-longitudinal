<?php

namespace App\Filament\Pages\Student;

use App\Models\AcademicPeriod;
use App\Models\AssessmentScore;
use App\Models\AttendanceSummary;
use App\Models\FinalGrade;
use App\Models\TeachingAssignment;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MyGrades extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Detail Nilai Saya';
    protected static ?string $title = 'Riwayat Nilai & Kehadiran';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = 'Akademik';
    
    // Custom blade file
    protected static string $view = 'filament.pages.student.my-grades';

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasRole('student') && Auth::user()->student !== null;
    }

    /**
     * Menarik semua data nilai untuk siswa yang sedang login.
     * Menggunakan eager loading berat untuk menghindari N+1.
     */
    protected function getViewData(): array
    {
        $student = Auth::user()->student;
        
        // Ambil periode akademik yang aktif saja saat ini (sebagai iterasi pertama)
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        
        if (!$activePeriod) {
            return [
                'hasData' => false,
            ];
        }

        // Cek enrollment di periode aktif ini
        $enrollment = $student->enrollments()
            ->where('academic_period_id', $activePeriod->id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return [
                'hasData' => false,
            ];
        }

        $classroomId = $enrollment->classroom_id;

        // Ambil penugasan (mapel) yang diajarkan di kelas tersebut
        $teachingAssignments = TeachingAssignment::with('subject', 'teacher')
            ->where('academic_period_id', $activePeriod->id)
            ->where('classroom_id', $classroomId)
            ->get();

        $assignmentIds = $teachingAssignments->pluck('id')->toArray();

        // 1. Ambil NILAI AKHIR (Final Grades)
        $finalGrades = FinalGrade::where('student_id', $student->id)
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where('semester', $activePeriod->semester)
            ->get()
            ->keyBy('teaching_assignment_id');

        // 2. Ambil DETAIL ASESMEN FORMATIF/SUMATIF
        // Gunakan whereHas untuk memastikan assessment ada di assignment yang relevan
        $assessmentScores = AssessmentScore::with('assessment')
            ->where('student_id', $student->id)
            ->whereHas('assessment', function($q) use ($assignmentIds) {
                $q->whereIn('teaching_assignment_id', $assignmentIds);
            })
            ->get()
            ->groupBy(function($score) {
                // Group by Assignment ID dulu, lalu by Type (formative/summative)
                return $score->assessment->teaching_assignment_id;
            });

        // 3. Ambil REKAP ABSENSI PER MAPEL
        $attendanceSummaries = AttendanceSummary::where('student_id', $student->id)
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where('semester', $activePeriod->semester)
            ->get()
            ->keyBy('teaching_assignment_id');

        // Susun data agar mudah dibaca di view
        $studentData = [];
        
        foreach ($teachingAssignments as $ta) {
            $isKokurikuler = $ta->subject->is_kokurikuler;
            
            $taScores = $assessmentScores->get($ta->id) ?? collect();
            
            $formatives = $taScores->filter(fn($score) => $score->assessment->type === 'formative');
            $summatives = $taScores->filter(fn($score) => $score->assessment->type === 'summative');
            
            $studentData[] = [
                'subject' => $ta->subject->name,
                'is_kokurikuler' => $isKokurikuler,
                'teacher' => $ta->teacher->name ?? '-',
                'kktp' => $ta->kktp ?? 75,
                
                'final_grade' => $finalGrades->get($ta->id),
                'attendance' => $attendanceSummaries->get($ta->id),
                
                'formative_scores' => $formatives,
                'summative_scores' => $summatives,
            ];
        }

        // Pisahkan yang akademik dan non-akademik (kokurikuler)
        $akademikData = collect($studentData)->filter(fn($d) => !$d['is_kokurikuler']);
        $kokurikulerData = collect($studentData)->filter(fn($d) => $d['is_kokurikuler']);

        return [
            'hasData' => true,
            'student' => $student,
            'period' => $activePeriod,
            'classroom' => $enrollment->classroom->name ?? '-',
            'akademikData' => $akademikData,
            'kokurikulerData' => $kokurikulerData,
        ];
    }
}
