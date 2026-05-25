<?php

namespace App\Services;

use App\Models\BkStudentResponse;

class VakScoringService
{
    /**
     * Score the VAK questionnaire based on student answers.
     *
     * @param BkStudentResponse $response
     * @return array
     */
    public function score(BkStudentResponse $response): array
    {
        // Eager load the answers with their selected options to prevent N+1
        $response->load('answers.selectedOption');

        $counts = [
            'VISUAL' => 0,
            'AUDITORI' => 0,
            'KINESTETIK' => 0,
        ];

        $totalAnswers = 0;

        foreach ($response->answers as $answer) {
            $optionCode = $answer->selectedOption?->option_code;
            if ($optionCode && array_key_exists($optionCode, $counts)) {
                $counts[$optionCode]++;
                $totalAnswers++;
            }
        }

        if ($totalAnswers === 0) {
            return [
                'dominant_style' => 'Tidak Diketahui',
                'dominant_percentage' => 0,
                'scores' => $counts,
                'recommendation' => 'Siswa belum menjawab pertanyaan dengan lengkap atau opsi tidak valid.',
            ];
        }

        // Find the maximum score
        $maxScore = max($counts);
        
        // Find all styles that have the maximum score (to handle ties)
        $dominantStyles = array_keys($counts, $maxScore);

        $styleName = implode('-', array_map(function($style) {
            return ucfirst(strtolower($style));
        }, $dominantStyles));

        if (count($dominantStyles) > 1) {
            $dominantStyleString = "Campuran ($styleName)";
        } else {
            $dominantStyleString = ucfirst(strtolower($dominantStyles[0]));
        }

        $percentage = ($maxScore / $totalAnswers) * 100;

        $recommendation = $this->generateRecommendation($dominantStyles);

        return [
            'dominant_style' => $dominantStyleString,
            'dominant_percentage' => round($percentage, 2),
            'scores' => $counts,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Generate recommendation based on dominant style(s).
     *
     * @param array $dominantStyles
     * @return string
     */
    private function generateRecommendation(array $dominantStyles): string
    {
        $recommendations = [];

        if (in_array('VISUAL', $dominantStyles)) {
            $recommendations[] = "Visual: Gunakan mind mapping, grafik, warna-warni (stabilo) pada catatan, dan perbanyak membaca buku bergambar atau menonton video pembelajaran.";
        }
        
        if (in_array('AUDITORI', $dominantStyles)) {
            $recommendations[] = "Auditori: Belajar kelompok dan berdiskusi sangat disarankan. Rekam penjelasan guru dan dengarkan ulang, serta baca materi dengan suara lantang.";
        }
        
        if (in_array('KINESTETIK', $dominantStyles)) {
            $recommendations[] = "Kinestetik: Belajar sambil melakukan aktivitas fisik seperti berjalan atau mengunyah permen karet. Gunakan alat peraga, eksperimen langsung, dan beri jeda istirahat singkat setiap 20 menit saat belajar.";
        }

        if (count($dominantStyles) > 1) {
            return "Anda memiliki gaya belajar campuran. Cobalah menggabungkan metode berikut:\n" . implode("\n", $recommendations);
        }

        return $recommendations[0];
    }
}
