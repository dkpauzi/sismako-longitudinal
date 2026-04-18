<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SchoolOrganizationStructure;
use App\Models\SchoolProfile;

class SchoolOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $profile = SchoolProfile::first();
        if (!$profile) {
            $this->command->error('Profil Sekolah (SchoolProfile) tidak ditemukan. Pastikan SchoolSeeder sudah dijalankan.');
            return;
        }

        SchoolOrganizationStructure::truncate();

        $structures = [
            ['position' => 'Kepala Sekolah', 'name' => 'Reni Ofriyanti, S. Pd', 'order' => 1],
            
            // Wali Kelas + Guru Mapel
            ['position' => 'Wali Kelas 7.1 & Guru Mapel TIK, Kokurikuler', 'name' => 'Gima Ramadan, S.T', 'order' => 2],
            ['position' => 'Wali Kelas 7.2 & Guru Mapel PJOK, Kokurikuler', 'name' => 'Nadya Putri Adha, S.Si', 'order' => 3],
            ['position' => 'Wali Kelas 8.1 & Guru Mapel PAI, Kokurikuler', 'name' => 'Riza Mustika, S.Pdi', 'order' => 4],
            ['position' => 'Wali Kelas 8.2 & Guru Mapel B. Inggris, Kokurikuler', 'name' => 'Asfriyanti, S.Pd', 'order' => 5],
            ['position' => 'Wali Kelas 9.1 & Guru Mapel IPS, Kokurikuler', 'name' => 'Wempi Afridawati, S.Pd', 'order' => 6],
            
            // Guru Mapel Saja
            ['position' => 'Guru Mapel Bahasa Indonesia, Kokurikuler', 'name' => 'Yunita, S.Pd.Ind', 'order' => 7],
            ['position' => 'Guru Mapel IPA, Kokurikuler', 'name' => 'Petria Susila, S.P', 'order' => 8],
            ['position' => 'Guru Mapel Seni Budaya, Kokurikuler', 'name' => 'Winda Arizona Asura, S.Pd', 'order' => 9],
            ['position' => 'Guru Mapel PKN, Kokurikuler', 'name' => 'Yuli Asman, S.Sos', 'order' => 10],
            ['position' => 'Guru Mapel Matematika, Kokurikuler', 'name' => 'Anggun Salsabil, S.Pd', 'order' => 11],
            ['position' => 'Koordinator BK', 'name' => 'Neneng Cahyana, S.Pd', 'order' => 12],
            ['position' => 'Kepala Tata Usaha', 'name' => 'Febri Yenni, S.A.P', 'order' => 13]
        ];

        foreach ($structures as $st) {
            SchoolOrganizationStructure::create([
                'school_profile_id' => $profile->id,
                'position' => $st['position'],
                'name' => $st['name'],
                'order' => $st['order']
            ]);
        }

        $this->command->info('Struktur Organisasi Sekolah berhasil di-seed secara manual.');
    }
}
