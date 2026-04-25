<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkQuestionnaireTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'questionnaire_id',
        'classroom_id',
        'academic_period_id',
    ];

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(BkQuestionnaire::class, 'questionnaire_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}
