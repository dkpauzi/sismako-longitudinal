<?php

namespace App\Filament\Imports;

use App\Models\Student;
use App\Models\User;
use App\Models\Classroom;
use App\Models\AcademicPeriod;
use App\Models\Enrollment;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

/**
 * SMART IMPORTER: Auto-Account Generation untuk Siswa & Wali.
 *
 * Untuk setiap baris Excel, importer ini akan:
 *   1. Buat/Update akun User Siswa (username = NISN, password = NISN)
 *   2. Buat/Update akun User Wali  (username = WALI_{NISN}, password = WALI_{NISN})
 *   3. Buat/Update profil Student (link ke kedua akun di atas)
 *   4. Buat Enrollment ke kelas aktif (opsional, jika kolom kelas diisi)
 *
 * Semua operasi dibungkus dalam DB::transaction() untuk menjaga konsistensi data.
 */
class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

    // ✅ OPTIMASI: Cache role di level instance agar tidak query per baris Excel.
    private ?Role $studentRole = null;
    private ?Role $guardianRole = null;

    public static function getColumns(): array
    {
        return [
            // --- KOLOM WAJIB (Required) ---
            ImportColumn::make('nisn')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:15'])
                ->example('0012345678')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('nama_siswa')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Budi Santoso')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('jenis_kelamin')
                ->requiredMapping()
                ->rules(['required', 'in:L,P,Laki-laki,Perempuan,Laki-Laki'])
                ->example('L')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('nama_wali')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Suryadi')
                ->fillRecordUsing(fn() => null),

            // --- KOLOM OPSIONAL ---
            ImportColumn::make('nipd')
                ->rules(['nullable', 'string', 'max:20'])
                ->example('25.396')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('nik')
                ->rules(['nullable', 'string', 'max:20'])
                ->example('1303041805130002')
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
                ->rules(['nullable', 'string'])
                ->example('Islam')
                ->fillRecordUsing(fn() => null),

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

            ImportColumn::make('kelas_sekarang')
                ->rules(['nullable', 'string'])
                ->example('Kelas 7.1')
                ->fillRecordUsing(fn() => null),
        ];
    }

    /**
     * Override getValidationMessages() agar pesan error di Failed Rows CSV
     * tampil dalam Bahasa Indonesia.
     */
    public function getValidationMessages(): array
    {
        return [
            'nisn.required' => 'NISN wajib diisi.',
            'nisn.string' => 'NISN harus berupa teks.',
            'nisn.max' => 'NISN maksimal 15 karakter.',
            'nama_siswa.required' => 'Nama siswa wajib diisi.',
            'nama_siswa.string' => 'Nama siswa harus berupa teks.',
            'nama_siswa.max' => 'Nama siswa maksimal 255 karakter.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib diisi.',
            'jenis_kelamin.in' => 'Format jenis kelamin harus L atau P.',
            'nama_wali.required' => 'Nama wali wajib diisi.',
            'nama_wali.string' => 'Nama wali harus berupa teks.',
            'nama_wali.max' => 'Nama wali maksimal 255 karakter.',
            'tanggal_lahir.date_format' => 'Format tanggal lahir harus YYYY-MM-DD (contoh: 2010-08-17).',
        ];
    }

    public function resolveRecord(): ?Student
    {
        $data = $this->data;

        // Ambil data utama
        $nisn = trim($data['nisn'] ?? '');
        $nama = trim($data['nama_siswa'] ?? '');
        $namaWali = trim($data['nama_wali'] ?? '');

        // Format Jenis Kelamin
        $jkInput = strtoupper(trim($data['jenis_kelamin'] ?? ''));
        $gender = ($jkInput === 'P' || $jkInput === 'PEREMPUAN') ? 'P' : 'L';

        // ── SELURUH PROSES DIBUNGKUS DALAM TRANSAKSI ──────────────
        return DB::transaction(function () use ($data, $nisn, $nama, $namaWali, $gender) {

            // ── STEP 1: BUAT/UPDATE AKUN USER SISWA ───────────────
            $studentUser = User::updateOrCreate(
                ['username' => $nisn],
                [
                    'name' => $nama,
                    'email' => null,
                    'password' => Hash::make($nisn),
                    'role' => 'student',
                    'is_active' => true,
                ]
            );

            // Assign Spatie role 'student'
            if (!$studentUser->hasRole('student')) {
                $this->studentRole ??= Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
                $studentUser->assignRole($this->studentRole);
            }

            // ── STEP 2: BUAT/UPDATE AKUN USER WALI ────────────────
            $guardianUsername = 'WALI_' . $nisn;
            $guardianUser = User::updateOrCreate(
                ['username' => $guardianUsername],
                [
                    'name' => $namaWali,
                    'email' => null,
                    'password' => Hash::make($guardianUsername),
                    'role' => 'guardian',
                    'is_active' => true,
                ]
            );

            // Assign Spatie role 'guardian'
            if (!$guardianUser->hasRole('guardian')) {
                $this->guardianRole ??= Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web']);
                $guardianUser->assignRole($this->guardianRole);
            }

            // ── STEP 3: BUAT/UPDATE PROFIL SISWA ──────────────────
            $student = Student::updateOrCreate(
                ['nisn' => $nisn],
                [
                    'user_id' => $studentUser->id,
                    'guardian_user_id' => $guardianUser->id,
                    'name' => $nama,
                    'nipd' => trim($data['nipd'] ?? '') ?: null,
                    'nik' => trim($data['nik'] ?? '') ?: null,
                    'gender' => $gender,
                    'place_of_birth' => trim($data['tempat_lahir'] ?? '') ?: null,
                    'date_of_birth' => trim($data['tanggal_lahir'] ?? '') ?: null,
                    'religion' => trim($data['agama'] ?? '') ?: null,
                    'address' => trim($data['alamat'] ?? '') ?: null,
                    'father_name' => trim($data['nama_ayah'] ?? '') ?: null,
                    'mother_name' => trim($data['nama_ibu'] ?? '') ?: null,
                    'status' => 'active',
                ]
            );

            // ── STEP 4: ENROLLMENT KE KELAS (OPSIONAL) ───────────
            $className = trim($data['kelas_sekarang'] ?? '');
            if ($className !== '') {
                $activePeriod = AcademicPeriod::where('is_active', true)->first();
                if (!$activePeriod) {
                    throw new RowImportFailedException(
                        "Gagal: Tidak ada Tahun Ajaran yang berstatus 'Aktif' di database."
                    );
                }

                $classroom = Classroom::whereRaw('LOWER(name) = ?', [strtolower($className)])->first();
                if (!$classroom) {
                    throw new RowImportFailedException(
                        "Gagal: Kelas '{$className}' tidak ditemukan di database."
                    );
                }

                Enrollment::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_period_id' => $activePeriod->id,
                    ],
                    [
                        'classroom_id' => $classroom->id,
                        'status' => 'active',
                    ]
                );
            }

            return $student;
        });
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Proses impor data siswa selesai. '
            . number_format($import->successful_rows)
            . ' siswa berhasil dibuatkan akun (siswa + wali) dan profil.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' Namun, ada ' . number_format($failedRowsCount)
                . ' baris yang gagal. Silakan unduh laporan error untuk detailnya.';
        }

        return $body;
    }
}