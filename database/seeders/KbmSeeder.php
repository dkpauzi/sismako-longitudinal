<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AcademicPeriod;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Classroom;
use App\Models\LearningObjective;
use App\Models\TeachingAssignment;
use App\Models\SubjectSchedule;

class KbmSeeder extends Seeder
{
    public function run(): void
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        if (!$activePeriod) {
            $this->command->error('Tidak ada Tahun Ajaran aktif! Pastikan SchoolSeeder sudah dijalankan.');
            return;
        }

        // --- MAPEL MAP ---
        $subjMap = [
            'MTK' => Subject::where('code', 'MTK')->first()->id,
            'BIND' => Subject::where('code', 'BIND')->first()->id,
            'BING' => Subject::where('code', 'BING')->first()->id,
            'IPA' => Subject::where('code', 'IPA')->first()->id,
            'IPS' => Subject::where('code', 'IPS')->first()->id,
            'PAI' => Subject::where('code', 'PAI')->first()->id,
            'PJOK' => Subject::where('code', 'PJOK')->first()->id,
            'TIK' => Subject::where('code', 'TIK')->first()->id,
            'PKN' => Subject::where('code', 'PKN')->first()->id,
            'SENBUD' => Subject::where('code', 'SENBUD')->first()->id,
            'KOK' => Subject::where('code', 'KOK')->first()->id,
        ];

        // --- TIMESLOTS MAP ---
        // Karena waktu tertera di prompt, kita map jam ke string waktu
        $timeslots = [
            '07.30-08.10' => ['07:30', '08:10'],
            '07.30-08.50' => ['07:30', '08:50'],
            '08.00-08.40' => ['08:00', '08:40'],
            '08.00-09.20' => ['08:00', '09:20'],
            '08.00-10.00' => ['08:00', '10:00'],
            '08.10-08.50' => ['08:10', '08:50'],
            '08.10-09.30' => ['08:10', '09:30'],
            '08.10-10.10' => ['08:10', '10:10'],
            '08.20-09.00' => ['08:20', '09:00'],
            '08.40-10.00' => ['08:40', '10:00'],
            '08.50-10.10' => ['08:50', '10:10'],
            '09.00-09.40' => ['09:00', '09:40'],
            '09.20-11.10' => ['09:20', '11:10'],
            '09.20-11.50' => ['09:20', '11:50'],
            '09.30-10.10' => ['09:30', '10:10'],
            '09.30-11.35' => ['09:30', '11:35'],
            '10.25-11.45' => ['10:25', '11:45'],
            '10.30-11.10' => ['10:30', '11:10'],
            '10.30-11.50' => ['10:30', '11:50'],
            '10.55-11.35' => ['10:55', '11:35'],
            '10.55-12.15' => ['10:55', '12:15'],
            '11.10-11.50' => ['11:10', '11:50'],
            '11.20-12.00' => ['11:20', '12:00'],
            '11.35-12.15' => ['11:35', '12:15'],
            '12.20-13.00' => ['12:20', '13:00'],
            '13.00-13.40' => ['13:00', '13:40'],
            '13.00-14.20' => ['13:00', '14:20'],
            '13.40-14.20' => ['13:40', '14:20'],
            '13.40-15.00' => ['13:40', '15:00'],
            '14.20-15.00' => ['14:20', '15:00'],
        ];

