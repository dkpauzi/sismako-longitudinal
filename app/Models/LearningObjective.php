<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningObjective extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'academic_period_id',
        'grade_level',  // 7, 8, 9
        'phase',        // D
        'code',         // TP.7.1
        'content',      // Deskripsi Lengkap
        'attribute',    // Ringkasan untuk Rapor
    ];

    // Relasi ke Guru (Pemilik TP)
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    // Relasi ke Mapel
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    // Relasi ke Tahun Ajaran
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}