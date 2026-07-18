<?php

namespace Tests\Feature;

use App\Filament\Imports\StudentImporter;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test suite untuk StudentImporter.
 *
 * Menguji logika resolveRecord() secara langsung (unit-test level)
 * tanpa memerlukan file CSV atau Filament import pipeline.
 * Ini mensimulasikan apa yang Filament lakukan di balik layar.
 */
class StudentImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Spatie roles yang dibutuhkan oleh importer
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web']);
    }

    /**
     * Helper: Simulasikan resolveRecord() pada StudentImporter.
     *
     * Filament memanggil resolveRecord() untuk setiap baris CSV
     * setelah validasi kolom. Kita mensimulasikan hal yang sama
     * dengan mengisi $importer->data secara manual.
     */
    private function simulateImportRow(array $rowData): ?Student
    {
        $importer = new StudentImporter(
            // Filament Importer constructor memerlukan Import model, columnMap, dan options.
            new \Filament\Actions\Imports\Models\Import(),
            [], // columnMap
            []  // options
        );

        // Set data baris (biasanya diisi oleh Filament dari CSV)
        $reflection = new \ReflectionProperty($importer, 'data');
        $reflection->setAccessible(true);
        $reflection->setValue($importer, $rowData);

        return $importer->resolveRecord();
    }

    // ─────────────────────────────────────────────────────────────
    // TEST: Core Import Logic
    // ─────────────────────────────────────────────────────────────

    /**
     * Test: Valid row menghasilkan 2 User + 1 Student.
     */
    public function test_valid_row_creates_two_users_and_one_student(): void
    {
        $student = $this->simulateImportRow([
            'nisn' => '0012345678',
            'nama_siswa' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Suryadi',
            'nipd' => '25.396',
            'nik' => '1303041805130002',
            'tempat_lahir' => 'Sijunjung',
            'tanggal_lahir' => '2010-08-17',
            'agama' => 'Islam',
            'alamat' => 'Jorong Pematang',
            'nama_ayah' => 'Suryadi',
            'nama_ibu' => 'Siti',
            'kelas_sekarang' => '',
        ]);

        // Student harus ada
        $this->assertNotNull($student);
        $this->assertEquals('0012345678', $student->nisn);
        $this->assertEquals('Budi Santoso', $student->name);
        $this->assertEquals('L', $student->gender);
        $this->assertEquals('25.396', $student->nipd);
        $this->assertEquals('Sijunjung', $student->place_of_birth);
        $this->assertEquals('active', $student->status);

        // User Siswa harus ada
        $studentUser = User::where('username', '0012345678')->first();
        $this->assertNotNull($studentUser);
        $this->assertEquals('Budi Santoso', $studentUser->name);
        $this->assertEquals('student', $studentUser->role);
        $this->assertTrue($studentUser->is_active);
        $this->assertEquals($studentUser->id, $student->user_id);

        // User Wali harus ada
        $guardianUser = User::where('username', 'WALI_0012345678')->first();
        $this->assertNotNull($guardianUser);
        // Konvensi importer: nama wali digenerate dari nama siswa (nama_wali di file diabaikan)
        $this->assertEquals('Orang Tua/Wali dari Budi Santoso', $guardianUser->name);
        $this->assertEquals('guardian', $guardianUser->role);
        $this->assertTrue($guardianUser->is_active);
        $this->assertEquals($guardianUser->id, $student->guardian_user_id);

        // Total: 2 users + 1 student
        $this->assertEquals(2, User::count());
        $this->assertEquals(1, Student::count());
    }

    /**
     * Test: Spatie roles di-assign dengan benar.
     */
    public function test_spatie_roles_are_correctly_assigned(): void
    {
        $this->simulateImportRow([
            'nisn' => '9876543210',
            'nama_siswa' => 'Ani Wahyuni',
            'jenis_kelamin' => 'P',
            'nama_wali' => 'Ahmad',
            'kelas_sekarang' => '',
        ]);

        $studentUser = User::where('username', '9876543210')->first();
        $guardianUser = User::where('username', 'WALI_9876543210')->first();

        $this->assertTrue($studentUser->hasRole('student'));
        $this->assertTrue($guardianUser->hasRole('guardian'));
    }

    /**
     * Test: Import ulang (duplikat NISN) → update, bukan duplikasi.
     */
    public function test_duplicate_nisn_updates_existing_records(): void
    {
        // Import pertama
        $this->simulateImportRow([
            'nisn' => '1111111111',
            'nama_siswa' => 'Cici Lama',
            'jenis_kelamin' => 'P',
            'nama_wali' => 'Wali Lama',
            'kelas_sekarang' => '',
        ]);

        $this->assertEquals(1, Student::count());
        $this->assertEquals(2, User::count());

        // Import kedua dengan NISN yang sama tapi nama berubah
        $student = $this->simulateImportRow([
            'nisn' => '1111111111',
            'nama_siswa' => 'Cici Baru',
            'jenis_kelamin' => 'P',
            'nama_wali' => 'Wali Baru',
            'kelas_sekarang' => '',
        ]);

        // Tidak boleh duplikasi
        $this->assertEquals(1, Student::count());
        $this->assertEquals(2, User::count());

        // Data ter-update
        $this->assertEquals('Cici Baru', $student->name);
        $guardianUser = User::where('username', 'WALI_1111111111')->first();
        // Konvensi importer: nama wali digenerate dari nama siswa (ikut terupdate saat re-import)
        $this->assertEquals('Orang Tua/Wali dari Cici Baru', $guardianUser->name);
    }

    /**
     * Test: Gender normalization (Perempuan → P, Laki-laki → L).
     */
    public function test_gender_normalization(): void
    {
        $student1 = $this->simulateImportRow([
            'nisn' => '2222222222',
            'nama_siswa' => 'Dina',
            'jenis_kelamin' => 'Perempuan',
            'nama_wali' => 'Wali',
            'kelas_sekarang' => '',
        ]);
        $this->assertEquals('P', $student1->gender);

        $student2 = $this->simulateImportRow([
            'nisn' => '3333333333',
            'nama_siswa' => 'Eko',
            'jenis_kelamin' => 'Laki-laki',
            'nama_wali' => 'Wali',
            'kelas_sekarang' => '',
        ]);
        $this->assertEquals('L', $student2->gender);
    }

    // ─────────────────────────────────────────────────────────────
    // TEST: Enrollment Logic
    // ─────────────────────────────────────────────────────────────

    /**
     * Test: Enrollment otomatis jika kolom kelas_sekarang diisi.
     */
    public function test_enrollment_created_when_classroom_specified(): void
    {
        $period = AcademicPeriod::create([
            'start_year' => 2026, 'end_year' => 2027, 'semester' => 'odd',
            'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);
        $classroom = Classroom::create(['name' => 'Kelas 7.1', 'grade_level' => 7]);

        $student = $this->simulateImportRow([
            'nisn' => '4444444444',
            'nama_siswa' => 'Fani',
            'jenis_kelamin' => 'P',
            'nama_wali' => 'Wali Fani',
            'kelas_sekarang' => 'Kelas 7.1',
        ]);

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('academic_period_id', $period->id)
            ->first();

        $this->assertNotNull($enrollment);
        $this->assertEquals($classroom->id, $enrollment->classroom_id);
        $this->assertEquals('active', $enrollment->status);
    }

    /**
     * Test: Error ketika kelas tidak ditemukan → RowImportFailedException.
     */
    public function test_invalid_classroom_throws_row_import_failed_exception(): void
    {
        AcademicPeriod::create([
            'start_year' => 2026, 'end_year' => 2027, 'semester' => 'odd',
            'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);

        $this->expectException(\Filament\Actions\Imports\Exceptions\RowImportFailedException::class);
        $this->expectExceptionMessage("Kelas 'Kelas 999' tidak ditemukan");

        $this->simulateImportRow([
            'nisn' => '5555555555',
            'nama_siswa' => 'Gita',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Wali',
            'kelas_sekarang' => 'Kelas 999',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // TEST: Fault Tolerance
    // ─────────────────────────────────────────────────────────────

    /**
     * Test: Kegagalan baris N TIDAK mempengaruhi baris N-1 atau N+1.
     *
     * Simulasi: Import 3 baris, baris ke-2 gagal karena kelas tidak ada.
     * Baris 1 dan 3 harus tetap tersimpan.
     */
    public function test_failed_row_does_not_break_other_rows(): void
    {
        AcademicPeriod::create([
            'start_year' => 2026, 'end_year' => 2027, 'semester' => 'odd',
            'is_active' => true, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        ]);

        // Baris 1: Sukses (tanpa kelas)
        $student1 = $this->simulateImportRow([
            'nisn' => '6666666666',
            'nama_siswa' => 'Hani',
            'jenis_kelamin' => 'P',
            'nama_wali' => 'Wali Hani',
            'kelas_sekarang' => '',
        ]);
        $this->assertNotNull($student1);

        // Baris 2: Gagal (kelas tidak ada)
        $row2Failed = false;
        try {
            $this->simulateImportRow([
                'nisn' => '7777777777',
                'nama_siswa' => 'Irma',
                'jenis_kelamin' => 'P',
                'nama_wali' => 'Wali Irma',
                'kelas_sekarang' => 'Kelas Tidak Ada',
            ]);
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $e) {
            $row2Failed = true;
        }
        $this->assertTrue($row2Failed, 'Baris 2 harus gagal.');

        // Baris 3: Sukses (tanpa kelas)
        $student3 = $this->simulateImportRow([
            'nisn' => '8888888888',
            'nama_siswa' => 'Joko',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Wali Joko',
            'kelas_sekarang' => '',
        ]);
        $this->assertNotNull($student3);

        // Baris 1 dan 3 tersimpan, baris 2 tidak ada di students
        $this->assertEquals(2, Student::count());
        $this->assertNotNull(Student::where('nisn', '6666666666')->first());
        $this->assertNotNull(Student::where('nisn', '8888888888')->first());

        // Baris 2: user siswa mungkin sudah dibuat sebelum exception (tergantung urutan),
        // tapi transaction di-rollback karena RowImportFailedException.
        // Kita cek bahwa student record TIDAK ada.
        $this->assertNull(Student::where('nisn', '7777777777')->first());
    }

    /**
     * Test: Chunk size dikonfigurasi pada ImportAction (bukan lagi properti importer).
     *
     * Properti statis $chunkSize di importer adalah dead code — Filament tidak
     * membacanya. Ukuran chunk yang efektif diatur via ImportAction->chunkSize(50)
     * di halaman Data Siswa. Test ini memverifikasi konfigurasi yang benar itu.
     */
    public function test_chunk_size_is_configured_on_import_action(): void
    {
        $action = \App\Filament\Resources\StudentResource::table(
            app(\Filament\Tables\Table::class, ['livewire' => new \App\Filament\Resources\StudentResource\Pages\ListStudents()])
        )->getAction('import_siswa');

        $this->assertNotNull($action, 'Aksi import_siswa harus ada di tabel Data Siswa.');
        $this->assertEquals(50, $action->getChunkSize());
    }
}
