<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model GradeRange — Rentang nilai internal (A-E) per SK Mengajar.
 *
 * Setiap TeachingAssignment memiliki 5 baris GradeRange (A, B, C, D, E).
 * Grade ini SEPENUHNYA INTERNAL — tidak boleh ditampilkan ke siswa/wali.
 * Digunakan sebagai parameter trigger untuk variasi teks deskripsi rapor.
 *
 * @property int    $id
 * @property int    $teaching_assignment_id
 * @property string $letter                  A|B|C|D|E
 * @property float  $min_score               Batas bawah (inklusif)
 * @property float  $max_score               Batas atas (inklusif, untuk display)
 */
class GradeRange extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_assignment_id',
        'letter',
        'min_score',
        'max_score',
    ];

    protected $casts = [
        'min_score' => 'decimal:2',
        'max_score' => 'decimal:2',
    ];

    // ── RELASI ────────────────────────────────────────────────────

    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }
}
