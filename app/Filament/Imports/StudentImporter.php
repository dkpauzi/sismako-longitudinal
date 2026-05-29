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
 * ARSITEKTUR SHARED HOSTING (QUEUE_CONNECTION=sync):
 * - $chunkSize = 50 → Filament memproses 50 baris per batch, bukan seluruh CSV.
 * - Setiap baris dibungkus dalam DB::transaction() tersendiri.
 * - Kegagalan baris N TIDAK mempengaruhi baris N-1 atau N+1.
 * - gc_collect_cycles() dipanggil setiap 25 baris untuk mencegah memory leak.
 *
 * Untuk setiap baris Excel, importer ini akan:
 *   1. Buat/Update akun User Siswa (username = NISN, password = NISN)
 *   2. Buat/Update akun User Wali  (username = WALI_{NISN}, password = WALI_{NISN})
 *   3. Buat/Update profil Student (link ke kedua akun di atas)
 *   4. Buat Enrollment ke kelas aktif (opsional, jika kolom kelas diisi)
 */
class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

    /**
     * OPTIMASI SHARED HOSTING: Ukuran chunk kecil.
     * Filament akan memproses 50 baris per batch synchronous,
     * bukan seluruh file sekaligus.
     */
    public static int $chunkSize = 50;

    // Cache role di level instance agar tidak query DB per baris Excel.
    private ?Role $studentRole = null;
    private ?Role $guardianRole = null;

    // Counter internal untuk GC trigger
    private int $rowCounter = 0;

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
                ->rules(['nullable'])
                ->example('17-08-2010 (Gunakan format teks DD-MM-YYYY)')
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
            // Keterangan tanggal lahir dihapus karena sekarang otomatis di-parse
        ];
    }

    /**
     * Proses satu baris CSV.
     *
     * FAULT TOLERANCE:
     * - Setiap baris dibungkus dalam DB::transaction() sendiri.
     * - Jika baris 12 gagal (duplikat, validation error), baris 11 sudah commit
     *   dan baris 13 akan tetap diproses.
     * - RowImportFailedException dicatat di Failed Rows Log (CSV downloadable).
     * - gc_collect_cycles() dipanggil setiap 25 baris untuk mengontrol RAM.
     */
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

        // ── VALIDASI BISNIS TAMBAHAN ─────────────────────────────────
        if ($nisn === '') {
            throw new RowImportFailedException('NISN kosong atau tidak valid.');
        }

        // ── TRIPLE-LAYER DATE SANITIZATION (TANGGAL LAHIR) ───────────
        $rawDate = $data['tanggal_lahir'] ?? null;
        $parsedDate = null;

        if (!empty($rawDate)) {
            try {
                if ($rawDate instanceof \DateTimeInterface) {
                    // Scenario A: Object
                    $parsedDate = $rawDate->format('Y-m-d');
                } elseif (is_numeric($rawDate)) {
                    // Scenario B: Excel Serial Date atau Unix Timestamp
                    // Excel epoch = 1900-01-01. Offset 25569 hari ke Unix epoch 1970
                    if ($rawDate > 25569) {
                        $parsedDate = \Carbon\Carbon::createFromTimestamp(($rawDate - 25569) * 86400)->format('Y-m-d');
                    } else {
                        $parsedDate = null;
                    }
                } else {
                    // Scenario C: String parsing
                    // Jika format DD-MM-YYYY (Indonesian format)
                    if (preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/', $rawDate)) {
                        $parsedDate = \Carbon\Carbon::createFromFormat('d-m-Y', str_replace('/', '-', $rawDate))->format('Y-m-d');
                    } else {
                        $parsedDate = \Carbon\Carbon::parse($rawDate)->format('Y-m-d');
                    }
                }
            } catch (\Exception $e) {
                // Scenario D: Fallback to null on failure
                $parsedDate = null;
            }
        }

        // ── SELURUH PROSES DIBUNGKUS DALAM TRANSAKSI PER BARIS ──────
        $student = DB::transaction(function () use ($data, $nisn, $nama, $namaWali, $gender, $parsedDate) {

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
                    'date_of_birth' => $parsedDate,
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

        // ── GARBAGE COLLECTION ───────────────────────────────────────
        // Panggil gc setiap 25 baris untuk mencegah memory leak
        // saat memproses CSV besar secara synchronous.
        $this->rowCounter++;
        if ($this->rowCounter % 25 === 0) {
            gc_collect_cycles();
        }

        return $student;
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