<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\DescriptionGeneratorService;

class FinalGrade extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'final_score' => 'decimal:2',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function teachingAssignment()
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    /**
     * Generate dan simpan narrative_description untuk satu siswa.
     * Bisa dipanggil dari Filament Action, Artisan Command, atau Observer.
     */
    public static function generateNarrative(
        TeachingAssignment $assignment,
        int $studentId,
        string $semester
    ): self {
        $service = new DescriptionGeneratorService();
        $narrative = $service->generate($assignment, $studentId);

        return static::updateOrCreate(
            [
                'student_id' => $studentId,
                'teaching_assignment_id' => $assignment->id,
                'semester' => $semester,
            ],
            [
                'narrative_description' => $narrative,
            ]
        );
    }
}