<?php
// app/Services/DescriptionGeneratorService.php

namespace App\Services;

use App\Models\LearningObjective;
use App\Models\NarrativeTemplate;
use App\Models\TeachingAssignment;

/**
 * MESIN PEMBUAT NARASI RAPOR (Rule Engine v2)
 *
 * Service ini menghasilkan deskripsi naratif rapor per siswa per mapel.
 * Alur:
 *   1. Hitung rata-rata skor per TP (Tujuan Pembelajaran)
 *   2. Konversi setiap skor TP ke letter grade (A-E) via GradeRangeResolver
 *   3. Identifikasi TP Max (grade tertinggi) dan TP Min (grade terendah)
 *   4. Ambil template narasi berdasarkan grade, ganti placeholder [TP]
 *   5. Gabungkan kalimat dengan konjungsi yang tepat
 *
 * Hierarki template:
 *   Guru Override → Admin Default → Hardcoded Fallback
 */
class DescriptionGeneratorService
{
    /**
     * Generate deskripsi naratif rapor untuk satu siswa di satu mapel.
     *
     * @param  TeachingAssignment  $assignment  SK Mengajar (mapel + kelas)
     * @param  int                 $studentId   ID siswa
     * @return string              Narasi hasil generate
     */
    public function generate(TeachingAssignment $assignment, int $studentId): string
    {
        $kktp = $assignment->kktp_or_default;

        // 1. Hitung rata-rata skor per TP
        $tpResults = $this->calculateScorePerTp($assignment, $studentId, $kktp);

        // 2. Jika tidak ada data TP, kembalikan narasi default
        if ($tpResults->isEmpty()) {
            return $this->buildDefaultNarrative($assignment);
        }

        // 3. Konversi setiap skor TP ke letter grade via GradeRangeResolver
        $tpWithGrades = $tpResults->map(function ($tp) use ($assignment) {
            $tp['grade'] = GradeRangeResolver::resolve($assignment, $tp['average_score']);
            return $tp;
        });

        // 4. Bangun narasi dengan Rule Engine
        return $this->buildGradeBasedNarrative($assignment, $tpWithGrades);
    }

    /**
     * Hitung rata-rata nilai siswa per Tujuan Pembelajaran.
     *
     * Alur:
     * assessment → assessment_learning_objective → learning_objective
     *                                ↓
     *                          grade (nilai siswa)
     */
    public function calculateScorePerTp(
        TeachingAssignment $assignment,
        int $studentId,
        int $kktp
    ): \Illuminate\Support\Collection {
        // Ambil semua TP yang diujikan di kelas ini
        $learningObjectives = LearningObjective::whereHas(
            'assessments',
            fn($q) => $q->where('teaching_assignment_id', $assignment->id)
        )
            ->where('subject_id', $assignment->subject_id)
            ->get();

        // ✅ PERBAIKAN N+1: Load semua asesmen beserta grades & TP-nya dalam 1 query.
        $assessments = $assignment->assessments()
            ->with([
                'learningObjectives',
                'grades' => fn($q) => $q->where('student_id', $studentId)->whereNotNull('score'),
            ])
            ->get();

        return $learningObjectives->map(function (LearningObjective $lo) use ($assessments, $kktp) {
            // ✅ Filter dari collection (bukan query baru)
            $scores = $assessments
                ->filter(fn($a) => $a->learningObjectives->contains('id', $lo->id))
                ->flatMap(fn($a) => $a->grades->pluck('score'));

            // Jika tidak ada nilai untuk TP ini, skip
            if ($scores->isEmpty()) {
                return null;
            }

            $average = round($scores->avg(), 2);

            return [
                'id' => $lo->id,
                'code' => $lo->code,
                'attribute' => $lo->attribute, // Ringkasan kompetensi untuk rapor
                'average_score' => $average,
                'is_tuntas' => $average >= $kktp,
            ];
        })->filter()->values(); // Hapus null (TP tanpa nilai)
    }

    /**
     * Rule Engine: Bangun narasi berdasarkan grade per TP.
     *
     * Logika:
     * - Jika semua TP memiliki grade sama: 1 kalimat, gabungkan TP dengan "dan"
     * - Jika Max != Min: 2 kalimat, masing-masing dengan template sesuai grade
     * - Konjungsi antar kalimat tergantung apakah Min lulus/gagal
     */
    private function buildGradeBasedNarrative(
        TeachingAssignment $assignment,
        \Illuminate\Support\Collection $tpWithGrades
    ): string {
        // Urutkan berdasarkan grade priority: A=1, B=2, C=3, D=4, E=5
        $gradePriority = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5];

