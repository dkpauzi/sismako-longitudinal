<?php
// app/Observers/GradeObserver.php

namespace App\Observers;

use App\Models\Grade;
use App\Models\FinalGrade;

class GradeObserver
{
    /**
     * Dipanggil setelah Grade berhasil dibuat atau diupdate.
     */
    public function saved(Grade $grade): void
    {
        $this->recalculate($grade);
    }

    /**
     * Dipanggil setelah Grade berhasil dihapus.
     */
    public function deleted(Grade $grade): void
    {
        $this->recalculate($grade);
    }

    /**
     * Inti logika: panggil calculateFinalGrade() dari TeachingAssignment,
     * lalu simpan hasilnya ke final_grades sebagai snapshot rapor.
     *
     * PENTING: Jika nilai sudah dikunci (is_locked = true) oleh Wali Kelas,
     * Observer TIDAK akan mengupdate — nilai rapor yang sudah dicetak
     * tidak boleh berubah tanpa persetujuan eksplisit.
     */
    private function recalculate(Grade $grade): void
    {
        // ✅ PERBAIKAN N+1: Eager load seluruh chain relasi dalam 1 query.
        // Sebelumnya 3 query terpisah: assessment, teachingAssignment, academicPeriod
        $grade->load('assessment.teachingAssignment.academicPeriod');

        $assessment = $grade->assessment;
        $assignment = $assessment->teachingAssignment;
        $semester = $assignment->academicPeriod->semester;
        $studentId = $grade->student_id;

        // Cek apakah nilai akhir siswa ini sudah dikunci oleh Wali Kelas.
        // Jika sudah dikunci, hentikan proses — jangan timpa nilai rapor.
        $existing = FinalGrade::where('student_id', $studentId)
            ->where('teaching_assignment_id', $assignment->id)
            ->where('semester', $semester)
            ->first();

        if ($existing?->is_locked) {
            return; // Nilai sudah dikunci, skip update
        }

        // Panggil method calculateFinalGrade() yang sudah ada di TeachingAssignment.
        // Method ini sudah menangani semua formula (average, weighting, percentage)
        // dan formative boost — kita tidak perlu duplikasi logikanya.
        $finalScore = $assignment->calculateFinalGrade($studentId);

        // Tentukan predikat berdasarkan nilai akhir.
        // Sesuaikan ambang batas ini dengan kebijakan sekolah jika berbeda.
        $gradeLabel = $this->resolveGradeLabel($finalScore, $assignment->kktp ?? 75);

        FinalGrade::updateOrCreate(
            [
                'student_id' => $studentId,
                'teaching_assignment_id' => $assignment->id,
                'semester' => $semester,
            ],
            [
                'final_score' => $finalScore > 0 ? $finalScore : null,
                'grade_label' => $finalScore > 0 ? $gradeLabel : null,
            ]
        );
    }

    /**
     * Tentukan predikat berdasarkan nilai akhir dan KKTP.
     *
     * Kurikulum Merdeka menggunakan 4 predikat:
     *   A = Sangat Baik  (>= 90)
     *   B = Baik         (>= KKTP, < 90)
     *   C = Cukup        (>= KKTP - 15, < KKTP)
     *   D = Perlu Bimbingan (< KKTP - 15)
     *
     * Ambang batas C dan D dihitung relatif terhadap KKTP agar
     * fleksibel — sekolah dengan KKTP 70 tidak disamakan dengan
     * sekolah yang KKTP-nya 80.
     */
    private function resolveGradeLabel(int|float $score, int $kktp): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= $kktp => 'B',
            $score >= $kktp - 15 => 'C',
            default => 'D',
        };
    }
}