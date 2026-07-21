<?php

namespace App\Models;

use App\Models\Concerns\HasHashidsRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Student extends Model
{
    use HasFactory, HasHashidsRouteKey;

    /**
     * Kolom yang diizinkan untuk diisi secara massal (Mass Assignment).
     * Disusun berdasarkan kebutuhan pendataan Dapodik dan form registrasi.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',          // Foreign Key: ID Akun Login Siswa di tabel users
        'guardian_user_id', // Foreign Key: ID Akun Login Wali Siswa di tabel users

        // --- IDENTITAS KEPENDUDUKAN & SEKOLAH ---
        'nisn',             // Nomor Induk Siswa Nasional (Unik, 10 digit angka)
        'nipd',             // Nomor Induk Peserta Didik / NIS Lokal
        'nik',              // Nomor Induk Kependudukan (Sesuai KK, 16 digit angka)

        // --- BIODATA PRIBADI ---
        'name',             // Nama Lengkap Siswa
        'gender',           // Jenis Kelamin: 'L' = Laki-laki, 'P' = Perempuan
        'place_of_birth',   // Tempat Lahir
        'date_of_birth',    // Tanggal Lahir
        'religion',         // Agama (Islam, Kristen, dll)
        'address',          // Alamat Domisili Siswa

        // --- DATA ORANG TUA ---
        'father_name',      // Nama Ayah Kandung
        'mother_name',      // Nama Ibu Kandung

        // --- STATUS SISTEM ---
        'status',           // Status Kesiswaan: active, graduated, moved, dropped_out, deceased
    ];

    /**
     * Konversi tipe data otomatis (Casting).
     * Berguna agar tanggal lahir langsung menjadi objek Carbon dan bisa diformat dengan mudah.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date', // Mengubah string database YYYY-MM-DD menjadi objek Carbon
        'status' => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS (HUBUNGAN ANTAR TABEL)
    |--------------------------------------------------------------------------
    */

    /**
     * Relasi ke tabel `users`.
     * Satu siswa (Student) ditautkan ke maksimal satu akun Login (User).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke akun login Wali Siswa (User).
     * Satu siswa bisa punya 1 akun wali. Satu akun wali bisa terhubung ke banyak siswa (saudara kandung).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function guardianUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    /**
     * Relasi ke tabel `enrollments` (Riwayat Pendaftaran/Kenaikan Kelas).
     * Satu siswa bisa memiliki banyak riwayat kelas (Contoh: Kelas 7, 8, dan 9).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Relasi Pintas (Shortcut) ke tabel `classrooms`.
     * Mengambil daftar SEMUA kelas yang PERNAH diduduki siswa ini sepanjang masa sekolahnya.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'enrollments')
            ->withPivot(['status', 'academic_period_id'])
            ->withTimestamps();
    }

    /**
     * 🌟 RELASI KELAS SAAT INI (Tahun Ajaran Aktif) 🌟
     * Menggunakan HasOneThrough agar Filament bisa melakukan Eager Loading (Mencegah N+1 query),
     * serta memungkinkan pencarian (Searching) dan pengurutan (Sorting) di tampilan tabel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function currentClassroom(): HasOneThrough
    {
        return $this->hasOneThrough(
            Classroom::class,           // Model Tujuan Akhir (Tabel classrooms)
            Enrollment::class,          // Model Perantara (Tabel enrollments)
            'student_id',               // Foreign key di tabel perantara (enrollments.student_id)
            'id',                       // Foreign key di tabel tujuan (classrooms.id)
            'id',                       // Local key di tabel awal (students.id)
            'classroom_id'              // Local key di tabel perantara (enrollments.classroom_id)
        )
            // Melakukan JOIN ke tabel tahun ajaran untuk memfilter HANYA tahun ajaran yang sedang aktif
            ->join('academic_periods', 'enrollments.academic_period_id', '=', 'academic_periods.id')
            ->where('academic_periods.is_active', true)
            ->select('classrooms.*');
    }

    /**
     * Relasi ke respons kuesioner BK siswa.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bkResponses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BkStudentResponse::class, 'student_id');
    }

    /**
     * Relasi ke rekam bimbingan konseling siswa.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function counselingRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BkCounselingRecord::class, 'student_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES (FUNGSI FILTER QUERY BANTUAN)
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Filter query untuk hanya mengambil siswa yang berstatus 'active' (Siswa Aktif).
     * Cara Penggunaan di Controller/Filament: Student::active()->get();
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Relasi ke rekam nilai akhir rapor (bisa dioverride manual).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function finalGrades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinalGrade::class);
    }
}