        // --- GURU ---
        // Sesuai mapping yang ada
        $schedulesData = [
            ['guru' => 'Yunita', 'sub_codes' => ['BIND', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '7.1', 'time' => '08.50-10.10', 'sub' => 'BIND'],
                ['day' => 'Senin', 'kelas' => '9.1', 'time' => '10.55-12.15', 'sub' => 'BIND'],
                ['day' => 'Selasa', 'kelas' => '7.2', 'time' => '09.20-11.10', 'sub' => 'BIND'],
                ['day' => 'Selasa', 'kelas' => '8.2', 'time' => '11.10-11.50', 'sub' => 'BIND'],
                ['day' => 'Selasa', 'kelas' => '7.1', 'time' => '12.20-13.00', 'sub' => 'KOK'],
                ['day' => 'Selasa', 'kelas' => '8.2', 'time' => '13.00-13.40', 'sub' => 'BIND'],
                ['day' => 'Selasa', 'kelas' => '8.1', 'time' => '13.40-14.20', 'sub' => 'BIND'],
                ['day' => 'Selasa', 'kelas' => '9.1', 'time' => '14.20-15.00', 'sub' => 'KOK'],
                ['day' => 'Rabu', 'kelas' => '9.1', 'time' => '07.30-08.50', 'sub' => 'BIND'],
                ['day' => 'Rabu', 'kelas' => '8.2', 'time' => '08.50-10.10', 'sub' => 'BIND'],
                ['day' => 'Rabu', 'kelas' => '7.1', 'time' => '10.55-11.35', 'sub' => 'BIND'],
                ['day' => 'Rabu', 'kelas' => '8.2', 'time' => '11.35-12.15', 'sub' => 'KOK'],
                ['day' => 'Rabu', 'kelas' => '7.2', 'time' => '14.20-15.00', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '7.2', 'time' => '08.10-10.10', 'sub' => 'BIND'],
                ['day' => 'Kamis', 'kelas' => '7.1', 'time' => '10.55-12.15', 'sub' => 'BIND'],
                ['day' => 'Kamis', 'kelas' => '9.1', 'time' => '13.40-15.00', 'sub' => 'BIND'],
                ['day' => 'Jumat', 'kelas' => '8.1', 'time' => '08.20-09.00', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '8.1', 'time' => '10.25-11.45', 'sub' => 'BIND']
            ]],
            ['guru' => 'Petria', 'sub_codes' => ['IPA', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '9.1', 'time' => '13.00-13.40', 'sub' => 'KOK'],
                ['day' => 'Senin', 'kelas' => '9.1', 'time' => '13.40-15.00', 'sub' => 'IPA'],
                ['day' => 'Selasa', 'kelas' => '7.1', 'time' => '08.00-09.20', 'sub' => 'IPA'],
                ['day' => 'Selasa', 'kelas' => '7.2', 'time' => '12.20-13.00', 'sub' => 'KOK'],
                ['day' => 'Rabu', 'kelas' => '7.1', 'time' => '07.30-08.50', 'sub' => 'IPA'],
                ['day' => 'Rabu', 'kelas' => '7.2', 'time' => '09.30-11.35', 'sub' => 'IPA'],
                ['day' => 'Rabu', 'kelas' => '7.1', 'time' => '11.35-12.15', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '9.1', 'time' => '08.50-10.10', 'sub' => 'IPA'],
                ['day' => 'Kamis', 'kelas' => '7.2', 'time' => '10.55-12.15', 'sub' => 'IPA']
            ]],
            ['guru' => 'Asfriyanti', 'sub_codes' => ['BING', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '7.2', 'time' => '10.55-12.15', 'sub' => 'BING'],
                ['day' => 'Senin', 'kelas' => '8.1', 'time' => '13.00-13.40', 'sub' => 'KOK'],
                ['day' => 'Selasa', 'kelas' => '8.1', 'time' => '08.00-10.00', 'sub' => 'BING'],
                ['day' => 'Selasa', 'kelas' => '8.2', 'time' => '10.30-11.10', 'sub' => 'BING'],
                ['day' => 'Selasa', 'kelas' => '9.1', 'time' => '13.00-14.20', 'sub' => 'BING'],
                ['day' => 'Selasa', 'kelas' => '7.1', 'time' => '14.20-15.00', 'sub' => 'KOK'],
                ['day' => 'Rabu', 'kelas' => '7.2', 'time' => '07.30-08.10', 'sub' => 'BING'],
                ['day' => 'Rabu', 'kelas' => '7.1', 'time' => '08.50-10.10', 'sub' => 'BING'],
                ['day' => 'Rabu', 'kelas' => '8.2', 'time' => '10.55-11.35', 'sub' => 'BING'],
                ['day' => 'Rabu', 'kelas' => '9.1', 'time' => '11.35-12.15', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '7.2', 'time' => '07.30-08.10', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '9.1', 'time' => '08.10-08.50', 'sub' => 'BING'],
                ['day' => 'Kamis', 'kelas' => '7.1', 'time' => '09.30-10.10', 'sub' => 'BING'],
                ['day' => 'Kamis', 'kelas' => '8.2', 'time' => '13.00-13.40', 'sub' => 'KOK']
            ]],
            ['guru' => 'Riza', 'sub_codes' => ['PAI', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '7.2', 'time' => '08.10-08.50', 'sub' => 'KOK'],
                ['day' => 'Senin', 'kelas' => '7.1', 'time' => '10.55-12.15', 'sub' => 'PAI'],
                ['day' => 'Senin', 'kelas' => '8.2', 'time' => '13.00-13.40', 'sub' => 'KOK'],
                ['day' => 'Selasa', 'kelas' => '8.2', 'time' => '08.00-09.20', 'sub' => 'PAI'],
                ['day' => 'Selasa', 'kelas' => '7.2', 'time' => '11.10-11.50', 'sub' => 'PAI'],
                ['day' => 'Selasa', 'kelas' => '7.2', 'time' => '13.00-13.40', 'sub' => 'PAI'],
                ['day' => 'Kamis', 'kelas' => '7.1', 'time' => '07.30-08.10', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '8.1', 'time' => '08.10-09.30', 'sub' => 'PAI'],
                ['day' => 'Kamis', 'kelas' => '8.1', 'time' => '13.00-13.40', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '9.1', 'time' => '08.20-09.00', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '9.1', 'time' => '10.25-11.45', 'sub' => 'PAI']
            ]],
            ['guru' => 'Wempi', 'sub_codes' => ['IPS', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '7.1', 'time' => '13.00-13.40', 'sub' => 'KOK'],
                ['day' => 'Senin', 'kelas' => '7.2', 'time' => '13.40-15.00', 'sub' => 'IPS'],
                ['day' => 'Selasa', 'kelas' => '9.1', 'time' => '08.00-08.40', 'sub' => 'IPS'],
                ['day' => 'Selasa', 'kelas' => '7.1', 'time' => '09.20-11.50', 'sub' => 'IPS'],
                ['day' => 'Selasa', 'kelas' => '8.1', 'time' => '12.20-13.00', 'sub' => 'KOK'],
                ['day' => 'Selasa', 'kelas' => '7.2', 'time' => '13.40-14.20', 'sub' => 'IPS'],
                ['day' => 'Rabu', 'kelas' => '8.2', 'time' => '07.30-08.10', 'sub' => 'IPS'],
                ['day' => 'Rabu', 'kelas' => '9.1', 'time' => '08.10-09.30', 'sub' => 'IPS'],
                ['day' => 'Rabu', 'kelas' => '8.1', 'time' => '10.55-11.35', 'sub' => 'IPS'],
                ['day' => 'Rabu', 'kelas' => '8.1', 'time' => '13.00-14.20', 'sub' => 'IPS'],
                ['day' => 'Rabu', 'kelas' => '9.1', 'time' => '14.20-15.00', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '7.2', 'time' => '08.20-09.00', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '8.2', 'time' => '09.00-09.40', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '8.2', 'time' => '10.25-11.45', 'sub' => 'IPS']
            ]],
            ['guru' => 'Winda', 'sub_codes' => ['SENBUD', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '9.1', 'time' => '08.10-08.50', 'sub' => 'KOK'],
                ['day' => 'Senin', 'kelas' => '9.1', 'time' => '08.50-10.10', 'sub' => 'SENBUD'],
                ['day' => 'Senin', 'kelas' => '8.2', 'time' => '10.55-12.15', 'sub' => 'SENBUD'],
                ['day' => 'Selasa', 'kelas' => '8.2', 'time' => '12.20-13.00', 'sub' => 'KOK'],
                ['day' => 'Selasa', 'kelas' => '7.1', 'time' => '13.00-14.20', 'sub' => 'SENBUD'],
                ['day' => 'Rabu', 'kelas' => '8.1', 'time' => '08.50-10.10', 'sub' => 'SENBUD'],
                ['day' => 'Rabu', 'kelas' => '7.2', 'time' => '11.35-12.15', 'sub' => 'KOK'],
                ['day' => 'Rabu', 'kelas' => '7.2', 'time' => '13.00-14.20', 'sub' => 'SENBUD'],
                ['day' => 'Rabu', 'kelas' => '8.1', 'time' => '14.20-15.00', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '7.1', 'time' => '09.00-09.40', 'sub' => 'KOK']
            ]],
            ['guru' => 'Nadya', 'sub_codes' => ['PJOK', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '8.2', 'time' => '08.10-08.50', 'sub' => 'KOK'],
                ['day' => 'Senin', 'kelas' => '8.2', 'time' => '08.50-10.10', 'sub' => 'PJOK'],
                ['day' => 'Rabu', 'kelas' => '8.1', 'time' => '07.30-08.50', 'sub' => 'PJOK'],
                ['day' => 'Rabu', 'kelas' => '8.1', 'time' => '11.35-12.15', 'sub' => 'KOK'],
                ['day' => 'Rabu', 'kelas' => '9.1', 'time' => '13.00-14.20', 'sub' => 'PJOK'],
                ['day' => 'Kamis', 'kelas' => '7.1', 'time' => '08.10-09.30', 'sub' => 'PJOK'],
                ['day' => 'Kamis', 'kelas' => '9.1', 'time' => '13.00-13.40', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '7.1', 'time' => '08.20-09.00', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '8.1', 'time' => '09.00-09.40', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '7.2', 'time' => '10.25-11.45', 'sub' => 'PJOK']
            ]],
            ['guru' => 'Yuli', 'sub_codes' => ['PKN', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '8.2', 'time' => '13.40-15.00', 'sub' => 'PKN'],
                ['day' => 'Selasa', 'kelas' => '7.2', 'time' => '08.00-09.20', 'sub' => 'PKN'],
                ['day' => 'Selasa', 'kelas' => '9.1', 'time' => '10.30-11.50', 'sub' => 'PKN'],
                ['day' => 'Selasa', 'kelas' => '7.2', 'time' => '14.20-15.00', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '8.1', 'time' => '07.30-08.10', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '8.1', 'time' => '09.30-11.35', 'sub' => 'PKN'],
                ['day' => 'Kamis', 'kelas' => '7.1', 'time' => '13.00-13.40', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '7.1', 'time' => '13.40-15.00', 'sub' => 'PKN'],
                ['day' => 'Jumat', 'kelas' => '8.2', 'time' => '08.20-09.00', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '9.1', 'time' => '09.00-09.40', 'sub' => 'KOK']
            ]],
            ['guru' => 'Gima', 'sub_codes' => ['TIK', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '7.2', 'time' => '13.00-13.40', 'sub' => 'KOK'],
                ['day' => 'Selasa', 'kelas' => '9.1', 'time' => '12.20-13.00', 'sub' => 'KOK'],
                ['day' => 'Selasa', 'kelas' => '8.1', 'time' => '14.20-15.00', 'sub' => 'KOK'],
                ['day' => 'Rabu', 'kelas' => '7.2', 'time' => '08.10-09.30', 'sub' => 'TIK'],
                ['day' => 'Rabu', 'kelas' => '9.1', 'time' => '09.30-11.35', 'sub' => 'TIK'],
                ['day' => 'Rabu', 'kelas' => '7.1', 'time' => '13.00-14.20', 'sub' => 'TIK'],
                ['day' => 'Rabu', 'kelas' => '7.1', 'time' => '14.20-15.00', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '8.2', 'time' => '07.30-08.10', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '8.1', 'time' => '08.10-08.50', 'sub' => 'TIK'],
                ['day' => 'Kamis', 'kelas' => '8.2', 'time' => '10.55-12.15', 'sub' => 'TIK'],
                ['day' => 'Kamis', 'kelas' => '8.1', 'time' => '13.40-15.00', 'sub' => 'TIK']
            ]],
            ['guru' => 'Anggun', 'sub_codes' => ['MTK', 'KOK'], 'schedules' => [
                ['day' => 'Senin', 'kelas' => '7.1', 'time' => '08.10-08.50', 'sub' => 'KOK'],
                ['day' => 'Senin', 'kelas' => '7.2', 'time' => '08.50-10.10', 'sub' => 'MTK'],
                ['day' => 'Senin', 'kelas' => '8.1', 'time' => '10.55-12.15', 'sub' => 'MTK'],
                ['day' => 'Senin', 'kelas' => '7.1', 'time' => '13.40-15.00', 'sub' => 'MTK'],
                ['day' => 'Selasa', 'kelas' => '9.1', 'time' => '08.40-10.00', 'sub' => 'MTK'],
                ['day' => 'Selasa', 'kelas' => '8.1', 'time' => '11.10-11.50', 'sub' => 'MTK'],
                ['day' => 'Selasa', 'kelas' => '8.2', 'time' => '13.40-14.20', 'sub' => 'MTK'],
                ['day' => 'Selasa', 'kelas' => '8.2', 'time' => '14.20-15.00', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '9.1', 'time' => '07.30-08.10', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '8.2', 'time' => '08.10-10.10', 'sub' => 'MTK'],
                ['day' => 'Kamis', 'kelas' => '9.1', 'time' => '10.55-12.15', 'sub' => 'MTK'],
                ['day' => 'Kamis', 'kelas' => '7.2', 'time' => '13.00-13.40', 'sub' => 'KOK'],
                ['day' => 'Kamis', 'kelas' => '7.2', 'time' => '13.40-15.00', 'sub' => 'MTK'],
                ['day' => 'Jumat', 'kelas' => '8.1', 'time' => '09.00-09.40', 'sub' => 'KOK'],
                ['day' => 'Jumat', 'kelas' => '7.1', 'time' => '10.25-11.45', 'sub' => 'MTK']
            ]],
        ];


        foreach ($schedulesData as $item) {
            $guru = Teacher::where('name', 'LIKE', '%' . $item['guru'] . '%')->first();
            if (!$guru) {
                $this->command->warn("Guru " . $item['guru'] . " tidak ditemukan, modifikasi KbmSeeder.");
                continue;
            }

            foreach ($item['schedules'] as $sch) {
                $classroom = Classroom::where('name', 'Kelas ' . $sch['kelas'])->first();
                if (!$classroom) {
                    $this->command->warn("Kelas " . $sch['kelas'] . " tidak ditemukan.");
                    continue;
                }

                $subjectId = $subjMap[$sch['sub']];

                $assignment = TeachingAssignment::firstOrCreate(
                    [
                        'academic_period_id' => $activePeriod->id,
                        'teacher_id' => $guru->id,
                        'subject_id' => $subjectId,
                        'classroom_id' => $classroom->id
                    ],
                    ['grading_formula' => 'average']
                );

                $timesArr = $timeslots[$sch['time']] ?? null;
                if ($timesArr) {
                    SubjectSchedule::updateOrCreate(
                        ['teaching_assignment_id' => $assignment->id, 'day' => $sch['day']],
                        ['start_time' => $timesArr[0], 'end_time' => $timesArr[1]]
                    );
                } else {
                    $this->command->warn("Timeslot tidak dikenali: " . $sch['time']);
                }
            }
        }
    }
}