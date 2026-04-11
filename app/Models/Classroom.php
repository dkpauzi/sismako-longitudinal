<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class Classroom
 * * Model untuk merepresentasikan Kelas (Rombel).
 * * @property int $id
 * @property string $name (Contoh: 7.1, 8.2)
 * @property int $grade_level (7, 8, 9)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * * @property-read \Illuminate\Database\Eloquent\Collection|ClassHomeroom[] $classHomerooms
 * @property-read \Illuminate\Database\Eloquent\Collection|Enrollment[] $enrollments
 * @property-read \Illuminate\Database\Eloquent\Collection|Student[] $students
 * * @package App\Models
 */
class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'grade_level',
    ];

    /**
     * Casting data agar tipe datanya konsisten.
     */
    protected $casts = [
        'grade_level' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS (HUBUNGAN ANTAR TABEL)
    |--------------------------------------------------------------------------
    */

    /**
     * Relasi ke Riwayat Wali Kelas (Pivot Table).
     * Menghubungkan Kelas dengan Guru.
     */
    public function classHomerooms(): HasMany
    {
        return $this->hasMany(ClassHomeroom::class);
    }

    /**
     * Relasi ke Tabel Pendaftaran (Jembatan Siswa & Kelas).
     * Data siswa yang terdaftar di kelas ini (History + Aktif).
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Relasi Pintas (Shortcut) untuk mengambil Data Siswa secara langsung.
     * Mengambil siswa lewat tabel 'enrollments'.
     * Cara pakai: $classroom->students
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'enrollments')
            ->withPivot(['status', 'academic_period_id'])
            ->withTimestamps();
    }

    /**
     * Relasi ke Jadwal Pelajaran.
     */
    public function subjectSchedules(): HasMany
    {
        return $this->hasMany(SubjectSchedule::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS (FUNGSI BANTUAN)
    |--------------------------------------------------------------------------
    */

    /**
     * Helper untuk mendapatkan Nama Wali Kelas yang SEDANG MENJABAT saat ini.
     * Cara pakai: $classroom->current_homeroom_teacher?->name
     */
    public function getCurrentHomeroomTeacherAttribute()
    {
        return $this->classHomerooms()
            ->where('is_current', true)
            ->with('teacher') // Eager load
            ->first()
                ?->teacher; // Ambil object Teacher-nya
    }

    /**
     * Helper untuk menghitung jumlah siswa AKTIF di kelas ini pada periode aktif.
     */
    public function getActiveStudentsCountAttribute(): int
    {
        return $this->enrollments()
            ->where('status', 'active')
            ->whereHas('academicPeriod', function ($q) {
                $q->where('is_active', true);
            })
            ->count();
    }
}