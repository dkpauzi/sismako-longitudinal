<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',    // Link ke Ujiannya
        'student_id',       // Link ke Siswanya
        'score',            // Angka (0-100)
        'original_score',   // Nilai asli sebelum remedial
        'remedial_attempts', // Jumlah percobaan remedial
        'feedback',         // Catatan Guru (Penting di Kurikulum Merdeka)
    ];

    protected $casts = [
        'remedial_attempts' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}