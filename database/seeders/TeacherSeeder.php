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
        // 1. Data Guru dari Excel DUK (Daftar Urut Kepangkatan)
        // Format NIP sudah dibersihkan dari spasi
        $teachersData = [
            [
                'name' => 'Imma Setiawati, S.Pd',
                'nip' => '196706121990032010',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1967-12-06',
                'degree' => 'S1',
                'university' => 'UT',
                'graduation_year' => '2003',
                'major' => 'Pend. Biologi',
                'employment_status' => 'PNS',
                'position' => 'Kepala Sekolah',
                'grade' => 'IV.c',
                'rank' => 'Pembina Utama Muda',
                'assignment_date' => '1990-01-03', // TMT Mulai Dinas
            ],
            [
                'name' => 'Yunita, S.Pd.Ind',
                'nip' => '196604161994122001',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1966-16-04',
                'degree' => 'S1',
                'university' => 'UT',
                'graduation_year' => '2009',
                'major' => 'B.Indonesia',
                'employment_status' => 'PNS',
                'position' => 'Guru',
                'grade' => 'IV.b',
                'rank' => 'Pembina Tk 1',
                'assignment_date' => '1994-12-01', // TMT Mulai Dinas
            ],
            [
                'name' => 'Petria Susila, S.P',
                'nip' => '197402102008012002',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1974-10-02',
                'degree' => 'S1',
                'university' => 'UNAND',
                'graduation_year' => '1997',
                'major' => 'Ilmu Tanah',
                'employment_status' => 'PNS',
                'position' => 'Guru',
                'grade' => 'IV.a',
                'rank' => 'Pembina',
                'assignment_date' => '2008-01-01', // TMT Mulai Dinas
            ],
            [
                'name' => 'Asfriyanti, S.Pd',
                'nip' => '197202282014062001',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1972-28-02',
                'degree' => 'S1',
                'university' => 'IKIPM',
                'graduation_year' => '1997',
                'major' => 'B.Inggris',
                'employment_status' => 'PNS',
                'position' => 'Guru',
                'grade' => 'III.b',
                'rank' => 'Penata Muda Tk 1',
                'assignment_date' => '2014-01-06', // TMT Mulai Dinas
            ],
            [
                'name' => 'Riza Mustika, S.Pdi',
                'nip' => '197907152014062006',
                'gender' => 'P',
                'place_of_birth' => '',
                'date_of_birth' => '1979-15-07',
                'degree' => 'S1',
                'university' => 'STAIN',
                'graduation_year' => '2003',
                'major' => 'Tarbiyah',
                'employment_status' => 'PNS',
                'position' => 'Guru',
                'grade' => 'III.b',
                'rank' => 'Penata Muda Tk 1',
                'assignment_date' => '2014-01-06', // TMT Mulai Dinas
            ],
            [
                'name' => 'Winda Arizona Asura, S.Pd',
                'nip' => '199109082022212001',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1991-09-08',
                'degree' => 'S1',
                'university' => 'UNP',
                'graduation_year' => '2017',
                'major' => 'Sendratasik',
                'employment_status' => 'PNS',
                'position' => 'Guru',
                'grade' => 'IX',
                'rank' => NULL,
                'assignment_date' => NULL, // TMT Mulai Dinas
            ],
            [
                'name' => 'Nadya Putri Adha, S.Si',
                'nip' => '199305312023212026',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1993-31-05',
                'degree' => 'S1',
                'university' => 'UNP',
                'graduation_year' => '2015',
                'major' => 'Ilmu Keolahragaan',
                'employment_status' => 'PPPK',
                'position' => 'Guru',
                'grade' => 'IX',
                'rank' => NULL,
                'assignment_date' => NULL, // TMT Mulai Dinas
            ],
            [
                'name' => 'Wempi Afridawati, S.Pd',
                'nip' => '198304042023212027',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1983-04-04',
                'degree' => 'S1',
                'university' => 'STKIP',
                'graduation_year' => '2007',
                'major' => 'Pend. Geografi',
                'employment_status' => 'PPPK',
                'position' => 'Guru',
                'grade' => 'IX',
                'rank' => NULL,
                'assignment_date' => NULL,
            ],
            [
                'name' => 'Yuli Asman, S.Sos',
                'nip' => '199603032024011023',
                'gender' => 'L',
                'place_of_birth' => NULL,
                'date_of_birth' => '1996-03-03',
                'degree' => 'S1',
                'university' => 'UIN SUSKA RIAU',
                'graduation_year' => '2018',
                'major' => 'Ilmu Adm. Negara',
                'employment_status' => 'PPPK',
                'position' => 'Guru',
                'grade' => 'IX',
                'rank' => NULL,
                'assignment_date' => NULL,
            ],
            [
                'name' => 'Neneng Cahyana, S.Pd',
                'nip' => '199804222024212031',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1998-22-04',
                'degree' => 'S1',
                'university' => 'UNP',
                'graduation_year' => '2022',
                'major' => 'Pendidikan BK',
                'employment_status' => 'PPPK',
                'position' => 'Guru',
                'grade' => 'IX',
                'rank' => NULL,
                'assignment_date' => NULL,
            ],
            [
                'name' => 'Gima Ramadan, S.T',
                'nip' => '19901012024211008',
                'gender' => 'L',
                'place_of_birth' => NULL,
                'date_of_birth' => '1999-01-01',
                'degree' => 'S1',
                'university' => 'UMRAH',
                'graduation_year' => '2021',
                'major' => 'Teknik Informatika',
                'employment_status' => 'PPPK',
                'position' => 'Guru',
                'grade' => 'IX',
                'rank' => NULL,
                'assignment_date' => NULL,
            ],
            [
                'name' => 'Anggun Salsabil, S.Pd',
                'nip' => '200011242025212007',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '2000-24-11',
                'degree' => 'S1',
                'university' => 'UIN',
                'graduation_year' => '2021',
                'major' => 'Pend. Matematika',
                'employment_status' => 'PPPK',
                'position' => 'Guru',
                'grade' => 'IX',
                'rank' => NULL,
                'assignment_date' => NULL,
            ],
            [
                'name' => 'Febri Yenni, S.A.P',
                'nip' => '198002182011012005',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1980-18-02',
                'degree' => 'S1',
                'university' => NULL,
                'graduation_year' => '2021',
                'major' => 'Ilmu Adm Publik',
                'employment_status' => 'PNS',
                'position' => 'Fungsional Umum',
                'grade' => 'III.a',
                'rank' => 'Penata Muda',
                'assignment_date' => '2011-01-01',
            ],
            [
                'name' => 'Herdianto',
                'nip' => '196902282025211019',
                'gender' => 'L',
                'place_of_birth' => NULL,
                'date_of_birth' => '1969-28-02',
                'degree' => 'SMA',
                'university' => NULL,
                'graduation_year' => '2021',
                'major' => 'Biologi',
                'employment_status' => 'PPPKPW',
                'position' => 'Operator Layanan Operasional',
                'grade' => NULL,
                'rank' => NULL,
                'assignment_date' => NULL,
            ],
            [
                'name' => 'Yeni Marlina',
                'nip' => '197903242025212026',
                'gender' => 'P',
                'place_of_birth' => NULL,
                'date_of_birth' => '1979-24-03',
                'degree' => 'SMK',
                'university' => NULL,
                'graduation_year' => '2021',
                'major' => 'PS',
                'employment_status' => 'PPPKPW',
                'position' => 'Operator Layanan Operasional',
                'grade' => NULL,
                'rank' => NULL,
                'assignment_date' => NULL,
            ],
            // --- TAMBAHKAN DATA GURU LAINNYA DI SINI ---
        ];

        // 2. Pastikan Role Teacher Ada
        $roleGuru = Role::firstOrCreate(['name' => 'teacher']);

        foreach ($teachersData as $data) {
            // A. Create User Login
            // Username: NIP, Password: guru123
            // Jika NIP kosong, gunakan nama depan + random
            $email = !empty($data['nip'])
                ? $data['nip'] . '@sekolah.com'
                : strtolower(explode(' ', $data['name'])[0]) . rand(100, 999) . '@sekolah.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('guru123'),
                    'role' => 'teacher',
                    'is_active' => true,
                ]
            );

            // Assign Role Shield
            $user->assignRole($roleGuru);

            // B. Create Data Guru
            Teacher::updateOrCreate(
                ['nip' => $data['nip']], // Kunci pengecekan (biar tidak duplikat)
                [
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'gender' => $data['gender'],
                    'place_of_birth' => $data['place_of_birth'],
                    'date_of_birth' => $data['date_of_birth'],
                    'degree' => $data['degree'],
                    'university' => $data['university'],
                    'graduation_year' => $data['graduation_year'],
                    'major' => $data['major'],
                    'employment_status' => $data['employment_status'],
                    'position' => $data['position'],
                    'grade' => $data['grade'],
                    'rank' => $data['rank'],
                    'assignment_date' => $data['assignment_date'],
                    'is_active' => true,
                ]
            );
        }
    }
}