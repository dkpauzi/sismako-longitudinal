<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;
    //
    protected $fillable = [
        'nisn',
        'name',
        'gender',
        'date_of_birth',
        'address',
        'status',
    ];
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
