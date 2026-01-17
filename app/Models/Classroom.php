<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use HasFactory;
    //
    protected $fillable = [
        'name',
        'grade_level',
        'homeroom_teacher_id',
    ];
    public function classHomerooms(): HasMany
    {
        return $this->hasMany(ClassHomeroom::class);
    }
    public function enrollments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
