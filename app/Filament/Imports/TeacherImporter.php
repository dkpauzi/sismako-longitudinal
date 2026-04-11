<?php

namespace App\Filament\Imports;

use Carbon\Carbon;
use Exception;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class TeacherImporter extends Importer
{
    protected static ?string $model = Teacher::class;

    /**
     * Mendefinisikan struktur kolom yang akan dibaca dari file Excel (CSV).
     */
    public static function getColumns(): array
    {
        return [
            // --- DATA UTAMA & KONTAK ---
            ImportColumn::make('nip')
                ->rules(['nullable', 'string', 'max:25'])
                ->example('198001012005011001')
                ->fillRecordUsing(fn() => null), // Mencegah auto-save, kita handle manual di resolveRecord()

            ImportColumn::make('nama_guru')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->example('Ahmad Kurniawan, S.Pd.')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:255'])
                ->example('ahmad@sekolah.com')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('no_hp')
                ->rules(['nullable', 'string', 'max:20'])
                ->example('081234567890')
                ->fillRecordUsing(fn() => null),

            // --- BIODATA PRIBADI ---
            ImportColumn::make('jenis_kelamin')
                ->requiredMapping()
                ->rules(['required', 'in:L,P,Laki-laki,Perempuan,Laki-Laki'])
                ->example('L')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('tempat_lahir')
                ->rules(['nullable', 'string'])
                ->example('Padang')
                ->fillRecordUsing(fn() => null),

            // Tanggal lahir diset sebagai string agar Admin bebas mengetik format Indonesia
            ImportColumn::make('tanggal_lahir')
                ->rules(['nullable', 'string'])
                ->example('17-08-1980')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('alamat')
                ->rules(['nullable', 'string'])
                ->example('Jalan Sudirman No 10')
                ->fillRecordUsing(fn() => null),

            // --- RIWAYAT PENDIDIKAN ---
            ImportColumn::make('gelar_pendidikan')
                ->rules(['nullable', 'string'])
                ->example('S1')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('jurusan')
                ->rules(['nullable', 'string'])
                ->example('Pendidikan Biologi')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('asal_kampus')
                ->rules(['nullable', 'string'])
                ->example('Universitas Negeri Padang')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('tahun_lulus')
                ->rules(['nullable', 'numeric', 'digits:4'])
                ->example('2005')
                ->fillRecordUsing(fn() => null),

            // --- DATA KEPEGAWAIAN ---
            ImportColumn::make('status_pegawai')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->example('PNS')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('jabatan')
                ->rules(['nullable', 'string'])
                ->example('Guru Mapel')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('golongan')
                ->rules(['nullable', 'string'])
                ->example('III/b')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('pangkat')
                ->rules(['nullable', 'string'])
                ->example('Penata Muda Tingkat I')
                ->fillRecordUsing(fn() => null),

            // Mulai dinas diset sebagai string agar Admin bebas mengetik format Indonesia
            ImportColumn::make('mulai_dinas')
                ->rules(['nullable', 'string'])
                ->example('01-01-2006')
                ->fillRecordUsing(fn() => null),
        ];
    }

    /**
     * Memproses setiap baris dari file CSV secara individual.
     */
    public function resolveRecord(): ?Teacher
    {
        $data = $this->data;

        // 1. AMBIL DATA KUNCI (NIP, EMAIL, NAMA)
        $nip = trim($data['nip'] ?? '');
        $email = trim(strtolower($data['email'] ?? ''));
        $nama = trim($data['nama_guru'] ?? '');

        // 2. VALIDASI SUPER KETAT: Harus ada NIP atau Email!
        if (empty($nip) && empty($email)) {
            throw new RowImportFailedException("Gagal: Guru tanpa NIP (Honorer) WAJIB diisikan alamat Email-nya untuk pembuatan akun Login.");
        }

        // 3. TENTUKAN USERNAME & PASSWORD LOGIC
        // Jika ada NIP -> Username & Pass pakai NIP
        // Jika NIP kosong -> Username pakai Email, Pass pakai 'guru123'
        $username = !empty($nip) ? $nip : $email;
        $password = !empty($nip) ? $nip : 'guru123';

        // 4. BUAT ATAU UPDATE AKUN USER (LOGIN)
        $user = User::firstOrCreate(
            ['username' => $username],
            [
                'name' => $nama,
                'email' => !empty($email) ? $email : null,
                'password' => Hash::make($password),
                'role' => 'teacher',
                'is_active' => true,
            ]
        );

        // Pastikan role 'teacher' dari Spatie diberikan ke akun tersebut
        if (method_exists($user, 'assignRole') && !$user->hasRole('teacher')) {
            $roleGuru = Role::firstOrCreate(['name' => 'teacher']);
            $user->assignRole($roleGuru);
        }

        // --- 5. MESIN PENERJEMAH TANGGAL (INDONESIA KE MYSQL) ---
        // Mengubah format (DD-MM-YYYY atau DD/MM/YYYY) menjadi (YYYY-MM-DD)
        $parseIndonesianDate = function ($dateString) {
            if (empty(trim($dateString)))
                return null;
            try {
                // Ubah garis miring (/) menjadi strip (-) agar seragam
                $cleanDate = str_replace('/', '-', trim($dateString));
                // Terjemahkan ke format Database MySQL
                return Carbon::createFromFormat('d-m-Y', $cleanDate)->format('Y-m-d');
            } catch (Exception $e) {
                // Jika Admin mengetik format ngawur (misal: "17 Agustus"), beri pesan error spesifik
                throw new RowImportFailedException("Gagal: Format tanggal '{$dateString}' tidak dikenali. Gunakan format Hari-Bulan-Tahun (Contoh: 31-12-1990).");
            }
        };

        // Eksekusi penerjemahan tanggal
        $tglLahirFix = $parseIndonesianDate($data['tanggal_lahir'] ?? null);
        $mulaiDinasFix = $parseIndonesianDate($data['mulai_dinas'] ?? null);
        // --------------------------------------------------------

        // 6. TENTUKAN JENIS KELAMIN
        $jkInput = strtoupper(trim($data['jenis_kelamin'] ?? ''));
        $gender = ($jkInput === 'P' || $jkInput === 'PEREMPUAN') ? 'P' : 'L';

        // 7. BUAT ATAU UPDATE PROFIL GURU DI DATABASE
        // Kita jadikan 'nip' sebagai patokan update. Jika NIP kosong (Honorer), jadikan 'email' patokannya.
        $searchCriteria = !empty($nip) ? ['nip' => $nip] : ['email' => $email];

        $teacher = Teacher::updateOrCreate(
            $searchCriteria,
            [
                'user_id' => $user->id,
                'nip' => !empty($nip) ? $nip : null,
                'name' => $nama,
                'email' => !empty($email) ? $email : null,
                'phone' => trim($data['no_hp'] ?? null),
                'gender' => $gender,
                'place_of_birth' => trim($data['tempat_lahir'] ?? null),
                'date_of_birth' => $tglLahirFix,            // Menggunakan tanggal yang sudah diterjemahkan
                'address' => trim($data['alamat'] ?? null),
                'degree' => trim($data['gelar_pendidikan'] ?? null),
                'major' => trim($data['jurusan'] ?? null),
                'university' => trim($data['asal_kampus'] ?? null),
                'graduation_year' => trim($data['tahun_lulus'] ?? null),
                'employment_status' => trim($data['status_pegawai'] ?? null),
                'position' => trim($data['jabatan'] ?? null),
                'grade' => trim($data['golongan'] ?? null),
                'rank' => trim($data['pangkat'] ?? null),
                'assignment_date' => $mulaiDinasFix,        // Menggunakan tanggal yang sudah diterjemahkan
                'is_active' => true,
            ]
        );

        return $teacher;
    }

    /**
     * Pesan notifikasi yang muncul di pojok kanan atas setelah proses import selesai.
     */
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Proses import data Guru telah selesai. ' . number_format($import->successful_rows) . ' profil guru beserta akun login-nya berhasil dibuat.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' Namun, ada ' . number_format($failedRowsCount) . ' baris yang gagal di-import. Silakan unduh log error untuk mengeceknya.';
        }

        return $body;
    }
}