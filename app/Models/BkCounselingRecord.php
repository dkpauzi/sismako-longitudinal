<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BkCounselingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'counselor_id',
        'session_date',
        'session_type',
        'topic',
        'category',
        'description',
        'action_taken',
        'follow_up_date',
        'follow_up_note',
        'is_visible_to_student',
        'is_visible_to_guardian',
        'is_visible_to_homeroom',
        'is_visible_to_principal',
    ];

    protected $casts = [
        'session_date' => 'date',
        'follow_up_date' => 'date',
        'is_visible_to_student' => 'boolean',
        'is_visible_to_guardian' => 'boolean',
        'is_visible_to_homeroom' => 'boolean',
        'is_visible_to_principal' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(BkRecordAttachment::class, 'record_id');
    }
}
