<?php

namespace App\Models;

use App\Services\GradeRangeResolver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model TeachingAssignment (SK Mengajar)
 * * @property int $id
 * @property int $academic_period_id
 * @property int $teacher_id
 * @property int $subject_id
 * @property int $classroom_id
 * @property string $grading_formula
 * @property int|null $kktp
 * * Relasi:
 * @property AcademicPeriod $academicPeriod
 * @property Teacher $teacher
 * @property Subject $subject
 * @property Classroom $classroom
 */
class TeachingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_period_id',
        'teacher_id',
        'subject_id',
        'classroom_id',
        'grading_formula',
        'kktp',
        'subject_type',
        'booster_mode',
        'booster_value',
    ];

    protected $casts = [
        'subject_type' => 'string',
        'kktp' => 'integer',
        'booster_value' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELASI PARENT (Belongs To)
    |--------------------------------------------------------------------------
    */

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /*
    |--------------------------------------------------------------------------
    | RELASI CHILDREN (Has Many)
    |--------------------------------------------------------------------------
    */

    public function schedules(): HasMany
    {
        return $this->hasMany(SubjectSchedule::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Helper: Apakah periode akademik dari SK Mengajar ini masih aktif?
     * Null-safe — mengembalikan false jika relasi tidak ter-load.
     */
    public function isPeriodActive(): bool
    {
        return (bool) $this->academicPeriod?->is_active;
    }

    // Tambahkan di dalam class TeachingAssignment
    public function lessonJournals()
    {
        return $this->hasMany(LessonJournal::class);
    }

    public function attendanceSummaries()
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function finalGrades()
    {
        return $this->hasMany(FinalGrade::class);
    }
    public function studentSubjectEnrollments(): HasMany
    {
        return $this->hasMany(StudentSubjectEnrollment::class);
    }

    /**
     * Tentukan type efektif dari teaching assignment ini.
     *
     * Urutan prioritas:
     * 1. subject_type di teaching_assignments (override per kelas)
     * 2. type di subjects (default global)
     */
    public function getEffectiveType(): string
    {
        return $this->subject_type ?? $this->subject->type ?? 'mandatory';
    }

    /**
     * Apakah mapel ini otomatis masuk ke semua siswa di kelas?
     */
    public function isAutoEnroll(): bool
    {
        return in_array($this->getEffectiveType(), ['mandatory', 'kokurikuler']);
    }

    public function isKokurikuler(): bool
    {
        return $this->getEffectiveType() === 'kokurikuler';
    }

    public function isExtracurricular(): bool
    {
        return $this->getEffectiveType() === 'extracurricular';
    }


    /*
    |--------------------------------------------------------------------------
    | KALKULATOR NILAI AKHIR (CORE LOGIC)
    |--------------------------------------------------------------------------
    */

    /**
     * Menghitung Nilai Akhir Rapor untuk satu orang siswa di kelas ini.
     * Mengakomodasi 3 Opsi Sumatif dan 1 Opsi Booster Formatif (Poin).
     */
    public function calculateFinalGrade(int $studentId): float
    {
        // 1. Ambil semua asesmen milik kelas ini beserta nilai anak tersebut
        $assessments = $this->assessments()->with([
            'grades' => function ($query) use ($studentId) {
                $query->where('student_id', $studentId);
            }
        ])->get();

        if ($assessments->isEmpty()) {
            return 0; // Belum ada ujian sama sekali
        }

        // --- TAHAP 1: HITUNG NILAI DASAR SUMATIF ---
        $summativeAssessments = $assessments->filter(function ($assessment) {
            return in_array($assessment->category, ['sumatif_lingkup_materi', 'sumatif_akhir_semester']);
        });

        $summativeScore = 0;

        if ($summativeAssessments->isNotEmpty()) {
            if ($this->grading_formula === 'average') {
                // Opsi 1: Rata-rata murni
                $totalScore = 0.0;
                $count = 0;
                foreach ($summativeAssessments as $assessment) {
                    $grade = $assessment->grades->first();
                    if ($grade && $grade->score !== null) {
                        $totalScore += (float) $grade->score;
                        $count++;
                    }
                }
                $summativeScore = $count > 0 ? ($totalScore / $count) : 0.0;

            } elseif ($this->grading_formula === 'weighting') {
                // Opsi 2: Pembobotan Persentase
                // ✅ PERBAIKAN: Skip nilai null agar data kosong tidak dianggap 0
                //    dan distorsi rata-rata. Jika total bobot < 100 (karena ada yg belum diisi),
                //    normalisasi agar proporsi tetap akurat.
                $totalWeight = 0;
                foreach ($summativeAssessments as $assessment) {
                    $grade = $assessment->grades->first();
                    if (!$grade || $grade->score === null) continue; // Skip data kosong

                    $score  = (float) $grade->score;
                    $weight = (float) $assessment->weight;
                    $summativeScore += ($score * ($weight / 100));
                    $totalWeight    += $weight;
                }
                // Normalisasi jika bobot terkumpul kurang dari 100 karena data belum lengkap
                if ($totalWeight > 0 && $totalWeight < 100) {
                    $summativeScore = ($summativeScore / $totalWeight) * 100;
                }

            } elseif ($this->grading_formula === 'percentage') {
                // Opsi 3: Persentase Ketuntasan KKTP
                $kktp = (int) $this->kktp_or_default;
                $passedCount = 0;
                $totalCount = $summativeAssessments->count();

                foreach ($summativeAssessments as $assessment) {
                    $grade = $assessment->grades->first();
                    if ($grade && $grade->score >= $kktp) {
                        $passedCount++;
                    }
                }
                $summativeScore = $totalCount > 0 ? (($passedCount / $totalCount) * 100) : 0.0;
            }
        }

        // --- TAHAP 1b: BOOSTER FORMATIF ---
        // Ambil skor semua asesmen formatif siswa ini, lalu hitung kontribusinya
        // sesuai mode booster (none/weight/point) yang diatur pada SK Mengajar.
        $formativeScores = $assessments
            ->filter(fn ($a) => str_starts_with($a->category, 'formatif'))
            ->map(fn ($a) => $a->grades->first()?->score);

        $booster = $this->boosterContribution($formativeScores);

        // --- TAHAP 2: GABUNG + CAP 100 + PEMBULATAN ---
        $finalGrade = min(100, $summativeScore + $booster);

        return (float) round($finalGrade);
    }

    /**
     * Hitung kontribusi booster dari sekumpulan nilai formatif.
     *
     * Aturan (berdasarkan booster_mode pada SK Mengajar):
     * - none   : 0 (booster nonaktif)
     * - weight : akumulasi (nilai_formatif x booster_value%) tiap formatif terisi
     * - point  : (jumlah formatif terisi) x booster_value
     *
     * "Terisi" = skor tidak null dan > 0. Pembatasan maksimal 100 dilakukan
     * di pemanggil (calculateFinalGrade / calculateScorePerTp).
     *
     * @param \Illuminate\Support\Collection $formativeScores Koleksi skor formatif (boleh berisi null).
     */
    public function boosterContribution(\Illuminate\Support\Collection $formativeScores): float
    {
        $scores = $formativeScores->filter(fn ($s) => $s !== null && (float) $s > 0);

        return match ($this->booster_mode) {
            'weight' => (float) $scores->sum(fn ($s) => (float) $s * ((float) $this->booster_value / 100)),
            'point'  => (float) $scores->count() * (float) $this->booster_value,
            default  => 0.0,
        };
    }
    /**
     * Relasi ke grade_ranges: 5 baris (A-E) per SK Mengajar.
     * Digunakan oleh GradeRangeResolver untuk menentukan letter grade internal.
     */
    public function gradeRanges(): HasMany
    {
        return $this->hasMany(GradeRange::class);
    }

    /**
     * Relasi ke narrative_templates: template kustom guru per SK Mengajar.
     * Jika tidak ada, sistem fallback ke template default Admin.
     */
    public function narrativeTemplates(): HasMany
    {
        return $this->hasMany(NarrativeTemplate::class);
    }

    protected static function booted(): void
    {
        static::creating(function (TeachingAssignment $model) {
            // Jika KKTP tidak diisi saat create,
            // ambil default dari school_settings
            if (is_null($model->kktp)) {
                $defaultKkm = \App\Models\SchoolSetting::first()?->default_kkm ?? 75;
                $model->kktp = $defaultKkm;
            }
        });

        // Auto-seed grade_ranges setelah SK Mengajar dibuat
        static::created(function (TeachingAssignment $model) {
            GradeRangeResolver::seedDefaults($model);
        });

        // Auto-recalculate grade_ranges jika KKTP diubah
        static::updated(function (TeachingAssignment $model) {
            if ($model->wasChanged('kktp')) {
                GradeRangeResolver::seedDefaults($model);
            }

            // Setelan booster berubah -> hitung ulang nilai akhir seluruh siswa.
            if ($model->wasChanged(['booster_mode', 'booster_value'])) {
                $model->recalculateFinalGrades();
            }
        });
    }

    /**
     * Hitung ulang FinalGrade seluruh siswa aktif di SK Mengajar ini.
     * Menghormati is_locked & is_manual_override (tidak menimpa nilai terkunci/override).
     */
    public function recalculateFinalGrades(): void
    {
        $semester = $this->academicPeriod->semester;

        $studentIds = \App\Models\Enrollment::where('classroom_id', $this->classroom_id)
            ->where('academic_period_id', $this->academic_period_id)
            ->where('status', 'active')
            ->pluck('student_id');

        foreach ($studentIds as $studentId) {
            // Pre-check untuk menghemat calculateFinalGrade() pada nilai terkunci.
            // Penegakan sesungguhnya tetap di FinalGrade::snapshot() (defense-in-depth).
            $existing = FinalGrade::where('student_id', $studentId)
                ->where('teaching_assignment_id', $this->id)
                ->where('semester', $semester)
                ->first();

            if ($existing?->is_locked || $existing?->is_manual_override) {
                continue;
            }

            $score = $this->calculateFinalGrade($studentId);

            FinalGrade::snapshot(
                $studentId,
                $this->id,
                $semester,
                [
                    'final_score' => $score > 0 ? $score : null,
                    'grade_label' => $score > 0 ? GradeRangeResolver::resolve($this, $score) : null,
                ]
            );
        }
    }

    /**
     * Accessor terpusat untuk KKTP.
     * Fallback chain: DB value -> SchoolSetting default -> hardcoded 75.
     * Satu-satunya sumber kebenaran untuk default KKTP di seluruh aplikasi.
     */
    protected function kktpOrDefault(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ($this->kktp 
                ?? \App\Models\SchoolSetting::first()?->default_kkm 
                ?? 75)
        );
    }
}