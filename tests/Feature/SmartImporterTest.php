<?php

namespace Tests\Feature;

use App\Filament\Imports\StudentImporter;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test Smart Importer: verifikasi bahwa proses import 1 baris Excel
 * berhasil membuat 2 User (siswa + wali) dan 1 profil Student
 * yang terhubung dengan benar.
 */
class SmartImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat Spatie roles yang dibutuhkan importer
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guardian', 'guard_name' => 'web']);
    }

    /**
     * Helper: simulasikan resolveRecord() dari StudentImporter
     * dengan data row tertentu.
     */
    private function runImporter(array $rowData): Student
    {
        $import = \Filament\Actions\Imports\Models\Import::create([
            'importer' => StudentImporter::class,
            'file_name' => 'test.csv',
            'file_path' => 'test.csv',
            'total_rows' => 1,
            'user_id' => User::factory()->create(['role' => 'admin', 'is_active' => true])->id,
        ]);

        // Filament Importer requires: Import $import, array $columnMap, array $options
        $columnMap = [];
        $options = [];

        $importer = new StudentImporter($import, $columnMap, $options);

        // Set data baris via reflection (Filament sets this internally)
        $reflection = new \ReflectionClass($importer);
        $prop = $reflection->getProperty('data');
        $prop->setAccessible(true);
        $prop->setValue($importer, $rowData);

        return $importer->resolveRecord();
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 1: Import creates correct number of records
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_import_creates_student_user_and_guardian_user(): void
    {
        $this->runImporter([
            'nisn' => '0098765432',
            'nama_siswa' => 'Ahmad Rizki',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Bapak Ahmad',
        ]);

        // Harus ada 3 user total: 1 admin (setUp), 1 siswa, 1 wali
        // Tapi kita cek spesifik
        $this->assertDatabaseHas('users', [
            'username' => '0098765432',
            'role' => 'student',
        ]);

        $this->assertDatabaseHas('users', [
            'username' => 'WALI_0098765432',
            'role' => 'guardian',
        ]);

        $this->assertDatabaseHas('students', [
            'nisn' => '0098765432',
            'name' => 'Ahmad Rizki',
            'gender' => 'L',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 2: Student profile is correctly linked to both users
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_student_profile_links_to_correct_users(): void
    {
        $student = $this->runImporter([
            'nisn' => '0098765432',
            'nama_siswa' => 'Ahmad Rizki',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Bapak Ahmad',
        ]);

        // student->user_id harus mengarah ke user dengan username = NISN
        $studentUser = User::where('username', '0098765432')->first();
        $guardianUser = User::where('username', 'WALI_0098765432')->first();

        $this->assertNotNull($studentUser);
        $this->assertNotNull($guardianUser);
        $this->assertEquals($studentUser->id, $student->user_id);
        $this->assertEquals($guardianUser->id, $student->guardian_user_id);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 3: Spatie roles are correctly assigned
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_spatie_roles_are_assigned_correctly(): void
    {
        $this->runImporter([
            'nisn' => '0098765432',
            'nama_siswa' => 'Ahmad Rizki',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Bapak Ahmad',
        ]);

        $studentUser = User::where('username', '0098765432')->first();
        $guardianUser = User::where('username', 'WALI_0098765432')->first();

        $this->assertTrue($studentUser->hasRole('student'));
        $this->assertTrue($guardianUser->hasRole('guardian'));
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 4: Guardian user gets correct name from nama_wali column
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_guardian_user_gets_nama_wali_as_name(): void
    {
        $this->runImporter([
            'nisn' => '0098765432',
            'nama_siswa' => 'Ahmad Rizki',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Suryadi',
        ]);

        $guardianUser = User::where('username', 'WALI_0098765432')->first();
        $this->assertEquals('Suryadi', $guardianUser->name);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 5: Upsert - re-import same NISN updates existing records
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_reimport_same_nisn_updates_instead_of_duplicating(): void
    {
        // Import pertama
        $this->runImporter([
            'nisn' => '0098765432',
            'nama_siswa' => 'Ahmad Rizki',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Suryadi',
        ]);

        // Import kedua — nama berubah
        $this->runImporter([
            'nisn' => '0098765432',
            'nama_siswa' => 'Ahmad Rizki Pratama',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Suryadi Pratama',
        ]);

        // Harus tetap 1 student, 1 student user, 1 guardian user
        $this->assertEquals(1, Student::where('nisn', '0098765432')->count());
        $this->assertEquals(1, User::where('username', '0098765432')->count());
        $this->assertEquals(1, User::where('username', 'WALI_0098765432')->count());

        // Nama harus terupdate
        $student = Student::where('nisn', '0098765432')->first();
        $this->assertEquals('Ahmad Rizki Pratama', $student->name);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 6: Gender normalization (Perempuan -> P)
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_gender_normalization(): void
    {
        $student = $this->runImporter([
            'nisn' => '0098765432',
            'nama_siswa' => 'Siti Aminah',
            'jenis_kelamin' => 'Perempuan',
            'nama_wali' => 'Ibu Aminah',
        ]);

        $this->assertEquals('P', $student->gender);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 7: Enrollment is created when class is provided
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_enrollment_created_when_class_provided(): void
    {
        $period = AcademicPeriod::create([
            'start_year' => 2025, 'end_year' => 2026, 'semester' => 'odd',
            'start_date' => '2025-07-14', 'end_date' => '2025-12-19', 'is_active' => true,
        ]);

        $classroom = Classroom::create(['name' => 'Kelas 7.1', 'grade_level' => 7]);

        $student = $this->runImporter([
            'nisn' => '0098765432',
            'nama_siswa' => 'Ahmad Rizki',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Suryadi',
            'kelas_sekarang' => 'Kelas 7.1',
        ]);

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_period_id' => $period->id,
            'status' => 'active',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // TEST 8: Password conventions
    // ═══════════════════════════════════════════════════════════════

    /** @test */
    public function test_password_conventions(): void
    {
        $this->runImporter([
            'nisn' => '0098765432',
            'nama_siswa' => 'Ahmad Rizki',
            'jenis_kelamin' => 'L',
            'nama_wali' => 'Suryadi',
        ]);

        $studentUser = User::where('username', '0098765432')->first();
        $guardianUser = User::where('username', 'WALI_0098765432')->first();

        // Password siswa = NISN
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('0098765432', $studentUser->password));

        // Password wali = WALI_{NISN}
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('WALI_0098765432', $guardianUser->password));
    }
}
