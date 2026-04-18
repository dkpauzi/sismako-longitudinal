<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$student = App\Models\Student::where('nisn', '0105171307')->first();
if (!$student) {
    echo "Student not found!\n";
    exit;
}
echo "Student found: " . $student->name . "\n";

$classroom = App\Models\Classroom::where('name', 'Kelas 7.2')->first();
if (!$classroom) {
    echo "Classroom 7.2 not found!\n";
    exit;
}

// Ensure Academic Periods exist
$periodGanjil = App\Models\AcademicPeriod::firstOrCreate(
    ['start_year' => 2024, 'end_year' => 2025, 'semester' => 'odd'],
    ['start_date' => '2024-07-15', 'end_date' => '2024-12-20', 'is_active' => true] // Currently active in DB mostly
);
$periodGenap = App\Models\AcademicPeriod::firstOrCreate(
    ['start_year' => 2024, 'end_year' => 2025, 'semester' => 'even'],
    ['start_date' => '2025-01-08', 'end_date' => '2025-06-20', 'is_active' => false]
);

$periods = [$periodGanjil, $periodGenap];

// Get current mapped subjects for 7.2 (or any subjects)
$baseAssignments = App\Models\TeachingAssignment::where('classroom_id', $classroom->id)->get()->unique('subject_id');

if ($baseAssignments->isEmpty()) {
    echo "No base teaching assignments for 7.2.\n";
    exit;
}

foreach ($periods as $period) {
    // Enroll Zilian
    App\Models\Enrollment::updateOrCreate([
        'student_id' => $student->id,
        'academic_period_id' => $period->id,
    ], [
        'classroom_id' => $classroom->id,
        'status' => 'active',
        'enrollment_date' => \Carbon\Carbon::now()->subYear(),
    ]);

    foreach ($baseAssignments as $ba) {
        $ta = App\Models\TeachingAssignment::firstOrCreate([
            'academic_period_id' => $period->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $ba->subject_id,
        ], [
            'teacher_id' => $ba->teacher_id,
        ]);

        // Add Final Grade
        App\Models\FinalGrade::updateOrCreate([
            'student_id' => $student->id,
            'teaching_assignment_id' => $ta->id,
            'semester' => $period->semester,
        ], [
            'final_score' => rand(75, 98),
            'grade_label' => rand(75, 98) > 85 ? 'A' : 'B',
            'narrative_description' => 'Dummy data generated for QA',
            'is_locked' => true,
        ]);
        
        // Let's add some Attendance as well
        App\Models\AttendanceSummary::updateOrCreate([
             'student_id' => $student->id,
             'teaching_assignment_id' => $ta->id,
             'semester' => $period->semester,
        ],[
             'present' => rand(10, 15),
             'sick' => rand(0, 2),
             'permit' => rand(0, 1),
             'alpha' => rand(0, 1),
             'attendance_percentage' => rand(85, 100)
        ]);
    }
}

echo "Dummy data for Zilian Aldrin successfully created!\n";
