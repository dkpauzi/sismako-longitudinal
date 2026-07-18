<?php

namespace App\Filament\Imports;

use Carbon\Carbon;
use Exception;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class TeacherImporter extends Importer
{
    protected static ?string $model = Teacher::class;

    /**
     * Cache Spatie Role agar tidak query berulang per baris.
     */
    private ?Role $teacherRole = null;

    /**
     * Mendefinisikan struktur kolom yang akan dibaca dari file Excel (.xlsx).
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
                ->rules(['required'])
                ->example('L (Pilihan: L, P, Laki-laki, Perempuan)')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('tempat_lahir')
                ->rules(['nullable', 'string'])
                ->example('Padang')
                ->fillRecordUsing(fn() => null),

            ImportColumn::make('tanggal_lahir')
                ->rules(['nullable'])
                ->example('1980-08-17 (Format YYYY-MM-DD)')
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

            ImportColumn::make('mulai_dinas')
                ->rules(['nullable'])
                ->example('2006-01-01 (Format YYYY-MM-DD)')
                ->fillRecordUsing(fn() => null),
        ];
    }

    /**
     * Triple-Layer Date Sanitization.
     * Menangani: DateTimeInterface (OpenSpout), Excel Serial Number, dan String ISO 8601.
     */
    private function sanitizeDate(mixed $rawDate): ?string
    {
        if (empty($rawDate)) {
            return null;
        }

        try {
            // Scenario A: OpenSpout/Excel mengirim objek DateTime
            if ($rawDate instanceof \DateTimeInterface) {
                return $rawDate->format('Y-m-d');
            }

            // Scenario B: Excel Serial Date (angka seperti 29400, 44560, dsb.)
            if (is_numeric($rawDate)) {
                if ($rawDate > 25569) {
                    return Carbon::createFromTimestamp(($rawDate - 25569) * 86400)->format('Y-m-d');
                }
                return null; // Serial tidak valid
            }

            // Scenario C: String biasa — Carbon::parse menangani ISO 8601 (YYYY-MM-DD) secara native
            return Carbon::parse($rawDate)->format('Y-m-d');
        } catch (Exception $e) {
            // Scenario D: Format tidak dikenali, jangan crash — simpan null
            return null;
        }
    }

    /**
     * Memproses setiap baris dari file Excel secara individual.
     * Seluruh proses User + Teacher dibungkus dalam DB::transaction().
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

        // 4. TRIPLE-LAYER DATE SANITIZATION
        $tglLahirFix = $this->sanitizeDate($data['tanggal_lahir'] ?? null);
        $mulaiDinasFix = $this->sanitizeDate($data['mulai_dinas'] ?? null);

        // 5. TENTUKAN JENIS KELAMIN
        $jkInput = strtoupper(trim($data['jenis_kelamin'] ?? ''));
        $gender = ($jkInput === 'P' || $jkInput === 'PEREMPUAN') ? 'P' : 'L';

        // ══════════════════════════════════════════════════════════════
        // SELURUH PROSES USER + TEACHER DIBUNGKUS DALAM DB::transaction()
        // Jika Teacher gagal disimpan, User juga akan di-rollback.
        // ══════════════════════════════════════════════════════════════
        $teacher = DB::transaction(function () use ($data, $nip, $email, $nama, $username, $password, $gender, $tglLahirFix, $mulaiDinasFix) {

            // STEP A: BUAT ATAU UPDATE AKUN USER (LOGIN)
            // Konsisten dengan StudentImporter (Audit 2.3): pakai updateOrCreate agar
            // re-impor menyegarkan data profil (nama/email). Konvensi re-impor:
            // password diselaraskan ulang ke NIP/'guru123' — sama seperti importer siswa.
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $nama,
                    'email' => !empty($email) ? $email : null,
                    'password' => Hash::make($password),
                    // SECURITY HARDCODE: Role enum di tabel users SELALU 'teacher'.
                    // Jangan pernah membaca role dari Excel — mencegah privilege escalation.
                    'role' => 'teacher',
                    'is_active' => true,
                ]
            );

            // STEP B: Assign Spatie role 'teacher' secara KETAT
            // Tidak pernah assign admin/headmaster dari importer.
            if (!$user->hasRole('teacher')) {
                $this->teacherRole ??= Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
                $user->assignRole($this->teacherRole);
            }

            // STEP C: BUAT ATAU UPDATE PROFIL GURU DI DATABASE
            // Kita jadikan 'nip' sebagai patokan update. Jika NIP kosong (Honorer), jadikan 'email' patokannya.
            $searchCriteria = !empty($nip) ? ['nip' => $nip] : ['email' => $email];

            return Teacher::updateOrCreate(
                $searchCriteria,
                [
                    'user_id' => $user->id,
                    'nip' => !empty($nip) ? $nip : null,
                    'name' => $nama,
                    'email' => !empty($email) ? $email : null,
                    'phone' => trim($data['no_hp'] ?? null),
                    'gender' => $gender,
                    'place_of_birth' => trim($data['tempat_lahir'] ?? null),
                    'date_of_birth' => $tglLahirFix,
                    'address' => trim($data['alamat'] ?? null),
                    'degree' => trim($data['gelar_pendidikan'] ?? null),
                    'major' => trim($data['jurusan'] ?? null),
                    'university' => trim($data['asal_kampus'] ?? null),
                    'graduation_year' => trim($data['tahun_lulus'] ?? '') ?: null,
                    'employment_status' => trim($data['status_pegawai'] ?? null),
                    'position' => trim($data['jabatan'] ?? null),
                    'grade' => trim($data['golongan'] ?? null),
                    'rank' => trim($data['pangkat'] ?? null),
                    'assignment_date' => $mulaiDinasFix,
                    'is_active' => true,
                ]
            );
        });

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