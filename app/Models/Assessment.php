<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_assignment_id', // Link ke Penugasan Guru
        'category',               // formatif, sumatif_lingkup_materi, sumatif_akhir_semester
        'technique',              // tes_tertulis, kinerja, dll
        'name',                   // Nama Ujian (Misal: UH 1)
        'date',                   // Tanggal
        'weight',
    ];

    // Relasi ke Induk (Penugasan)
    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    // Relasi ke TP (Many-to-Many)
    // INI KUNCINYA: Satu Assessment bisa terhubung ke BANYAK TP
    public function learningObjectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'assessment_learning_objective');
    }

    // Relasi ke Nilai Siswa (Akan kita gunakan di langkah selanjutnya)
    // Pastikan Model Grade nanti dibuat juga
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}