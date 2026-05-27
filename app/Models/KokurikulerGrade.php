<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model KokurikulerGrade
 * * Model ini berfungsi sebagai wadah untuk menampung nilai akhir 
 * berupa teks/deskripsi untuk mata pelajaran yang ditandai sebagai Kokurikuler (P5).
 * * @property int $id
 * @property int $teaching_assignment_id
 * @property int $student_id
 * @property int $academic_period_id
 * @property string|null $project_title
 * @property string|null $narrative_description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * * Relasi:
 * @property-read TeachingAssignment $teachingAssignment
 * @property-read Student $student
 * @property-read AcademicPeriod $academicPeriod
 */
class KokurikulerGrade extends Model
{
    use HasFactory;

    /**
     * Nama tabel di database (opsional, tapi baik untuk penegasan)
     */
    protected $table = 'kokurikuler_grades';

    /**
     * Kolom yang diizinkan untuk diisi secara massal (mass assignment).
     */
    protected $fillable = [
        'teaching_assignment_id',
        'student_id',
        'academic_period_id',
        'project_title',
        'narrative_description',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELASI (RELATIONSHIPS)
    |--------------------------------------------------------------------------
    */

    /**
     * Relasi ke SK Mengajar (Teaching Assignment).
     * Menghubungkan nilai ini dengan Guru, Mata Pelajaran (P5), dan Kelas tertentu.
     */
    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    /**
     * Relasi ke Siswa.
     * Menunjukkan nilai deskripsi ini adalah milik siswa yang mana.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Relasi ke AcademicPeriod.
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}