        $sorted = $tpWithGrades->sortBy(fn($tp) => $gradePriority[$tp['grade']] ?? 5);

        $maxTp = $sorted->first();  // TP dengan grade tertinggi
        $minTp = $sorted->last();   // TP dengan grade terendah
        $maxGrade = $maxTp['grade'];
        $minGrade = $minTp['grade'];

        $assignmentId = $assignment->id;

        // ── CASE 1: Semua TP memiliki grade yang sama ─────────────
        if ($maxGrade === $minGrade) {
            $allAttributes = $tpWithGrades->pluck('attribute')->toArray();
            $combinedTp = $this->combineAttributes($allAttributes);
            $template = NarrativeTemplate::getTemplate($maxGrade, $assignmentId);

            return str_replace('[TP]', $combinedTp, $template);
        }

        // ── CASE 2: Max != Min — buat 2 kalimat ──────────────────

        // Kumpulkan semua TP untuk grade Max (bisa lebih dari 1)
        $maxTps = $tpWithGrades->where('grade', $maxGrade);
        $maxAttributes = $maxTps->pluck('attribute')->toArray();
        $maxCombined = $this->combineAttributes($maxAttributes);
        $maxTemplate = NarrativeTemplate::getTemplate($maxGrade, $assignmentId);
        $maxSentence = str_replace('[TP]', $maxCombined, $maxTemplate);

        // Kumpulkan semua TP untuk grade Min
        $minTps = $tpWithGrades->where('grade', $minGrade);
        $minAttributes = $minTps->pluck('attribute')->toArray();
        $minCombined = $this->combineAttributes($minAttributes);
        $minTemplate = NarrativeTemplate::getTemplate($minGrade, $assignmentId);
        $minSentence = str_replace('[TP]', $minCombined, $minTemplate);

        // ── Tentukan konjungsi ───────────────────────────────────
        $conjunction = $this->resolveConjunction($maxGrade, $minGrade);

        // Gabungkan: hilangkan titik di akhir kalimat pertama, tambah konjungsi
        $maxSentence = rtrim($maxSentence, '. ');
        $minSentence = lcfirst(ltrim($minSentence));

        return $maxSentence . $conjunction . $minSentence;
    }

    /**
     * Gabungkan beberapa atribut TP menjadi string natural.
     *
     * 1 TP:  "aljabar"
     * 2 TP:  "aljabar dan geometri"
     * 3+ TP: "aljabar, geometri, dan statistika"
     */
    public function combineAttributes(array $attributes): string
    {
        $count = count($attributes);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $attributes[0];
        }

        if ($count === 2) {
            return $attributes[0] . ' dan ' . $attributes[1];
        }

        // 3+: "a, b, dan c"
        $last = array_pop($attributes);
        return implode(', ', $attributes) . ', dan ' . $last;
    }

    /**
     * Tentukan konjungsi berdasarkan kombinasi grade Max dan Min.
     *
     * Rules:
     * - Min lulus (A/B/C):          ", serta "   (tambahan positif)
     * - Min gagal (D/E) tapi Max lulus:  ", namun "   (kontras)
     * - Keduanya gagal (D/E + D/E):      ", dan juga " (penambahan negatif)
     */
    public function resolveConjunction(string $maxGrade, string $minGrade): string
    {
        $passingGrades = ['A', 'B', 'C'];
        $maxPassing = in_array($maxGrade, $passingGrades);
        $minPassing = in_array($minGrade, $passingGrades);

        if ($minPassing) {
            // Min grade masih lulus → tambahan positif
            return ', serta ';
        }

        if ($maxPassing && !$minPassing) {
            // Max lulus tapi Min gagal → kontras
            return ', namun ';
        }

        // Keduanya gagal → penambahan negatif
        return ', dan juga ';
    }

    /**
     * Narasi default jika tidak ada data TP yang bisa diproses.
     */
    private function buildDefaultNarrative(TeachingAssignment $assignment): string
    {
        $subjectName = $assignment->subject->name;

        return "Ananda telah mengikuti pembelajaran {$subjectName} dengan baik. " .
            "Terus tingkatkan semangat belajar untuk mencapai hasil yang lebih optimal.";
    }
}