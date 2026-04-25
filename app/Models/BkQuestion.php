<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BkQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'questionnaire_id',
        'question_text',
        'question_type',
        'order',
    ];

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(BkQuestionnaire::class, 'questionnaire_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(BkQuestionOption::class, 'question_id')->orderBy('order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(BkAnswer::class, 'question_id');
    }
}
