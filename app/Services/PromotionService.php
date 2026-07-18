<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class PromotionService
{
    /**
     * Ukuran chunk default untuk shared hosting.
     * Cukup kecil agar tidak melampaui execution time limit.
     */
    public const CHUNK_SIZE = 10;

    /**
     * Proses satu chunk kecil dari data promosi di dalam DB::transaction().
     *
     * Desain ini menghindari timeout di shared hosting:
     * - Hanya 10 siswa per transaction (bukan seluruh kelas).
     * - Livewire event recursion memastikan setiap chunk mendapat
     *   PHP execution timer yang fresh dari browser round-trip.
     *
     * @param array $chunk Array of student promotion data:
     *   [['enrollment_id' => int, 'action' => string, 'target_classroom_id' => int|null], ...]
     * @param int|null $targetAcademicPeriodId ID Tahun Ajaran Tujuan
     *
     * @return array ['success' => bool, 'message' => string, 'processed' => int]
     */
    public function processChunk(array $chunk, ?int $targetAcademicPeriodId = null): array
    {
        try {
            return DB::transaction(function () use ($chunk, $targetAcademicPeriodId) {
                $processedCount = 0;

                foreach ($chunk as $item) {
                    $this->processSinglePromotion($item, $targetAcademicPeriodId);
                    $processedCount++;
                }

                return [
                    'success' => true,
                    'message' => "{$processedCount} siswa berhasil diproses.",
                    'processed' => $processedCount,
                ];
            });
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Terjadi kesalahan: " . $e->getMessage(),
                'processed' => 0,
            ];
        }
    }

    /**
     * Proses massal seluruh daftar promosi dalam satu transaksi.
     *
     * PERINGATAN: Metode ini hanya aman untuk environment non-shared-hosting
     * atau untuk test suite. Di production gunakan processChunk() via event recursion.
     *
     * @param array $promotions Seluruh data promosi
     * @param int|null $targetAcademicPeriodId ID Tahun Ajaran Tujuan
     *
     * @return array ['success' => bool, 'message' => string, 'processed' => int]
     */
    public function processBatchPromotions(array $promotions, ?int $targetAcademicPeriodId = null): array
    {
        try {
            return DB::transaction(function () use ($promotions, $targetAcademicPeriodId) {
                $processedCount = 0;

                foreach ($promotions as $item) {
                    $this->processSinglePromotion($item, $targetAcademicPeriodId);
                    $processedCount++;
                }

                return [
                    'success' => true,
                    'message' => "{$processedCount} siswa berhasil diproses.",
                    'processed' => $processedCount,
                ];
            });
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Terjadi kesalahan: " . $e->getMessage(),
                'processed' => 0,
            ];
        }
    }

    /**
     * Proses satu siswa: update enrollment lama, buat enrollment baru
     * atau deaktivasi akun jika lulus.
     *
     * Metode ini TIDAK membungkus dalam transaction sendiri —
     * pemanggil bertanggung jawab atas transaction boundary.
     *
     * @param array $item Data promosi satu siswa
     * @param int|null $targetAcademicPeriodId ID Tahun Ajaran Tujuan
     * @throws Exception Jika data tidak valid
     */
    private function processSinglePromotion(array $item, ?int $targetAcademicPeriodId): void
    {
        $enrollmentId = $item['enrollment_id'];
        $action = $item['action'];
        $targetClassroomId = $item['target_classroom_id'] ?? null;

        $oldEnrollment = Enrollment::with('student.user')->find($enrollmentId);
        if (!$oldEnrollment) return;

        // Update status enrollment lama
        $oldEnrollment->update(['status' => $action]);

        $student = $oldEnrollment->student;

        if ($action === 'promoted' || $action === 'retained') {
            if (!$targetAcademicPeriodId || !$targetClassroomId) {
                throw new Exception(
                    "Tahun Ajaran Tujuan dan Kelas Tujuan wajib diisi untuk siswa yang Naik/Tinggal Kelas."
                );
            }

            // ── GERBANG JENJANG SERVER-SIDE (Audit 3.8) ───────────────────
            // Batasan UI wizard tidak cukup; payload Livewire yang dimodifikasi
            // bisa mengirim kelas lintas jenjang. Tegakkan aturan di service:
            //   promoted → kelas tujuan HARUS satu tingkat di atas kelas asal
            //   retained → kelas tujuan HARUS tingkat yang sama dengan asal
            $sourceGrade = $oldEnrollment->classroom?->grade_level
                ?? Classroom::whereKey($oldEnrollment->classroom_id)->value('grade_level');
            $targetGrade = Classroom::whereKey($targetClassroomId)->value('grade_level');

            if ($sourceGrade !== null && $targetGrade !== null) {
                $expected = $action === 'promoted' ? $sourceGrade + 1 : $sourceGrade;
                if ((int) $targetGrade !== (int) $expected) {
                    $labelAksi = $action === 'promoted' ? 'Naik Kelas' : 'Tinggal Kelas';
                    throw new Exception(
                        "Kelas tujuan tidak valid untuk {$labelAksi}: tingkat {$targetGrade} " .
                        "tidak sesuai (seharusnya tingkat {$expected})."
                    );
                }
            }

            // Buat enrollment baru di periode dan kelas tujuan
            Enrollment::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_period_id' => $targetAcademicPeriodId,
                ],
                [
                    'classroom_id' => $targetClassroomId,
                    'status' => 'active',
                    'promoted_from_enrollment_id' => $oldEnrollment->id,
                ]
            );
        } elseif ($action === 'graduated') {
            // Jika lulus, ubah status student dan nonaktifkan akun siswa.
            $student->update(['status' => 'graduated']);

            if ($student->user) {
                $student->user->update(['is_active' => false]);
            }

            // Nonaktifkan akun WALI hanya jika ia tidak lagi menaungi siswa aktif
            // lain (Audit 3.2). Satu wali bisa memantau beberapa anak/saudara;
            // menonaktifkannya saat satu anak lulus akan memutus akses ke adik
            // yang masih bersekolah.
            if ($student->guardian_user_id) {
                $guardian = User::find($student->guardian_user_id);

                $stillHasActiveChild = $guardian
                    ? $guardian->guardianStudents()
                        ->where('status', 'active')
                        ->where('id', '!=', $student->id)
                        ->exists()
                    : false;

                if ($guardian && ! $stillHasActiveChild) {
                    $guardian->update(['is_active' => false]);
                }
            }
        }
    }
}
