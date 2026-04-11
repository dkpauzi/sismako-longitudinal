<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $is_kokurikuler
 * @property string|null $description
 */
class Subject extends Model
{
    use HasFactory;

    /**
     * Kolom yang diizinkan untuk diisi secara massal (mass assignment).
     */
    protected $fillable = [
        'name',
        'code',
        'is_kokurikuler', // Penanda khusus untuk mata pelajaran Kokurikuler / P5
        'description',
    ];

    /**
     * Casting tipe data agar Laravel otomatis mengubah nilai 0/1 dari database
     * menjadi true/false di dalam aplikasi.
     */
    protected $casts = [
        'is_kokurikuler' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELASI (RELATIONSHIPS)
    |--------------------------------------------------------------------------
    */

    /**
     * Relasi ke SK Mengajar (Teaching Assignments).
     * Satu Mata Pelajaran bisa diajarkan di banyak kelas/SK.
     */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}