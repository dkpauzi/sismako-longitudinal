<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_assignment_id',
        'student_id',
        'date',
        'status',
        'note',
    ];

    // Relasi ke Induk (Penugasan)
    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    // Relasi ke Siswa
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
