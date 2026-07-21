<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashidsRouteKey;
use Illuminate\Database\Eloquent\Model;

class LessonJournal extends Model
{
    use HasFactory, HasHashidsRouteKey;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function teachingAssignment()
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}