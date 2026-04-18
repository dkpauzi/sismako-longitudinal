<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Teacher;
use Spatie\Permission\Models\Role;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        // 2. Pastikan Role Teacher Ada
        $roleGuru = Role::firstOrCreate(['name' => 'teacher']);

        $csvPath = base_path('Draft_Import_Data_Guru.csv');
        if (!file_exists($csvPath)) {
            $this->command->error("File $csvPath tidak ditemukan.");
            return;
        }

        $file = fopen($csvPath, 'r');
        // Baca header
        $header = fgetcsv($file, 1000, ';');

        while (($row = fgetcsv($file, 1000, ';')) !== false) {
            // Abaikan baris kosong
            if (empty(array_filter($row))) {
                continue;
            }

            $data = array_combine($header, $row);
            
            // Format tanggal_lahir dari DD/MM/YYYY ke YYYY-MM-DD
            $dob = null;
            if (!empty($data['tanggal_lahir'])) {
                $parts = explode('/', $data['tanggal_lahir']);
                if (count($parts) == 3) {
                    $dob = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                }
            }

            $assignmentDate = null;
            if (!empty($data['mulai_dinas'])) {
                $parts = explode('/', $data['mulai_dinas']);
                if (count($parts) == 3) {
                    $assignmentDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                }
            }

            // A. Create User Login
            $email = !empty($data['nip'])
                ? $data['nip'] . '@sekolah.com'
                : strtolower(explode(' ', $data['nama_guru'])[0]) . rand(100, 999) . '@sekolah.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['nama_guru'],
                    'password' => Hash::make('guru123'),
                    'role' => 'teacher',
                    'is_active' => true,
                ]
            );

            // Assign Role Shield
            $user->assignRole($roleGuru);

            // B. Create Data Guru
            Teacher::updateOrCreate(
                ['nip' => $data['nip'] ?: null],
                [
                    'user_id' => $user->id,
                    'name' => $data['nama_guru'],
                    'gender' => $data['jenis_kelamin'] ?: null,
                    'place_of_birth' => $data['tempat_lahir'] ?: null,
                    'date_of_birth' => $dob,
                    'degree' => $data['gelar_pendidikan'] ?: null,
                    'university' => $data['asal_kampus'] ?: null,
                    'graduation_year' => $data['tahun_lulus'] ?: null,
                    'major' => $data['jurusan'] ?: null,
                    'employment_status' => $data['status_pegawai'] ?: null,
                    'position' => $data['jabatan'] ?: null,
                    'grade' => $data['golongan'] ?: null,
                    'rank' => $data['pangkat'] ?: null,
                    'assignment_date' => $assignmentDate,
                    'is_active' => true,
                ]
            );
        }

        fclose($file);
    }
}