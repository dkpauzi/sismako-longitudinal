<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BkStudentResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'questionnaire_id',
        'student_id',
        'academic_period_id',
        'status',
        'submitted_at',

        // Evaluasi Guru BK
        'score',
        'score_distribution',
        'feedback',
        'recommendation',
        'evaluated_at',

        // AI/Expert System placeholder
        'ai_suggested_score',
        'ai_suggested_feedback',
        'ai_suggested_recommendation',
        'is_ai_assisted',
    ];

    protected $casts = [
        'submitted_at'   => 'datetime',
        'evaluated_at'   => 'datetime',
        'score'          => 'decimal:2',
        'score_distribution' => 'array',
        'ai_suggested_score' => 'decimal:2',
        'is_ai_assisted' => 'boolean',
        'status'         => 'string',
    ];

    // ── RELASI ────────────────────────────────────────────────────

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(BkQuestionnaire::class, 'questionnaire_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(BkAnswer::class, 'response_id');
    }

    // ── SCOPES ────────────────────────────────────────────────────

    /**
     * Scope: hanya respons yang sudah dievaluasi oleh Guru BK.
     */
    public function scopeEvaluated(Builder $query): Builder
    {
        return $query->whereNotNull('evaluated_at');
    }

    /**
     * Scope: hanya respons yang belum dievaluasi.
     */
    public function scopeNotEvaluated(Builder $query): Builder
    {
        return $query->whereNull('evaluated_at');
    }
}
