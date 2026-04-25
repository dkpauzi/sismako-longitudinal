<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model Extracurricular (Master Data Ekstrakurikuler)
 *
 * Tabel: extracurriculars
 * Pivot: student_extracurriculars (dengan kolom score_grade, description)
 */
class Extracurricular extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'coach_name',
        'description',
    ];

    /**
     * Relasi ke siswa yang mengikuti ekskul ini.
     * Many-to-Many melalui tabel pivot student_extracurriculars.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_extracurriculars')
            ->withPivot(['academic_period_id', 'score_grade', 'description'])
            ->withTimestamps();
    }
}
