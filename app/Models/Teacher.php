<?php

namespace App\Models;

use App\Models\Concerns\HasHashidsRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    use HasFactory, HasHashidsRouteKey;

    /**
     * Daftar kolom yang boleh diisi (Mass Assignment).
     * Sudah disesuaikan dengan Data Kepegawaian (DUK) & Dapodik.
     */
    protected $fillable = [
        'user_id',          // Relasi ke User Login

        // --- 1. IDENTITAS UTAMA ---
        'nip',              // Nomor Induk Pegawai
        'name',             // Nama Lengkap (termasuk gelar)
        'gender',           // L/P
        'place_of_birth',   // Tempat Lahir
        'date_of_birth',    // Tanggal Lahir

        // --- 2. KONTAK & ALAMAT ---
        'email',
        'phone',
        'address',

        // --- 3. DATA PENDIDIKAN ---
        'degree',           // Jenjang (S1, S2, D3)
        'major',            // Jurusan (Pend. Biologi, dll)
        'university',       // Asal Kampus
        'graduation_year',  // Tahun Lulus

        // --- 4. DATA KEPEGAWAIAN (DUK) ---
        'employment_status', // Status (PNS, PPPK, Honorer)
        'position',          // Jabatan (Kepala Sekolah, Guru Mapel)
        'grade',             // Golongan (IV/a, IX, dll)
        'rank',              // Pangkat (Pembina, Ahli Pertama)
        'assignment_date',   // TMT (Terhitung Mulai Tanggal) / Mulai Dinas

        // --- 5. STATUS SISTEM ---
        'is_active',         // Status aktif di sekolah
    ];

    /**
     * Konversi tipe data otomatis.
     */
    protected $casts = [
        'date_of_birth' => 'date',     // Jadi object Carbon (bisa format tanggal)
        'assignment_date' => 'date',   // Jadi object Carbon
        'is_active' => 'boolean',      // Jadi true/false
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Relasi ke Akun Login.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Riwayat Menjadi Wali Kelas.
     */
    public function classHomerooms(): HasMany
    {
        return $this->hasMany(ClassHomeroom::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS (Fungsi Bantuan)
    |--------------------------------------------------------------------------
    */

    /**
     * Helper: Mendapatkan Kelas yang SEDANG dipegang saat ini.
     * Cara pakai: $teacher->current_classroom?->name
     */
    public function getCurrentClassroomAttribute()
    {
        return $this->classHomerooms()
            ->where('is_current', true) // Cari yang statusnya aktif menjabat
            ->whereHas('academicPeriod', function ($q) {
                $q->where('is_active', true); // Pastikan di tahun ajaran aktif
            })
            ->with('classroom')
            ->first()
                ?->classroom;
    }

}