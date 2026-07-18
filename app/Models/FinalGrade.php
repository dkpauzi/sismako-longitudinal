<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FinalGrade extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'final_score' => 'decimal:2',
        'is_locked' => 'boolean',
        'is_manual_override' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function teachingAssignment()
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    /**
     * ══════════════════════════════════════════════════════════════
     * SATU-SATUNYA PINTU TULIS final_grades (Audit 3.6).
     * ══════════════════════════════════════════════════════════════
     * Semua penulisan snapshot rapor (skor, predikat, maupun narasi) —
     * baik dari Observer, bulk save, remedial, maupun generate narasi —
     * WAJIB lewat method ini agar penguncian nilai ditegakkan secara global.
     *
     * Aturan: jika FinalGrade yang ada sudah DIKUNCI (is_locked) atau
     * di-OVERRIDE MANUAL oleh admin (is_manual_override), snapshot dibatalkan
     * dan record TIDAK diubah — nilai rapor final tidak boleh tertimpa oleh
     * kalkulasi/generate otomatis.
     *
     * @param  array<string,mixed> $attributes Kolom yang akan diperbarui
     *                             (mis. final_score, grade_label, narrative_description).
     * @return self|null           Record final grade (yang ada atau baru),
     *                             null hanya jika terjadi kondisi tak terduga.
     */
    public static function snapshot(
        int $studentId,
        int $teachingAssignmentId,
        string $semester,
        array $attributes
    ): ?self {
        $keys = [
            'student_id' => $studentId,
            'teaching_assignment_id' => $teachingAssignmentId,
            'semester' => $semester,
        ];

        // ATOMIK (Audit RENDAH): read-then-write dibungkus transaksi + lockForUpdate
        // agar dua request beririsan (mis. "kunci" vs "rekalkulasi") tidak saling
        // menyalip dan menimpa nilai yang baru saja dikunci. lockForUpdate menjadi
        // no-op yang aman di SQLite (test) dan mengunci baris di MySQL (produksi).
        return DB::transaction(function () use ($keys, $attributes) {
            $existing = static::query()->where($keys)->lockForUpdate()->first();

            // GUARD PENGUNCIAN: nilai terkunci / override manual tidak boleh ditimpa.
            if ($existing && ($existing->is_locked || $existing->is_manual_override)) {
                return $existing;
            }

            return static::updateOrCreate($keys, $attributes);
        });
    }
}