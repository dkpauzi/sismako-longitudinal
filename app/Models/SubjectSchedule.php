<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_assignment_id',
        'day',
        'start_time',
        'end_time',
        'room',
        'note',
    ];

    // Relasi ke Induk (Penugasan)
    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }
}