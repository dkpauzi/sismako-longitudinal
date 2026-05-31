<?php

namespace App\Services;

use App\Models\GradeRange;
use App\Models\TeachingAssignment;

/**
 * Service untuk me-resolve skor numerik menjadi letter grade internal (A-E).
 *
 * ATURAN BISNIS KRITIS:
 * - C.min = KKTP → Grades A, B, C = LULUS (≥ KKTP)
 * - Grades D, E = TIDAK LULUS (< KKTP)
 * - Letter grade SEPENUHNYA INTERNAL, tidak boleh tampil di rapor/portal siswa
 *
 * Jika grade_ranges belum diisi untuk suatu TeachingAssignment,
 * service ini akan fallback ke perhitungan default berdasarkan KKTP.
 */
class GradeRangeResolver
{
    /**
     * Resolve skor menjadi letter grade (A-E).
     *
     * Strategi: ambil grade_range yang min_score <= score, urut DESC, ambil pertama.
     * Ini menghindari masalah boundary overlap.
     *
     * @param  TeachingAssignment $assignment  SK Mengajar yang memiliki grade_ranges
     * @param  float              $score       Nilai akhir siswa (0-100)
     * @return string             Letter grade: 'A', 'B', 'C', 'D', atau 'E'
     */
    public static function resolve(TeachingAssignment $assignment, float $score): string
    {
        // Coba resolve dari tabel grade_ranges
        $range = GradeRange::where('teaching_assignment_id', $assignment->id)
            ->where('min_score', '<=', $score)
            ->orderByDesc('min_score')
            ->first();

        if ($range) {
            return $range->letter;
        }

        // Fallback: gunakan default formula jika grade_ranges belum di-seed
        return self::resolveDefault($score, $assignment->kktp_or_default);
    }

    /**
     * Fallback resolver menggunakan formula default berbasis KKTP.
     *
     * KKTP Anchor: C.min = KKTP
     * - A, B, C = Lulus (di atas/sama dengan KKTP)
     * - D, E    = Tidak lulus (di bawah KKTP)
     *
     * @param  float $score  Nilai siswa
     * @param  int   $kktp   Nilai KKTP dari TeachingAssignment
     * @return string        Letter grade
     */
    public static function resolveDefault(float $score, int $kktp): string
    {
        $defaults = self::calculateDefaultRanges($kktp);

        // Resolve: cari grade yang min_score <= score, urut dari tertinggi
        foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
            if ($score >= $defaults[$letter]['min_score']) {
                return $letter;
            }
        }

        return 'E'; // Skor 0 atau di bawah semua range
    }

    /**
     * Hitung 5 range default berdasarkan KKTP.
     *
     * Formula:
     * - Ruang di atas KKTP (100 - KKTP) dibagi 3 untuk A, B, C
     * - Ruang di bawah KKTP (0 - KKTP-1) dibagi 2 untuk D, E
     *   dengan D mendapat 20% dari KKTP sebagai buffer
     *
     * Contoh (KKTP = 75):
     *   A: 91 - 100  (Sangat Memuaskan)
     *   B: 83 - 90   (Memuaskan)
     *   C: 75 - 82   (Cukup — mulai dari KKTP)
     *   D: 60 - 74   (Kurang — di bawah KKTP)
     *   E: 0  - 59   (Sangat Kurang)
     *
     * @param  int   $kktp  Nilai KKTP
     * @return array        ['A' => ['min_score' => x, 'max_score' => y], ...]
     */
    public static function calculateDefaultRanges(int $kktp): array
    {
        // ── DI ATAS KKTP: A, B, C ────────────────────────────────
        $aboveSpace = 100 - $kktp;
        $step = (int) floor($aboveSpace / 3);

        // Pastikan step minimal 1 agar range tidak collapse
        $step = max($step, 1);

        $cMin = $kktp;                       // C.min = KKTP (ANCHOR)
        $cMax = $kktp + $step - 1;

        $bMin = $kktp + $step;
        $bMax = $kktp + (2 * $step) - 1;

        $aMin = $kktp + (2 * $step);
        $aMax = 100;

        // ── DI BAWAH KKTP: D, E ─────────────────────────────────
        // D mendapat buffer 20% dari KKTP (minimum 10 poin)
        $belowBuffer = max((int) floor($kktp * 0.2), 10);
        $dMin = max(0, $kktp - $belowBuffer);
        $dMax = $kktp - 1;

        $eMin = 0;
        $eMax = max(0, $dMin - 1);

        return [
            'A' => ['min_score' => (float) $aMin, 'max_score' => (float) $aMax],
            'B' => ['min_score' => (float) $bMin, 'max_score' => (float) $bMax],
            'C' => ['min_score' => (float) $cMin, 'max_score' => (float) $cMax],
            'D' => ['min_score' => (float) $dMin, 'max_score' => (float) $dMax],
            'E' => ['min_score' => (float) $eMin, 'max_score' => (float) $eMax],
        ];
    }

    /**
     * Seed atau update 5 baris grade_ranges untuk suatu TeachingAssignment.
     * Dipanggil otomatis saat TeachingAssignment dibuat atau KKTP diubah.
     *
     * @param  TeachingAssignment $assignment
     * @return void
     */
    public static function seedDefaults(TeachingAssignment $assignment): void
    {
        $kktp = $assignment->kktp_or_default;
        $defaults = self::calculateDefaultRanges($kktp);

        foreach ($defaults as $letter => $range) {
            GradeRange::updateOrCreate(
                [
                    'teaching_assignment_id' => $assignment->id,
                    'letter' => $letter,
                ],
                [
                    'min_score' => $range['min_score'],
                    'max_score' => $range['max_score'],
                ]
            );
        }
    }
}
