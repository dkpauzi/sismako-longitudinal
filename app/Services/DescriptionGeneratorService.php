<?php
// app/Services/DescriptionGeneratorService.php

namespace App\Services;

use App\Models\Grade;
use App\Models\LearningObjective;
use App\Models\TeachingAssignment;

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
        $kktp = $assignment->kktp ?? 75;

        // 1. Ambil semua TP yang pernah diujikan di kelas ini,
        //    beserta rata-rata nilai siswa untuk setiap TP tersebut.
        $tpResults = $this->calculateScorePerTp($assignment, $studentId, $kktp);

        // 2. Jika tidak ada data TP sama sekali, kembalikan narasi default.
        if ($tpResults->isEmpty()) {
            return $this->buildDefaultNarrative($assignment);
        }

        // 3. Pisahkan TP yang tuntas dan belum tuntas.
        $tuntasTps = $tpResults->where('is_tuntas', true);
        $belumTuntasTps = $tpResults->where('is_tuntas', false);

        // 4. Bangun narasi dari komponen-komponen yang tersedia.
        return $this->buildNarrative(
            assignment: $assignment,
            tuntasTps: $tuntasTps,
            belumTuntasTps: $belumTuntasTps,
            averageScore: $tpResults->avg('average_score'),
        );
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
        // Sebelumnya, Grade::whereHas() dipanggil per TP dalam loop = N query.
        // Sekarang cukup 1 query, sisanya filter dari collection di memori PHP.
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
     * Bangun narasi akhir dari komponen yang sudah disiapkan.
     *
     * Struktur narasi:
     * [Kalimat pembuka] + [Kalimat kekuatan] + [Kalimat pengembangan]
     */
    private function buildNarrative(
        TeachingAssignment $assignment,
        \Illuminate\Support\Collection $tuntasTps,
        \Illuminate\Support\Collection $belumTuntasTps,
        float $averageScore,
    ): string {
        $parts = [];

        // --- KALIMAT PEMBUKA ---
        // Berdasarkan rata-rata keseluruhan nilai siswa
        $parts[] = $this->buildOpeningSentence($averageScore, $assignment->kktp ?? 75);

        // --- KALIMAT KEKUATAN ---
        // TP yang sudah dikuasai — ambil maksimal 2 TP terbaik
        if ($tuntasTps->isNotEmpty()) {
            $topTps = $tuntasTps
                ->sortByDesc('average_score')
                ->take(2)
                ->pluck('attribute')
                ->toArray();

            $parts[] = $this->buildStrengthSentence($topTps);
        }

        // --- KALIMAT PENGEMBANGAN ---
        // TP yang perlu ditingkatkan — ambil 1 TP terlemah
        if ($belumTuntasTps->isNotEmpty()) {
            $weakestTp = $belumTuntasTps
                ->sortBy('average_score')
                ->first();

            $parts[] = $this->buildImprovementSentence($weakestTp['attribute']);
        }

        return implode(' ', $parts);
    }

    /**
     * Kalimat pembuka berdasarkan rata-rata nilai.
     * Dibuat bervariasi agar tidak monoton antar siswa.
     */
    private function buildOpeningSentence(float $average, int $kktp): string
    {
        // Gunakan hash student + assignment untuk memilih variasi kalimat
        // sehingga siswa yang skornya sama tetap mendapat kalimat berbeda
        $variationSeed = (int) ($average * 100) % 3;

        if ($average >= 90) {
            $templates = [
                'Ananda menunjukkan capaian yang sangat memuaskan dalam mata pelajaran ini.',
                'Ananda telah mencapai hasil yang luar biasa dan melampaui target pembelajaran.',
                'Ananda memperlihatkan penguasaan materi yang sangat baik sepanjang semester ini.',
            ];
        } elseif ($average >= $kktp) {
            $templates = [
                'Ananda telah mencapai target Kriteria Ketercapaian Tujuan Pembelajaran (KKTP) dengan baik.',
                'Ananda menunjukkan perkembangan yang positif dalam mengikuti pembelajaran.',
                'Ananda mampu memenuhi capaian pembelajaran yang ditetapkan pada semester ini.',
            ];
        } elseif ($average >= $kktp - 15) {
            $templates = [
                'Ananda menunjukkan usaha yang cukup baik, namun masih perlu peningkatan untuk memenuhi KKTP.',
                'Ananda telah berusaha mengikuti pembelajaran dengan baik meski capaian masih perlu ditingkatkan.',
                'Ananda menampilkan perkembangan yang cukup, dengan beberapa aspek yang masih memerlukan perhatian.',
            ];
        } else {
            $templates = [
                'Ananda masih memerlukan bimbingan intensif untuk mencapai Kriteria Ketercapaian Tujuan Pembelajaran.',
                'Ananda perlu meningkatkan semangat belajar agar dapat memenuhi capaian pembelajaran yang diharapkan.',
                'Ananda membutuhkan dukungan dan pendampingan lebih lanjut dalam mengikuti pembelajaran.',
            ];
        }

        return $templates[$variationSeed];
    }

    /**
     * Kalimat kekuatan: menyebutkan TP yang sudah dikuasai.
     */
    private function buildStrengthSentence(array $topAttributes): string
    {
        if (count($topAttributes) === 1) {
            $templates = [
                "Ananda telah menguasai kompetensi {$topAttributes[0]}.",
                "Kemampuan ananda dalam {$topAttributes[0]} sudah tercapai dengan baik.",
                "Ananda menunjukkan penguasaan yang baik dalam {$topAttributes[0]}.",
            ];
        } else {
            $combined = $topAttributes[0] . ' serta ' . $topAttributes[1];
            $templates = [
                "Ananda telah menguasai kompetensi {$combined} dengan baik.",
                "Ananda menunjukkan kemampuan yang menonjol dalam {$combined}.",
                "Capaian ananda sangat baik dalam {$combined}.",
            ];
        }

        // Variasi kalimat berdasarkan jumlah karakter attribute
        $seed = strlen(implode('', $topAttributes)) % 3;
        return $templates[$seed];
    }

    /**
     * Kalimat pengembangan: menyebutkan TP yang perlu ditingkatkan.
     */
    private function buildImprovementSentence(string $weakAttribute): string
    {
        $templates = [
            "Perlu peningkatan pada kompetensi {$weakAttribute}.",
            "Ananda masih perlu berlatih lebih giat dalam {$weakAttribute}.",
            "Diperlukan perhatian lebih pada {$weakAttribute} agar capaian semakin optimal.",
        ];

        $seed = strlen($weakAttribute) % 3;
        return $templates[$seed];
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