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
        'type',
        'description',
    ];

    /**
     * Casting tipe data agar Laravel otomatis mengubah nilai 0/1 dari database
     * menjadi true/false di dalam aplikasi.
     */
    protected $casts = [
        'type' => 'string',
    ];

    // ── Helper Methods ───────────────────────────────────────────────────────

    /**
     * Apakah mapel ini otomatis masuk ke semua siswa?
     * mandatory & kokurikuler = ya
     * elective & extracurricular = tidak (perlu enrollment manual)
     */
    public function isAutoEnroll(): bool
    {
        return in_array($this->type, ['mandatory', 'kokurikuler']);
    }

    public function isKokurikuler(): bool
    {
        return $this->type === 'kokurikuler';
    }

    public function isElective(): bool
    {
        return $this->type === 'elective';
    }

    // ── Accessors untuk backward compatibility ───────────────────────────────
    // Beberapa bagian kode lama masih pakai is_kokurikuler
    // Accessor ini memastikan tidak ada yang broken

    public function getIsKokurikulerAttribute(): bool
    {
        return $this->type === 'kokurikuler';
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}