<?php
// app/Observers/AttendanceObserver.php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\AttendanceSummary;

class AttendanceObserver
{
    /**
     * Dipanggil setelah record Attendance berhasil dibuat atau diupdate.
     */
    public function saved(Attendance $attendance): void
    {
        $this->recalculate($attendance);
    }

    /**
     * Dipanggil setelah record Attendance berhasil dihapus.
     */
    public function deleted(Attendance $attendance): void
    {
        $this->recalculate($attendance);
    }

    /**
     * Inti logika: hitung ulang rekap dari data aktual di database.
     *
     * Menggunakan pendekatan RECALCULATE (bukan increment/decrement)
     * untuk menghindari data tidak sinkron akibat bulk operation
     * atau penghapusan massal yang melewati Observer.
     */
    private function recalculate(Attendance $attendance): void
    {
        $assignmentId = $attendance->teaching_assignment_id;
        $studentId = $attendance->student_id;

        // ✅ PERBAIKAN N+1: Eager load relasi dalam 1 query.
        // Sebelumnya 2 query terpisah: teachingAssignment, academicPeriod
        $attendance->load('teachingAssignment.academicPeriod');

        $semester = $attendance->teachingAssignment
            ->academicPeriod
            ->semester; // 'odd' atau 'even'

        // Hitung ulang semua status dari data AKTUAL di database.
        // Status 'holiday' sengaja tidak diikutsertakan karena bukan
        // kehadiran/ketidakhadiran siswa, melainkan kondisi sekolah.
        $counts = Attendance::query()
            ->where('teaching_assignment_id', $assignmentId)
            ->where('student_id', $studentId)
            ->whereIn('status', ['present', 'permit', 'sick', 'alpha'])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $present = $counts['present'] ?? 0;
        $permit = $counts['permit'] ?? 0;
        $sick = $counts['sick'] ?? 0;
        $alpha = $counts['alpha'] ?? 0;
        $total = $present + $permit + $sick + $alpha;

        // Persentase kehadiran: hanya menghitung 'present' / total pertemuan efektif.
        // Izin dan sakit TIDAK dianggap hadir, sesuai standar rapor SMP.
        $percentage = $total > 0
            ? round(($present / $total) * 100, 2)
            : 0.00;

        AttendanceSummary::updateOrCreate(
            [
                'student_id' => $studentId,
                'teaching_assignment_id' => $assignmentId,
                'semester' => $semester,
            ],
            [
                'present' => $present,
                'permit' => $permit,
                'sick' => $sick,
                'alpha' => $alpha,
                'attendance_percentage' => $percentage,
            ]
        );
    }
}