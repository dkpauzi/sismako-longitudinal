<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'response_id',
        'question_id',
        'selected_option_id',
        'text_answer',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(BkStudentResponse::class, 'response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(BkQuestion::class, 'question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(BkQuestionOption::class, 'selected_option_id');
    }
}
