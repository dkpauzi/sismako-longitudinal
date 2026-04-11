<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'classroom_id',
        'academic_period_id',
        'status',
    ];

    // Relasi ke Siswa
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // Relasi ke Kelas
    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    // Relasi ke Tahun Ajaran
    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}