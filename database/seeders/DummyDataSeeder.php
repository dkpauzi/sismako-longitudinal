<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TeachingAssignment;
use App\Models\Enrollment;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\Attendance;
use Carbon\Carbon;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating dummy assessments, grades, and attendances...');

        $assignments = TeachingAssignment::with('classroom')->get();

        foreach ($assignments as $assignment) {
            $students = Enrollment::where('classroom_id', $assignment->classroom_id)
                ->where('academic_period_id', $assignment->academic_period_id)
                ->with('student')
                ->get();

            if ($students->isEmpty()) {
                continue;
            }

            // Create Assessments
            $assessments = [];
            
            // 1 Formatif
            $assessments[] = Assessment::create([
                'teaching_assignment_id' => $assignment->id,
                'name' => 'Tugas 1 (Formatif)',
                'category' => 'formatif_deskripsi',
                'technique' => 'penugasan',
                'weight' => 0,
                'date' => Carbon::now()->subDays(20),
            ]);

            // 1 Sumatif 1
            $assessments[] = Assessment::create([
                'teaching_assignment_id' => $assignment->id,
                'name' => 'Ulangan Harian 1',
                'category' => 'sumatif_lingkup_materi',
                'technique' => 'tes_tertulis',
                'weight' => 50,
                'date' => Carbon::now()->subDays(10),
            ]);

            // 1 Sumatif Akhir
            $assessments[] = Assessment::create([
                'teaching_assignment_id' => $assignment->id,
                'name' => 'Ujian Akhir Semester',
                'category' => 'sumatif_akhir_semester',
                'technique' => 'tes_tertulis',
                'weight' => 50,
                'date' => Carbon::now(),
            ]);

            // Populate Grades
            foreach ($assessments as $assessment) {
                foreach ($students as $enrollment) {
                    $score = rand(60, 100);
                    $feedback = '';
                    if ($assessment->category === 'formatif_deskripsi') {
                        $feedback = $score >= 80 ? 'Anak ini sangat baik dalam materi ini.' : 'Perlu bimbingan lebih lanjut untuk materi ini.';
                    }

                    Grade::create([
                        'assessment_id' => $assessment->id,
                        'student_id' => $enrollment->student_id,
                        'score' => $score,
                        'feedback' => $feedback,
                    ]);
                }
            }

            // Populate Attendance (3 pertemuan per assignment)
            for ($i = 0; $i < 3; $i++) {
                $date = Carbon::now()->subDays(rand(1, 30));
                foreach ($students as $enrollment) {
                    $rand = rand(1, 100);
                    $status = 'present'; // 80% Hadir
                    if ($rand > 80 && $rand <= 85) $status = 'sick';
                    elseif ($rand > 85 && $rand <= 90) $status = 'permit';
                    elseif ($rand > 90) $status = 'alpha';

                    Attendance::create([
                        'teaching_assignment_id' => $assignment->id,
                        'student_id' => $enrollment->student_id,
                        'date' => $date,
                        'status' => $status,
                        'note' => $status !== 'present' ? 'Dummy note' : null,
                    ]);
                }
            }
        }

        $this->command->info('Creating historical longitudinal data for grades 8 and 9...');

        $mapping = [
            'Kelas 9.1' => 'Kelas 8.1',
            'Kelas 8.1' => 'Kelas 7.1',
            'Kelas 8.2' => 'Kelas 7.2',
        ];

        $pastPeriods = [1, 2]; // 1: 2023/2024 Ganjil, 2: 2023/2024 Genap

        foreach ($mapping as $currentName => $pastName) {
            $classCurrent = \App\Models\Classroom::where('name', $currentName)->first();
            $classPast = \App\Models\Classroom::where('name', $pastName)->first();

            if (!$classCurrent || !$classPast) continue;

            $currentAssignments = TeachingAssignment::where('classroom_id', $classCurrent->id)
                ->where('academic_period_id', 3)
                ->get();

            // Ambil seluruh siswa di kelas saat ini
            $students = Enrollment::where('classroom_id', $classCurrent->id)
                ->where('academic_period_id', 3)
                ->get();

            foreach ($pastPeriods as $pid) {
                foreach ($currentAssignments as $ca) {
                    // Buat penugasan ngajar di periode lalu (di KELAS LALU)
                    $pastAssignment = TeachingAssignment::firstOrCreate([
                        'academic_period_id' => $pid,
                        'classroom_id' => $classPast->id,
                        'subject_id' => $ca->subject_id,
                        'teacher_id' => $ca->teacher_id,
                    ]);

                    foreach ($students as $studentEnroll) {
                        // Pastikan siswa enroll di KELAS LALU pada PERIODE LALU
                        Enrollment::firstOrCreate([
                            'student_id' => $studentEnroll->student_id,
                            'academic_period_id' => $pid,
                        ], [
                            'classroom_id' => $classPast->id,
                            'status' => 'active',
                            'enrollment_date' => Carbon::now()->subYears(1),
                        ]);

                        // Nilai historis
                        \App\Models\FinalGrade::updateOrCreate([
                            'student_id' => $studentEnroll->student_id,
                            'teaching_assignment_id' => $pastAssignment->id,
                            'semester' => $pid == 1 ? 'odd' : 'even',
                        ], [
                            'final_score' => rand(75, 96),
                            'grade_label' => 'B',
                            'narrative_description' => 'Mencapai kompetensi dengan baik',
                            'is_locked' => true,
                        ]);
                    }
                }
            }
        }

        // --- TAMBAHAN: Buat final_grade untuk periode aktif saat ini agar grafik ujung kanan terisi ---
        foreach ($assignments as $assignment) {
            $students = Enrollment::where('classroom_id', $assignment->classroom_id)
                ->where('academic_period_id', $assignment->academic_period_id)
                ->get();
            foreach ($students as $studentEnroll) {
                \App\Models\FinalGrade::updateOrCreate([
                    'student_id' => $studentEnroll->student_id,
                    'teaching_assignment_id' => $assignment->id,
                    'semester' => 'odd', // Saat ini period 3 is odd
                ], [
                    'final_score' => rand(78, 98), // Sedikit lebih tinggi biar trennya naik :)
                    'grade_label' => 'A',
                    'narrative_description' => 'Mencapai kompetensi dengan sangat baik',
                    'is_locked' => true,
                ]);
            }
        }

        $this->command->info('Dummy data successfully generated!');
    }
}
