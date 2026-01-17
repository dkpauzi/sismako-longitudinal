<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use HasFactory;
    //
    protected $fillable = [
        'name',
        'grade_level',
        'homeroom_teacher_id',
    ];
}
