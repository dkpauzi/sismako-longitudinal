<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\AcademicPeriod;
use App\Models\SchoolProfile;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat User Admin
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@sekolah.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // 2. Buat Tahun Ajaran
        $period = AcademicPeriod::create([
            'name' => '2025/2026 Ganjil',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
            'is_active' => false,
        ]);

        AcademicPeriod::create([
            'name' => '2025/2026 Genap',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        // 3. Buat Mata Pelajaran (Pakai Array biar cepat)
        $subjects = [
            ['name' => 'Matematika', 'code' => 'MTK'],
            ['name' => 'Bahasa Indonesia', 'code' => 'BIND'],
            ['name' => 'Bahasa Inggris', 'code' => 'BING'],
            ['name' => 'Ilmu Pengetahuan Alam', 'code' => 'IPA'],
            ['name' => 'Ilmu Pengetahuan Sosial', 'code' => 'IPS'],
            ['name' => 'Pendidikan Agama Islam', 'code' => 'PAI'],
            ['name' => 'PJOK', 'code' => 'PJOK'],
            ['name' => 'Informatika', 'code' => 'INF'],
        ];

        foreach ($subjects as $sub) {
            Subject::create($sub);
        }

        // 4. Buat Kelas (Looping)
        $classes = [
            ['name' => '7A', 'grade_level' => 7],
            ['name' => '7B', 'grade_level' => 7],
            ['name' => '8A', 'grade_level' => 8],
            ['name' => '8B', 'grade_level' => 8],
            ['name' => '9A', 'grade_level' => 9],
        ];

        foreach ($classes as $cls) {
            Classroom::create($cls);
        }

        // 5. Buat Profil Sekolah Default
        SchoolProfile::create([
            'name' => 'SMP Teknologi Maju',
            'email' => 'info@smptekno.sch.id',
            'phone' => '021-12345678',
            'address' => 'Jl. Pendidikan No. 1, Jakarta',
            'accreditation' => 'A',
            'primary_color' => '#007bff', // Warna biru default
        ]);

        // --- BONUS: DATA USER GURU & SISWA ---

        // Buat User Guru
        $userGuru = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@sekolah.com',
            'password' => Hash::make('password'),
            'role' => 'teacher',
            'is_active' => true,
        ]);

        // Buat Data Teacher & Link ke User tadi
        Teacher::create([
            'user_id' => $userGuru->id,
            'nip' => '198501012010011001',
            'name' => $userGuru->name,
            'email' => $userGuru->email,
            'is_active' => true,
        ]);

        // Buat User Siswa
        $userSiswa = User::create([
            'name' => 'Zayyan Alfarizqi',
            'email' => 'zayyan@sekolah.com',
            'password' => Hash::make('password'),
            'role' => 'student',
            'is_active' => true,
        ]);

        // Buat Data Student & Link ke User tadi
        Student::create([
            'user_id' => $userSiswa->id,
            'nisn' => '0051234567',
            'name' => $userSiswa->name,
            'gender' => 'L',
            'status' => 'active',
            'date_of_birth' => '2012-05-15',
        ]);
    }
}