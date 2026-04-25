<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkQuestionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'option_text',
        'option_code',
        'score_weight',
        'order',
    ];

    protected $casts = [
        'score_weight' => 'decimal:2',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(BkQuestion::class, 'question_id');
    }
}
