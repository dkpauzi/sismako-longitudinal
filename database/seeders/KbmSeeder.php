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
        // =====================================================================
        // 1. SIAPKAN DATA MASTER (PERIODE & MAPEL)
        // =====================================================================
        //$periodGanjil = AcademicPeriod::where('start_year', 2025)->where('semester', 'odd')->first();
        //$periodGenap = AcademicPeriod::where('start_year', 2025)->where('semester', 'even')->first();

        //$mtk = Subject::where('code', 'MTK')->first();
        //$bind = Subject::where('code', 'BIND')->first();
        //$bing = Subject::where('code', 'BING')->first();
        //$ipa = Subject::where('code', 'IPA')->first();
        //$ips = Subject::where('code', 'IPS')->first();
        //$pai = Subject::where('code', 'PAI')->first();
        //$pjok = Subject::where('code', 'PJOK')->first();
        //$tik = Subject::where('code', 'TIK')->first();
        //$pkn = Subject::where('code', 'PKN')->first();
        //$bud = Subject::where('code', 'BUD')->first();
        //$bk = Subject::where('code', 'BK')->first();
        //$kok = Subject::where('code', 'KOK')->first(); // Mapel Kokurikuler P5

        //if (!$periodGanjil || !$mtk) {
        //    $this->command->warn('Seeder dibatalkan: Data Master tidak lengkap. Pastikan SchoolSeeder sudah dijalankan.');
        //    return;
        //}

        // =====================================================================
        // 2. SIAPKAN DATA KELAS
        // =====================================================================
        $kelas71 = Classroom::firstOrCreate(['name' => 'Kelas 7.1'], ['grade_level' => 7]);
        $kelas72 = Classroom::firstOrCreate(['name' => 'Kelas 7.2'], ['grade_level' => 7]);
        $kelas81 = Classroom::firstOrCreate(['name' => 'Kelas 8.1'], ['grade_level' => 8]);
        $kelas82 = Classroom::firstOrCreate(['name' => 'Kelas 8.2'], ['grade_level' => 8]);
        $kelas91 = Classroom::firstOrCreate(['name' => 'Kelas 9.1'], ['grade_level' => 9]);

        // =====================================================================
        // 3. SIAPKAN DATA GURU (Berdasarkan Nama Panggilan)
        // =====================================================================
        //$guruYunita = Teacher::firstOrCreate(['name' => 'Yunita, S.Pd.Ind']);
        //$guruWinda = Teacher::firstOrCreate(['name' => 'Winda Arizona Asura, S.Pd']);
        //$guruPetria = Teacher::firstOrCreate(['name' => 'Petria Susila, S.P']);
        //$guruNadya = Teacher::firstOrCreate(['name' => 'Nadya Putri Adha, S.Si']);
        //$guruAsfriyanti = Teacher::firstOrCreate(['name' => 'Asfriyanti, S.Pd']);
        //$guruYuli = Teacher::firstOrCreate(['name' => 'Yuli Asman, S.Sos']);
        //$guruRiza = Teacher::firstOrCreate(['name' => 'Riza Mustika, S.Pdi']);
        //$guruGima = Teacher::firstOrCreate(['name' => 'Gima Ramadan, S.T']);
        //$guruWempi = Teacher::firstOrCreate(['name' => 'Wempi Afridawati, S.Pd']);
        //$guruAnggun = Teacher::firstOrCreate(['name' => 'Anggun Salsabil, S.Pd']);
        //$guruNeneng = Teacher::firstOrCreate(['name' => 'Neneng Cahyana, S.Pd']);

        /*
        // =====================================================================
        // 4. PEMETAAN SK MENGAJAR DAN JADWAL (DINONAKTIFKAN UNTUK TES IMPORT)
        // =====================================================================
        if ($periodGanjil) {
            // [KELAS 7.1] - Senin
            $taBind71 = TeachingAssignment::firstOrCreate(
                ['academic_period_id' => $periodGanjil->id, 'teacher_id' => $guruYunita->id, 'subject_id' => $bind->id, 'classroom_id' => $kelas71->id],
                ['grading_formula' => 'average']
            );
            SubjectSchedule::updateOrCreate(['teaching_assignment_id' => $taBind71->id, 'day' => 'Senin'], ['start_time' => '08:50', 'end_time' => '10:10']);

            $taIpa71 = TeachingAssignment::firstOrCreate(
                ['academic_period_id' => $periodGanjil->id, 'teacher_id' => $guruPetria->id, 'subject_id' => $ipa->id, 'classroom_id' => $kelas71->id],
                ['grading_formula' => 'average']
            );
            SubjectSchedule::updateOrCreate(['teaching_assignment_id' => $taIpa71->id, 'day' => 'Senin'], ['start_time' => '10:40', 'end_time' => '12:00']);

            $taPai71 = TeachingAssignment::firstOrCreate(
                ['academic_period_id' => $periodGanjil->id, 'teacher_id' => $guruRiza->id, 'subject_id' => $pai->id, 'classroom_id' => $kelas71->id],
                ['grading_formula' => 'average']
            );
            SubjectSchedule::updateOrCreate(['teaching_assignment_id' => $taPai71->id, 'day' => 'Senin'], ['start_time' => '13:40', 'end_time' => '15:00']);

            // [KELAS 8.1] - Contoh Tambahan Mapel Akademik
            $taMtk81 = TeachingAssignment::firstOrCreate(
                ['academic_period_id' => $periodGanjil->id, 'teacher_id' => $guruAnggun->id, 'subject_id' => $mtk->id, 'classroom_id' => $kelas81->id],
                ['grading_formula' => 'weighting']
            );
            SubjectSchedule::updateOrCreate(['teaching_assignment_id' => $taMtk81->id, 'day' => 'Selasa'], ['start_time' => '07:30', 'end_time' => '09:00']);
        }

        // =====================================================================
        // 5. PEMETAAN TEAM TEACHING KOKURIKULER P5 (DINONAKTIFKAN UNTUK TES IMPORT)
        // =====================================================================
        $kokurikulerAssignments = [

            // Kelas 7.1
            ['guru' => $guruYunita, 'kelas' => $kelas71, 'day' => 'Senin', 'start' => '08:10', 'end' => '08:50'],
            ['guru' => $guruWinda, 'kelas' => $kelas71, 'day' => 'Senin', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruPetria, 'kelas' => $kelas71, 'day' => 'Selasa', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruGima, 'kelas' => $kelas71, 'day' => 'Selasa', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruWempi, 'kelas' => $kelas71, 'day' => 'Rabu', 'start' => '11:20', 'end' => '12:00'],
            ['guru' => $guruAsfriyanti, 'kelas' => $kelas71, 'day' => 'Rabu', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruRiza, 'kelas' => $kelas71, 'day' => 'Kamis', 'start' => '07:30', 'end' => '08:10'],
            ['guru' => $guruNadya, 'kelas' => $kelas71, 'day' => 'Kamis', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruYuli, 'kelas' => $kelas71, 'day' => 'Jumat', 'start' => '08:30', 'end' => '09:10'],
            ['guru' => $guruAnggun, 'kelas' => $kelas71, 'day' => 'Kamis', 'start' => '09:10', 'end' => '09:50'],

            // Kelas 7.2
            ['guru' => $guruPetria, 'kelas' => $kelas72, 'day' => 'Senin', 'start' => '08:10', 'end' => '08:50'],
            ['guru' => $guruNadya, 'kelas' => $kelas72, 'day' => 'Senin', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruRiza, 'kelas' => $kelas72, 'day' => 'Selasa', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruAnggun, 'kelas' => $kelas72, 'day' => 'Selasa', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruWinda, 'kelas' => $kelas72, 'day' => 'Rabu', 'start' => '11:20', 'end' => '12:00'],
            ['guru' => $guruWempi, 'kelas' => $kelas72, 'day' => 'Rabu', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruGima, 'kelas' => $kelas72, 'day' => 'Kamis', 'start' => '07:30', 'end' => '08:10'],
            ['guru' => $guruPetria, 'kelas' => $kelas72, 'day' => 'Kamis', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruAsfriyanti, 'kelas' => $kelas72, 'day' => 'Jumat', 'start' => '08:30', 'end' => '09:10'],
            ['guru' => $guruYunita, 'kelas' => $kelas72, 'day' => 'Kamis', 'start' => '09:10', 'end' => '09:50'],

            // Kelas 8.1
            ['guru' => $guruAsfriyanti, 'kelas' => $kelas81, 'day' => 'Senin', 'start' => '08:10', 'end' => '08:50'],
            ['guru' => $guruYuli, 'kelas' => $kelas81, 'day' => 'Senin', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruWempi, 'kelas' => $kelas81, 'day' => 'Selasa', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruPetria, 'kelas' => $kelas81, 'day' => 'Selasa', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruNadya, 'kelas' => $kelas81, 'day' => 'Rabu', 'start' => '11:20', 'end' => '12:00'],
            ['guru' => $guruWinda, 'kelas' => $kelas81, 'day' => 'Rabu', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruYunita, 'kelas' => $kelas81, 'day' => 'Kamis', 'start' => '07:30', 'end' => '08:10'],
            ['guru' => $guruGima, 'kelas' => $kelas81, 'day' => 'Kamis', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruAnggun, 'kelas' => $kelas81, 'day' => 'Jumat', 'start' => '08:30', 'end' => '09:10'],
            ['guru' => $guruRiza, 'kelas' => $kelas81, 'day' => 'Kamis', 'start' => '09:10', 'end' => '09:50'],

            // Kelas 8.2 (2 Guru)
            ['guru' => $guruRiza, 'kelas' => $kelas82, 'day' => 'Senin', 'start' => '08:10', 'end' => '08:50'],
            ['guru' => $guruGima, 'kelas' => $kelas82, 'day' => 'Senin', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruWinda, 'kelas' => $kelas82, 'day' => 'Selasa', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruWempi, 'kelas' => $kelas82, 'day' => 'Selasa', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruYunita, 'kelas' => $kelas82, 'day' => 'Rabu', 'start' => '11:20', 'end' => '12:00'],
            ['guru' => $guruPetria, 'kelas' => $kelas82, 'day' => 'Rabu', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruAnggun, 'kelas' => $kelas82, 'day' => 'Kamis', 'start' => '07:30', 'end' => '08:10'],
            ['guru' => $guruAsfriyanti, 'kelas' => $kelas82, 'day' => 'Kamis', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruNadya, 'kelas' => $kelas82, 'day' => 'Jumat', 'start' => '08:30', 'end' => '09:10'],
            ['guru' => $guruYuli, 'kelas' => $kelas82, 'day' => 'Kamis', 'start' => '09:10', 'end' => '09:50'],

            // Kelas 9.1 (2 Guru)
            ['guru' => $guruWempi, 'kelas' => $kelas91, 'day' => 'Senin', 'start' => '08:10', 'end' => '08:50'],
            ['guru' => $guruAnggun, 'kelas' => $kelas91, 'day' => 'Senin', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruYuli, 'kelas' => $kelas81, 'day' => 'Selasa', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruWinda, 'kelas' => $kelas81, 'day' => 'Selasa', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruGima, 'kelas' => $kelas81, 'day' => 'Rabu', 'start' => '11:20', 'end' => '12:00'],
            ['guru' => $guruNadya, 'kelas' => $kelas81, 'day' => 'Rabu', 'start' => '14:20', 'end' => '15:00'],
            ['guru' => $guruPetria, 'kelas' => $kelas81, 'day' => 'Kamis', 'start' => '07:30', 'end' => '08:10'],
            ['guru' => $guruYunita, 'kelas' => $kelas81, 'day' => 'Kamis', 'start' => '13:00', 'end' => '13:40'],
            ['guru' => $guruRiza, 'kelas' => $kelas81, 'day' => 'Jumat', 'start' => '08:30', 'end' => '09:10'],
            ['guru' => $guruAsfriyanti, 'kelas' => $kelas81, 'day' => 'Kamis', 'start' => '09:10', 'end' => '09:50'],
        ];

        foreach ($kokurikulerAssignments as $assign) {
            $taKok = TeachingAssignment::firstOrCreate([
                'academic_period_id' => $periodGanjil->id,
                'teacher_id' => $assign['guru']->id,
                'subject_id' => $kok->id, // MAPEL PASTI KOKURIKULER
                'classroom_id' => $assign['kelas']->id,
            ]);

            SubjectSchedule::updateOrCreate(
                [
                    'teaching_assignment_id' => $taKok->id,
                    'day' => $assign['day']
                ],
                [
                    'start_time' => $assign['start'],
                    'end_time' => $assign['end']
                ]
            );
        }

        // =====================================================================
        // 6. SEEDING TUJUAN PEMBELAJARAN TP (DINONAKTIFKAN UNTUK TES IMPORT)
        // =====================================================================

        // A. CONTOH TP UNTUK PKN KELAS 7 (Oleh Bpk. Yuli Asman)
        $tpPknKelas7 = [
            ['period' => $periodGanjil, 'code' => 'TP-7.1.1', 'content' => 'Peserta didik mampu memahami sejarah kelahiran Pancasila', 'attribute' => 'Memahami sejarah kelahiran Pancasila'],
            ['period' => $periodGanjil, 'code' => 'TP-7.1.2', 'content' => 'Peserta didik dapat menerapkan norma dan aturan', 'attribute' => 'Menerapkan norma dan aturan'],
            ['period' => $periodGenap, 'code' => 'TP-7.2.1', 'content' => 'Peserta didik mampu memahami Proklamasi kemerdekaan Republik Indonesia', 'attribute' => 'Memahami Proklamasi kemerdekaan RI'],
        ];

        foreach ($tpPknKelas7 as $tp) {
            LearningObjective::updateOrCreate(
                ['code' => $tp['code'], 'teacher_id' => $guruYuli->id, 'academic_period_id' => $tp['period']->id],
                ['subject_id' => $pkn->id, 'grade_level' => 7, 'phase' => 'D', 'content' => $tp['content'], 'attribute' => $tp['attribute']]
            );
        }

        // B. CONTOH TP UNTUK IPA KELAS 8 (Oleh Ibu Petria Susila)
        $tpIpaKelas8 = [
            [
                'period' => $periodGanjil,
                'code' => 'TP-IPA-8.1.1',
                'content' => 'Peserta didik mampu mengidentifikasi sistem pencernaan pada manusia dan fungsinya.',
                'attribute' => 'Mengidentifikasi sistem pencernaan manusia'
            ],
            [
                'period' => $periodGanjil,
                'code' => 'TP-IPA-8.1.2',
                'content' => 'Peserta didik dapat menjelaskan proses pernapasan dan organ-organ yang terlibat.',
                'attribute' => 'Menjelaskan proses pernapasan'
            ],
        ];

        foreach ($tpIpaKelas8 as $tp) {
            LearningObjective::updateOrCreate(
                [
                    'code' => $tp['code'],
                    'teacher_id' => $guruPetria->id, // Disesuaikan ke variabel guru Petria
                    'academic_period_id' => $tp['period']->id
                ],
                [
                    'subject_id' => $ipa->id,
                    'grade_level' => 8,
                    'phase' => 'D',
                    'content' => $tp['content'],
                    'attribute' => $tp['attribute']
                ]
            );
        }
        */
    }
}