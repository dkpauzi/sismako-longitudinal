<?php

namespace App\Filament\Pages\Student;

use App\Models\AcademicPeriod;
use App\Models\AttendanceSummary;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Models\TeachingAssignment;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MyGrades extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Detail Nilai Saya';
    protected static ?string $title = 'Riwayat Nilai & Kehadiran';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationGroup = 'Akademik';
    
    // Custom blade file
    protected static string $view = 'filament.pages.student.my-grades';

    public ?int $selectedPeriodId = null;

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasRole('student') && Auth::user()->student !== null;
    }

    public function mount(): void
    {
        $student = Auth::user()->student;
        if ($student) {
            $latestEnrollment = Enrollment::where('student_id', $student->id)
                ->orderBy('created_at', 'desc')
                ->first();
                
            if ($latestEnrollment) {
                $this->selectedPeriodId = $latestEnrollment->academic_period_id;
            }
        }
    }

    public function form(Form $form): Form
    {
        $student = Auth::user()->student;
        $options = [];
        
        if ($student) {
            // Hanya tampilkan periode dimana siswa ini terdaftar
            $periods = Enrollment::where('student_id', $student->id)
                ->with('academicPeriod')
                ->get()
                ->mapWithKeys(function($enrollment) {
                    $ap = $enrollment->academicPeriod;
                    if ($ap) {
                        return [$ap->id => $ap->name];
                    }
                    return [];
                })->toArray();
                
            $options = $periods;
        }

        return $form
            ->schema([
                Select::make('selectedPeriodId')
                    ->label('Pilih Semester / Tahun Ajaran')
                    ->options($options)
                    ->live()
            ])
            ->columns(1);
    }

    /**
     * Menarik semua data nilai untuk siswa yang sedang login.
     * Menggunakan eager loading berat untuk menghindari N+1.
     */
    protected function getViewData(): array
    {
        $student = Auth::user()->student;
        
        // Pakai periode dari form
        $activePeriod = AcademicPeriod::find($this->selectedPeriodId);
        
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
        $assessmentScores = \App\Models\Grade::with('assessment')
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
            
            $formatives = $taScores->filter(fn($score) => str_starts_with($score->assessment->category, 'formatif'));
            $summatives = $taScores->filter(fn($score) => str_starts_with($score->assessment->category, 'sumatif'));
            
            $studentData[] = [
                'subject' => $ta->subject->name,
                'is_kokurikuler' => $isKokurikuler,
                // Pembina ekskul eksternal (teacher_id null) → tampilkan namanya,
                // bukan '-'. instructorDisplayName() = sumber tunggal (teacher sudah
                // di-eager-load di query $teachingAssignments).
                'teacher' => $ta->instructorDisplayName(),
                'kktp' => $ta->kktp_or_default,
                
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
