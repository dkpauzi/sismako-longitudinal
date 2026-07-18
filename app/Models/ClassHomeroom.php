<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class ClassHomeroom
 *
 * Model ini merepresentasikan penetapan "Wali Kelas".
 * Berfungsi sebagai tabel pivot (penghubung) antara Kelas, Guru, dan Tahun Ajaran.
 *
 * @property int $id
 * @property int $classroom_id
 * @property int $teacher_id
 * @property int $academic_period_id
 * @property bool $is_current
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Classroom $classroom
 * @property-read Teacher $teacher
 * @property-read AcademicPeriod $academicPeriod
 *
 * @method static Builder|ClassHomeroom active()
 * @package App\Models
 */
class ClassHomeroom extends Model
{
    use HasFactory;

    /**
     * Nama tabel di database.
     */
    protected $table = 'class_homerooms';

    /**
     * Atribut yang dapat diisi secara massal.
     */
    protected $fillable = [
        'classroom_id',
        'teacher_id',
        'academic_period_id',
        'is_current', // Penanda apakah guru ini sedang menjabat sekarang
    ];

    /**
     * Konversi tipe data otomatis.
     */
    protected $casts = [
        'is_current' => 'boolean',
    ];

    /**
     * --- LOGIKA OTOMATIS (MODEL EVENT) ---
     * Menjamin integritas data Wali Kelas.
     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // LOGIK: Single Active Homeroom
            // Jika data ini diset sebagai "Masih Menjabat" (Aktif)
            if ($model->is_current) {
                // Cari data wali kelas lain di KELAS YANG SAMA pada PERIODE YANG SAMA
                // Lalu matikan status aktifnya agar tidak ada 2 wali kelas aktif sekaligus.
                static::where('classroom_id', $model->classroom_id)
                    ->where('academic_period_id', $model->academic_period_id)
                    ->where('id', '!=', $model->id) // Jangan update diri sendiri
                    ->update(['is_current' => false]);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS (HUBUNGAN ANTAR TABEL)
    |--------------------------------------------------------------------------
    */

    /**
     * Relasi ke Tahun Ajaran (SK Wali Kelas berlaku tahun berapa?).
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /**
     * Relasi ke Kelas (Guru ini pegang kelas apa?).
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Relasi ke Guru (Siapa wali kelasnya?).
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Relasi ke seluruh pendaftaran siswa DI KELAS YANG SAMA.
     * Dikaitkan lewat classroom_id (bukan id) karena ClassHomeroom pada
     * dasarnya adalah pivot kelas↔guru↔periode.
     *
     * CATATAN: relasi ini BELUM difilter periode/status — penghitungan
     * "siswa aktif per periode" dilakukan via withCount berkorelasi di
     * RaporResource (menghindari N+1 count per baris tabel).
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'classroom_id', 'classroom_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES (SHORTCUT QUERY)
    |--------------------------------------------------------------------------
    */

    /**
     * Shortcut untuk mengambil data wali kelas yang sedang aktif menjabat.
     *
     * Cara pakai: $classroom->classHomerooms()->active()->first();
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}