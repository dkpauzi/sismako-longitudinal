<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model NarrativeTemplate — Template kalimat deskripsi rapor per grade (A-E).
 *
 * Template menggunakan placeholder [TP] yang akan diganti dengan nama
 * Tujuan Pembelajaran saat narasi di-generate.
 *
 * Hierarki fallback:
 *   1. Template guru (teaching_assignment_id terisi, is_default = false)
 *   2. Template admin default (teaching_assignment_id = null, is_default = true)
 *
 * @property int         $id
 * @property string      $grade_letter            A|B|C|D|E
 * @property string      $template_text           Template dengan placeholder [TP]
 * @property bool        $is_default              true = admin school-wide default
 * @property int|null    $teaching_assignment_id   null = admin, terisi = guru override
 */
class NarrativeTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'grade_letter',
        'template_text',
        'is_default',
        'teaching_assignment_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // ── RELASI ────────────────────────────────────────────────────

    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    // ── QUERY HELPERS ─────────────────────────────────────────────

    /**
     * Ambil template untuk grade tertentu dengan hierarki fallback.
     *
     * Prioritas:
     *   1. Template guru (jika ada untuk teaching_assignment_id ini)
     *   2. Template admin default (is_default = true)
     *
     * @param  string $gradeLetter           A|B|C|D|E
     * @param  int|null $teachingAssignmentId  ID SK Mengajar (null = ambil default saja)
     * @return string                        Template text (atau hardcoded fallback)
     */
    public static function getTemplate(string $gradeLetter, ?int $teachingAssignmentId = null): string
    {
        // 1. Cek template guru dulu
        if ($teachingAssignmentId) {
            $teacherTemplate = static::where('grade_letter', $gradeLetter)
                ->where('teaching_assignment_id', $teachingAssignmentId)
                ->where('is_default', false)
                ->first();

            if ($teacherTemplate) {
                return $teacherTemplate->template_text;
            }
        }

        // 2. Fallback ke admin default
        $adminDefault = static::where('grade_letter', $gradeLetter)
            ->where('is_default', true)
            ->first();

        if ($adminDefault) {
            return $adminDefault->template_text;
        }

        // 3. Fallback terakhir: hardcoded safety net
        return self::getHardcodedFallback($gradeLetter);
    }

    /**
     * Hardcoded fallback jika tidak ada template di DB sama sekali.
     * Ini hanya terjadi jika migration seeder gagal atau DB kosong.
     */
    private static function getHardcodedFallback(string $gradeLetter): string
    {
        return match ($gradeLetter) {
            'A' => 'Ananda menunjukkan penguasaan yang sangat baik dalam materi [TP].',
            'B' => 'Ananda sudah memahami dan mampu menerapkan materi [TP] dengan baik.',
            'C' => 'Ananda cukup memahami materi [TP], namun masih perlu pendalaman lebih lanjut.',
            'D' => 'Ananda perlu meningkatkan pemahaman pada materi [TP] agar mencapai ketuntasan.',
            'E' => 'Ananda memerlukan bimbingan intensif pada materi [TP] untuk mencapai kompetensi dasar.',
        };
    }
}
