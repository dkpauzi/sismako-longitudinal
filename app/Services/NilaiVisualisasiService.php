<?php
// app/Services/NilaiVisualisasiService.php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\ClassHomeroom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class NilaiVisualisasiService
{
    /**
     * Cek apakah user yang sedang login boleh melihat
     * grafik nilai siswa tertentu.
     */
    public function canViewStudent(int $studentId): bool
    {
        $user = Auth::user();

        // Super Admin, Admin & Kepala Sekolah → selalu bisa (staf sekolah)
        if ($user->hasAnyRole(['super_admin', 'admin', 'headmaster'])) {
            return true;
        }

        // Siswa → hanya diri sendiri
        if ($user->hasRole('student')) {
            return $user->student?->id === $studentId;
        }

        // Wali Siswa → hanya anak yang terhubung via guardian_user_id
        if ($user->hasRole('guardian')) {
            return $user->guardianStudents()->where('id', $studentId)->exists();
        }

        // Guru → cek apakah mengajar siswa ini di periode aktif.
        // Null-safe: akun ber-role teacher tanpa profil Teacher tidak boleh 500.
        if ($user->hasRole('teacher')) {
            $teacherId = $user->teacher?->id;

            return $teacherId
                ? $this->guruMengajarSiswa($teacherId, $studentId)
                : false;
        }

        return false;
    }

    /**
     * Ambil data nilai siswa lintas periode untuk grafik longitudinal.
     * Return format: per periode → per mapel → nilai
     *
     * [
     *   '2024/2025 Ganjil' => [
     *     'Matematika' => 85,
     *     'Bahasa Indonesia' => 90,
     *   ],
     *   ...
     * ]
     */
    public function getNilaiLongitudinal(int $studentId): array
    {
        $user = Auth::user();
        $isTeacher = $user && $user->hasRole('teacher');
        $teacherId = $isTeacher ? $user->teacher?->id : null;
        
        // Ambil semua periode yang pernah diikuti siswa, urutkan berdasarkan waktu mulai semester
        $enrollments = Enrollment::where('student_id', $studentId)
            ->with('academicPeriod')
            ->get()
            ->sortBy(fn($e) => $e->academicPeriod?->start_date)
            ->values();
            
        $isHomeroomForStudent = false;
        $allowedSubjectIds = [];
        
        if ($isTeacher && $teacherId) {
            // Cek apakah guru ini adalah WALI KELAS untuk siswa ini di salah satu enrollmentnya
            $isHomeroomForStudent = ClassHomeroom::where('teacher_id', $teacherId)
                ->whereIn('classroom_id', $enrollments->pluck('classroom_id'))
                ->exists();
                
            if (!$isHomeroomForStudent) {
                // Jika bukan wali kelas, cari mapel apa saja yang diajar oleh guru ini
                $allowedSubjectIds = TeachingAssignment::where('teacher_id', $teacherId)
                    ->pluck('subject_id')
                    ->toArray();
            }
        }

        // ✅ PERBAIKAN N+1: Load SEMUA FinalGrade siswa dalam 1 query.
        $allGrades = FinalGrade::where('student_id', $studentId)
            ->with('teachingAssignment.subject')
            ->get();

        $result = [];

        foreach ($enrollments as $enrollment) {
            $period      = $enrollment->academicPeriod;
            $periodLabel = $period->name; // e.g. "2024/2025 Ganjil"

            // ✅ Filter dari collection (bukan query baru)
            $grades = $allGrades->filter(fn($g) =>
                $g->semester === $period->semester &&
                $g->teachingAssignment &&
                $g->teachingAssignment->academic_period_id === $period->id &&
                $g->teachingAssignment->classroom_id === $enrollment->classroom_id
            );

            $result[$periodLabel] = [];

            foreach ($grades as $grade) {
                $subject = $grade->teachingAssignment->subject;
                
                // --- PEMBATASAN UNTUK GURU MAPEL ---
                // Jika user adalah guru, BUKAN wali kelas siswa ini,
                // maka hanya tampilkan nilai untuk mapel yang ia ajar.
                if ($isTeacher && !$isHomeroomForStudent) {
                    if (!in_array($subject->id, $allowedSubjectIds)) {
                        continue; // Skip mapel yang tidak diajar oleh guru ini
                    }
                }
                
                $result[$periodLabel][$subject->name] = (float) $grade->final_score;
            }
        }

        return $result;
    }

    /**
     * Ambil ringkasan nilai per kelas untuk dashboard kepsek/wali.
     * Return: rata-rata nilai per mapel per kelas.
     */
    public function getRingkasanNilaiKelas(int $classroomId): array
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        if (!$activePeriod) return [];

        $assignments = TeachingAssignment::where('classroom_id', $classroomId)
            ->where('academic_period_id', $activePeriod->id)
            ->whereHas('subject', fn($q) => $q->whereIn('type', ['mandatory']))
            ->with([
                'subject',
                // ✅ ANTI-KONTAMINASI LINTAS SEMESTER: rata-rata kelas hanya
                // boleh dihitung dari nilai semester periode AKTIF. Tanpa scope
                // ini, nilai semester lain (mis. hasil override manual) ikut
                // terhitung dan mendistorsi metrik.
                // ✅ HEMAT MEMORI: hanya kolom yang benar-benar dipakai.
                'finalGrades' => fn($q) => $q
                    ->select('id', 'teaching_assignment_id', 'student_id', 'final_score', 'semester')
                    ->where('semester', $activePeriod->semester)
                    ->whereNotNull('final_score'),
            ])
            ->get();

        $result = [];

        foreach ($assignments as $assignment) {
            // Sudah difilter di eager load — tidak perlu filter ulang per iterasi
            $grades = $assignment->finalGrades;

            if ($grades->isEmpty()) continue;

            $result[] = [
                'subject'    => $assignment->subject->name,
                'rata_rata'  => round($grades->avg('final_score'), 1),
                'tertinggi'  => $grades->max('final_score'),
                'terendah'   => $grades->min('final_score'),
                'jumlah'     => $grades->count(),
            ];
        }

        // Sort dari rata-rata tertinggi
        usort($result, fn($a, $b) => $b['rata_rata'] <=> $a['rata_rata']);

        return $result;
    }

    /**
     * Data kinerja guru untuk dashboard kepala sekolah (garis besar).
     *
     * METRIK (OPSI A — BERBASIS SISWA):
     * "Kelengkapan Nilai %" = Σ(siswa aktif yang sudah punya nilai akhir per mapel)
     *                         ÷ Σ(siswa aktif × mapel yang diajar guru) × 100.
     * Metrik ini TIDAK bergantung pada penautan TP↔asesmen, sehingga andal apa
     * adanya (lihat catatan Opsi B di bawah).
     *
     * ──────────────────────────────────────────────────────────────────────
     * CATATAN PENGEMBANGAN — MIGRASI KE OPSI B (BERBASIS TP):
     * Bila kelak ingin mengukur progres dari sisi "tiap TP harus ada sumatif":
     *   1) PRASYARAT DATA: jadikan penautan TP wajib pada asesmen kategori
     *      sumatif. Di AssessmentsRelationManager, field CheckboxList
     *      'learningObjectives' harus ->required() saat category diawali
     *      'sumatif_' — tanpa ini metrik B under-report (asesmen sumatif yang
     *      tak tertaut TP tidak terhitung).
     *   2) RUMUS B: untuk tiap TA akademik, denominator = jumlah TP mapel itu
     *      (LearningObjective: subject_id + academic_period_id + grade_level);
     *      numerator = TP yang punya >=1 asesmen sumatif (category LIKE
     *      'sumatif_%') yang SUDAH ada grade-nya (grades.score not null).
     *      Relasi tersedia: LearningObjective::assessments() (pivot
     *      assessment_learning_objective) dan Assessment::grades().
     *   3) Ganti blok perhitungan $gradedCount/$totalStudents di bawah dengan
     *      pasangan (tp_dinilai / total_tp), sisakan persen & status apa adanya.
     *   4) Tambahkan test: TP tanpa sumatif => tidak terhitung; TP dgn sumatif
     *      bernilai => terhitung; asesmen sumatif tanpa tautan TP => TIDAK
     *      menaikkan angka (membuktikan prasyarat #1).
     * ──────────────────────────────────────────────────────────────────────
     *
     * Metrik lengkap: kelengkapan nilai + jumlah jurnal KBM.
     */
    public function getKinerjaGuru(): array
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        if (!$activePeriod) return [];

        $assignments = TeachingAssignment::where('academic_period_id', $activePeriod->id)
            // Mapel BERNILAI ANGKA: wajib (mandatory) & muatan lokal/pilihan (elective).
            // P5 (kokurikuler) & ekstrakurikuler dikecualikan karena naratif, bukan angka.
            ->whereHas('subject', fn($q) => $q->whereIn('type', ['mandatory', 'elective']))
            ->with([
                'teacher.user',
                'subject',
                'classroom',
                // ✅ PERBAIKAN: Scope finalGrades ke semester aktif saja.
                // Sebelumnya memuat SEMUA semester (3 tahun = 6 semester data).
                'finalGrades' => fn($q) => $q->where('semester', $activePeriod->semester)
                                              ->whereNotNull('final_score'),
                'lessonJournals',
            ])
            ->get()
            ->groupBy('teacher_id');

        $result = [];

        // ✅ PERBAIKAN N+1: Batch query enrollment counts dalam 1 query.
        // Sebelumnya Enrollment::count() dipanggil per TA di dalam loop.
        $enrollmentCounts = Enrollment::where('academic_period_id', $activePeriod->id)
            ->where('status', 'active')
            ->selectRaw('classroom_id, COUNT(*) as total')
            ->groupBy('classroom_id')
            ->pluck('total', 'classroom_id');

        foreach ($assignments as $teacherId => $teacherAssignments) {
            $teacher = $teacherAssignments->first()->teacher;

            // Hitung kelengkapan nilai
            $totalStudents  = 0;
            $totalGraded    = 0;
            $totalJournals  = 0;

            foreach ($teacherAssignments as $ta) {
                // ✅ Lookup dari collection (bukan query baru)
                $enrolledCount = $enrollmentCounts[$ta->classroom_id] ?? 0;

                // finalGrades sudah di-scope (semester aktif + final_score not null)
                // di eager load, jadi tinggal dihitung.
                $gradedCount = $ta->finalGrades->count();

                // ✅ PERBAIKAN BUG: cap pada jumlah siswa AKTIF. Siswa yang sudah
                // pindah/keluar (enrollment non-aktif) namun masih menyimpan nilai
                // akhir tidak boleh membuat kelengkapan melebihi 100%.
                $gradedCount = min($gradedCount, $enrolledCount);

                $totalStudents += $enrolledCount;
                $totalGraded   += $gradedCount;
                $totalJournals += $ta->lessonJournals->count();
            }

            $persentaseNilai = $totalStudents > 0
                ? round(($totalGraded / $totalStudents) * 100, 1)
                : 0;

            $result[] = [
                'teacher_id'         => $teacherId,
                // Null-safe: guru tanpa akun login (mis. pembina eksternal)
                // memakai nama profil; jangan 500 karena user_id null.
                'nama_guru'          => $teacher->user?->name ?? $teacher->name ?? 'Data Belum Lengkap',
                'jumlah_kelas'       => $teacherAssignments->count(),
                'total_siswa'        => $totalStudents,
                'nilai_terisi'       => $totalGraded,
                'persen_nilai'       => $persentaseNilai,
                'jurnal_masuk'       => $totalJournals,
                'status'             => $persentaseNilai >= 100 ? 'Lengkap'
                    : ($persentaseNilai >= 50 ? 'Sebagian' : 'Belum'),
            ];
        }

        // Sort dari persentase terendah (yang butuh perhatian)
        usort($result, fn($a, $b) => $a['persen_nilai'] <=> $b['persen_nilai']);

        return $result;
    }

    /**
     * Daftar siswa yang boleh dilihat oleh user yang sedang login.
     * Digunakan untuk dropdown pemilihan siswa di halaman detail.
     */
    public function getAccessibleStudents(): Collection
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['super_admin', 'admin', 'headmaster'])) {
            return Student::with('user')->orderBy('name')->get();
        }

        if ($user->hasRole('student')) {
            // Null-safe: akun student tanpa profil Student → daftar kosong, bukan 500
            return $user->student
                ? Student::where('id', $user->student->id)->get()
                : collect();
        }

        // Wali Siswa → hanya anak-anak yang terhubung via guardian_user_id
        if ($user->hasRole('guardian')) {
            return $user->guardianStudents()->orderBy('name')->get();
        }

        if ($user->hasRole('teacher')) {
            // Null-safe: akun teacher tanpa profil Teacher → daftar kosong, bukan 500
            if (!$user->teacher) return collect();

            $activePeriod = AcademicPeriod::where('is_active', true)->first();
            if (!$activePeriod) return collect();

            // Siswa yang diajar guru ini di periode aktif
            $classroomIds = TeachingAssignment::where('teacher_id', $user->teacher->id)
                ->where('academic_period_id', $activePeriod->id)
                ->pluck('classroom_id');

            return Student::whereHas('enrollments', fn($q) =>
                $q->whereIn('classroom_id', $classroomIds)
                  ->where('academic_period_id', $activePeriod->id)
                  ->where('status', 'active')
            )->orderBy('name')->get();
        }

        return collect();
    }

    // ─── PRIVATE ─────────────────────────────────────────────────────────────

    private function guruMengajarSiswa(int $teacherId, int $studentId): bool
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        if (!$activePeriod) return false;

        return TeachingAssignment::where('teacher_id', $teacherId)
            ->where('academic_period_id', $activePeriod->id)
            ->whereHas('classroom.enrollments', fn($q) =>
                $q->where('student_id', $studentId)
                  ->where('academic_period_id', $activePeriod->id)
                  ->where('status', 'active')
            )
            ->exists();
    }
}