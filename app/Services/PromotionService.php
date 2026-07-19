<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FinalGrade;
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

        $student = $oldEnrollment->student;

        // ── GUARD KUNCI RAPOR (Item 3.1) — rapor periode asal wajib terkunci ──
        $this->assertReportLocked($oldEnrollment, $student);

        // ── GUARD TEMPORAL (Item 3.2) — transisi hanya di batas tahun (Genap) ──
        $this->assertYearEndTransition($oldEnrollment, $targetAcademicPeriodId, $action);

        // Update status enrollment lama (setelah lolos semua guard)
        $oldEnrollment->update(['status' => $action]);

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

    /**
     * GUARD: rapor periode ASAL wajib terkunci sebelum transisi (Item 3.1).
     * Defensif — hanya final grade yang SUDAH ADA dan belum dikunci yang
     * memblokir; mapel (mis. Muatan Lokal/elektif) tanpa baris nilai TIDAK
     * menjebak siswa. Menjaga snapshot longitudinal ter-freeze (SRS §1/§4.8).
     *
     * @throws Exception bila ada final grade belum terkunci di periode asal.
     */
    private function assertReportLocked(Enrollment $oldEnrollment, ?Student $student): void
    {
        if (!$student) return;

        $hasUnlocked = FinalGrade::where('student_id', $student->id)
            ->whereHas('teachingAssignment', fn($q) => $q
                ->where('academic_period_id', $oldEnrollment->academic_period_id))
            ->where('is_locked', false)
            ->exists();

        if ($hasUnlocked) {
            throw new Exception(
                "Rapor '{$student->name}' pada periode asal belum dikunci. " .
                "Kunci semua nilai rapor terlebih dahulu sebelum memproses kenaikan/kelulusan."
            );
        }
    }

    /**
     * GUARD TEMPORAL: Naik/Tinggal/Lulus adalah keputusan AKHIR TAHUN (Item 3.2).
     * - Periode asal WAJIB semester Genap ('even'). Transisi Ganjil→Genap adalah
     *   "Lanjut Semester" (jalur terpisah), bukan kenaikan kelas.
     * - Untuk promoted/retained, periode tujuan WAJIB semester Ganjil tahun
     *   berikutnya (target.start_year == source.end_year).
     *
     * @throws Exception bila pasangan periode tidak sah.
     */
    private function assertYearEndTransition(Enrollment $oldEnrollment, ?int $targetAcademicPeriodId, string $action): void
    {
        $source = AcademicPeriod::find($oldEnrollment->academic_period_id);
        if (!$source) return;

        if ($source->semester !== 'even') {
            throw new Exception(
                "Kenaikan/Tinggal/Lulus hanya dapat diproses dari semester GENAP (akhir tahun ajaran). " .
                "Untuk transisi Ganjil→Genap, gunakan menu 'Lanjut Semester'."
            );
        }

        if (in_array($action, ['promoted', 'retained'], true)) {
            $target = $targetAcademicPeriodId ? AcademicPeriod::find($targetAcademicPeriodId) : null;
            if (!$target) return; // kelengkapan target divalidasi di cabang promoted/retained

            $isNextOddYear = $target->semester === 'odd'
                && (int) $target->start_year === (int) $source->end_year;

            if (!$isNextOddYear) {
                throw new Exception(
                    "Tahun Ajaran Tujuan tidak valid untuk Naik/Tinggal Kelas: " .
                    "harus semester GANJIL tahun ajaran berikutnya."
                );
            }
        }
    }

    /**
     * LANJUT SEMESTER (Ganjil→Genap tahun yang sama) — jalur mutasi enrollment
     * TERPISAH dari kenaikan kelas. Siswa TETAP di kelas & tingkat yang sama;
     * hanya enrollment periode Genap yang dibuat, menautkan rantai longitudinal.
     * Diproses per-chunk (event recursion) seperti promosi.
     *
     * @return array{success:bool,message:string,processed:int}
     */
    public function processSemesterChunk(array $chunk, ?int $targetPeriodId): array
    {
        try {
            return DB::transaction(function () use ($chunk, $targetPeriodId) {
                $processed = 0;
                foreach ($chunk as $item) {
                    $this->processSingleContinuation($item, $targetPeriodId);
                    $processed++;
                }
                return [
                    'success' => true,
                    'message' => "{$processed} siswa berhasil dilanjutkan ke semester Genap.",
                    'processed' => $processed,
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
     * Proses satu siswa untuk Lanjut Semester. Tidak membungkus transaction
     * sendiri (pemanggil mengatur boundary), konsisten dg processSinglePromotion.
     *
     * @throws Exception bila pasangan periode bukan Ganjil→Genap tahun yang sama.
     */
    private function processSingleContinuation(array $item, ?int $targetPeriodId): void
    {
        $old = Enrollment::with('student')->find($item['enrollment_id']);
        if (!$old) return;

        $student = $old->student;

        // Rapor Ganjil wajib terkunci sebelum lanjut ke Genap (konsisten Item 3.1).
        $this->assertReportLocked($old, $student);

        $source = AcademicPeriod::find($old->academic_period_id);
        $target = $targetPeriodId ? AcademicPeriod::find($targetPeriodId) : null;

        if (!$source || !$target) {
            throw new Exception("Periode asal/tujuan tidak valid untuk Lanjut Semester.");
        }

        $valid = $source->semester === 'odd'
            && $target->semester === 'even'
            && (int) $source->start_year === (int) $target->start_year
            && (int) $source->end_year === (int) $target->end_year;

        if (!$valid) {
            throw new Exception(
                "Lanjut Semester hanya dari GANJIL ke GENAP pada tahun ajaran yang sama."
            );
        }

        // Kelas & tingkat TETAP; buat enrollment Genap, tautkan rantai longitudinal.
        // Enrollment Ganjil dibiarkan 'active' sebagai catatan semester tersebut
        // (query rapor/roster sudah di-scope per academic_period_id).
        Enrollment::updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_period_id' => $target->id,
            ],
            [
                'classroom_id' => $old->classroom_id,
                'status' => 'active',
                'promoted_from_enrollment_id' => $old->id,
            ]
        );
    }
}
