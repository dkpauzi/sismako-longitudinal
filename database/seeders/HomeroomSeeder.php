<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\Classroom;
use App\Models\AcademicPeriod;
use App\Models\ClassHomeroom;

class HomeroomSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ambil Periode Aktif
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (!$activePeriod) {
            $this->command->error('Tidak ada Tahun Ajaran aktif! Pastikan SchoolSeeder sudah dijalankan.');
            return;
        }

        // 2. Daftar Pemetaan (Manual Mapping)
        // Format: 'Nama Kelas' => 'Nama Guru (Potongan nama depan saja cukup)'
        $assignments = [
            'Kelas 7.1' => 'Gima',
            'Kelas 7.2' => 'Nadya',
            'Kelas 8.1' => 'Riza',
            'Kelas 8.2' => 'Asfriyanti',
            'Kelas 9.1' => 'Wempi',
        ];

        foreach ($assignments as $className => $teacherName) {
            // Cari Kelas
            $classroom = Classroom::where('name', $className)->first();

            // Cari Guru (menggunakan 'like' agar tidak perlu nulis gelar panjangnya)
            $teacher = Teacher::where('name', 'LIKE', "%$teacherName%")->first();

            if ($classroom && $teacher) {
                // Simpan ke tabel ClassHomeroom
                ClassHomeroom::firstOrCreate([
                    'classroom_id' => $classroom->id,
                    'academic_period_id' => $activePeriod->id,
                ], [
                    'teacher_id' => $teacher->id,
                    'is_current' => true,
                ]);

                $this->command->info("Berhasil: $teacher->name menjadi Wali Kelas $classroom->name");
            } else {
                $this->command->warn("Gagal: Guru '$teacherName' atau Kelas '$className' tidak ditemukan.");
            }
        }
    }
}