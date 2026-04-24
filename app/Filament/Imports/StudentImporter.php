<?php

namespace App\Filament\Imports;

use App\Models\Student;
use App\Models\User;
use App\Models\Classroom;
use App\Models\AcademicPeriod;
use App\Models\Enrollment;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

    // ✅ OPTIMASI: Cache role di level instance agar tidak query per baris Excel.
    // Untuk 200 siswa, ini menghilangkan ~400 query Role::firstOrCreate() yang berulang.
    private ?Role $studentRole = null;
    private ?Role $guardianRole = null;

    public static function getColumns(): array
    {
        return [
            // --- DATA AKUN & IDENTITAS UTAMA ---
            ImportColumn::make('nisn')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:15'])
                ->example('0012345678')
                ->fillRecordUsing(fn() => null), // Mencegah Filament menyimpan otomatis, kita urus manual di bawah

            ImportColumn::make('nipd')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:20'])
                ->example('25.396')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('nama_siswa')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Budi Santoso')
                ->fillRecordUsing(fn() => null),

            // --- DATA BIODATA PRIBADI ---
            ImportColumn::make('nik')
                ->rules(['nullable', 'string', 'max:20'])
                ->example('1303041805130002')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('jenis_kelamin')
                ->requiredMapping()
                ->rules(['required', 'in:L,P,Laki-laki,Perempuan,Laki-Laki'])
                ->example('L')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('tempat_lahir')
                ->rules(['nullable', 'string'])
                ->example('Sijunjung')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('tanggal_lahir')
                ->rules(['nullable', 'date_format:Y-m-d'])
                ->example('2010-08-17')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('agama')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('Islam')
                ->fillRecordUsing(fn() => null),

            // --- DATA KELUARGA & ALAMAT ---
            ImportColumn::make('alamat')
                ->rules(['nullable', 'string'])
                ->example('Jorong Pematang Anjuang')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('nama_ayah')
                ->rules(['nullable', 'string'])
                ->example('Suryadi')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('nama_ibu')
                ->rules(['nullable', 'string'])
                ->example('Siti')
                ->fillRecordUsing(fn() => null),

            // --- PENEMPATAN KELAS ---
            ImportColumn::make('kelas_sekarang')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('Kelas 7.1')
                ->fillRecordUsing(fn() => null),
        ];
    }

    public function resolveRecord(): ?Student
    {
        $data = $this->data;

        // 1. CARI TAHUN AJARAN AKTIF (Sangat Penting untuk Enrollment)
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        if (!$activePeriod) {
            throw new RowImportFailedException("Gagal: Tidak ada Tahun Ajaran yang berstatus 'Aktif' di database.");
        }

        // 2. CARI KELAS
        $className = trim($data['kelas_sekarang'] ?? '');
        $classroom = Classroom::whereRaw('LOWER(name) = ?', [strtolower($className)])->first();
        if (!$classroom) {
            throw new RowImportFailedException("Gagal: Kelas '{$className}' tidak ditemukan di database.");
        }

        // 3. AMBIL DATA UTAMA (NISN dan Nama)
        $nisn = trim($data['nisn'] ?? '');
        $nama = trim($data['nama_siswa'] ?? '');

        // 4. BUAT ATAU UPDATE AKUN USER (LOGIN)
        $user = User::firstOrCreate(
            ['username' => $nisn], // Cari berdasarkan Username = NISN
            [
                'name' => $nama,
                'email' => null,
                'password' => Hash::make($nisn), // Password default = NISN
                'role' => 'student',
                'is_active' => true,
            ]
        );

        // Pastikan role Spatie diberikan
        if (method_exists($user, 'assignRole') && !$user->hasRole('student')) {
            $this->studentRole ??= Role::firstOrCreate(['name' => 'student']);
            $user->assignRole($this->studentRole);
        }

        // 4b. BUAT AKUN WALI SISWA (GUARDIAN)
        // Username: w_{NISN}, Password default: wali123
        // Jika sudah ada (misal: re-import), data akun tidak ditimpa.
        $guardianUser = User::firstOrCreate(
            ['username' => 'w_' . $nisn],
            [
                'name'      => 'Wali dari ' . $nama,
                'email'     => null,
                'password'  => Hash::make('wali123'),
                'role'      => 'guardian',
                'is_active' => true,
            ]
        );

        // Pastikan role Spatie 'wali_siswa' diberikan ke akun wali
        if (method_exists($guardianUser, 'assignRole') && !$guardianUser->hasRole('wali_siswa')) {
            $this->guardianRole ??= Role::firstOrCreate(['name' => 'wali_siswa']);
            $guardianUser->assignRole($this->guardianRole);
        }

        // 5. BUAT ATAU UPDATE PROFIL SISWA
        // Format Jenis Kelamin (Laki-laki -> L, Perempuan -> P)
        $jkInput = strtoupper(trim($data['jenis_kelamin'] ?? ''));
        $gender = ($jkInput === 'P' || $jkInput === 'PEREMPUAN') ? 'P' : 'L';

        $student = Student::updateOrCreate(
            ['nisn' => $nisn], // Patokan utamanya adalah NISN
            [
                'user_id' => $user->id,
                'guardian_user_id' => $guardianUser->id, // Link ke akun wali
                'name' => $nama,
                'nipd' => trim($data['nipd'] ?? ''),
                'nik' => trim($data['nik'] ?? null),
                'gender' => $gender,
                'place_of_birth' => trim($data['tempat_lahir'] ?? null),
                'date_of_birth' => trim($data['tanggal_lahir'] ?? null),
                'religion' => trim($data['agama'] ?? 'Islam'),
                'address' => trim($data['alamat'] ?? null),
                'father_name' => trim($data['nama_ayah'] ?? null),
                'mother_name' => trim($data['nama_ibu'] ?? null),
                'status' => 'active',
            ]
        );

        // 6. MASUKKAN SISWA KE KELAS (ENROLLMENT)
        // updateOrCreate digunakan agar jika di-import ulang, datanya ditimpa, bukan digandakan.
        Enrollment::updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_period_id' => $activePeriod->id, // Di tahun ajaran aktif ini...
            ],
            [
                'classroom_id' => $classroom->id, // ...dia masuk ke kelas ini
                'status' => 'active'
            ]
        );

        return $student;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Proses import data Siswa telah selesai. ' . number_format($import->successful_rows) . ' siswa berhasil dibuatkan akun (siswa + wali) dan dimasukkan ke kelas.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' Namun, ada ' . number_format($failedRowsCount) . ' baris yang gagal. Silakan unduh log error untuk detailnya.';
        }

        return $body;
    }
}