<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BkQuestionnaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'instructions',
        'counselor_id',
        'academic_period_id',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(BkQuestionnaireTarget::class, 'questionnaire_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(BkQuestion::class, 'questionnaire_id')->orderBy('order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(BkStudentResponse::class, 'questionnaire_id');
    }
}
