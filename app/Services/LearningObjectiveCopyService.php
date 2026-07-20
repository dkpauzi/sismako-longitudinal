<?php

namespace App\Services;

use App\Models\LearningObjective;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * SALIN TP ANTAR-PERIODE — mekanisme bulk-copy Tujuan Pembelajaran.
 *
 * TP terikat academic_period_id (SRS §4.1/§4.5), sehingga tiap semester guru
 * menghadapi "blank slate". Service ini mereplikasi TP dari periode SUMBER ke
 * periode TUJUAN dengan:
 *  - Idempotensi: TP dengan (subject_id, code) sama di periode tujuan DILEWATI
 *    (tidak diduplikasi, tidak ditimpa — menjaga editan tujuan yang sudah ada).
 *  - Scope RBAC: pemanggil memasok daftar subject yang diizinkan (null = semua,
 *    untuk admin). Guru dibatasi ke mapel yang ia ampu di periode TUJUAN.
 */
class LearningObjectiveCopyService
{
    /**
     * Daftar subject_id yang boleh disalin oleh $user ke $targetPeriodId.
     * - super_admin/admin → null (SEMUA subject).
     * - teacher → hanya subject yang ia ampu (teaching_assignments) di periode TUJUAN.
     * - lainnya / guru tanpa profil → [] (tidak ada).
     *
     * @return array<int>|null null berarti "semua subject" (tanpa batasan).
     */
    public function allowedSubjectIdsFor(User $user, int $targetPeriodId): ?array
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return null; // tanpa batasan
        }

        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            return [];
        }

        return TeachingAssignment::where('teacher_id', $teacherId)
            ->where('academic_period_id', $targetPeriodId)
            ->pluck('subject_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Salin TP dari periode sumber ke tujuan.
     *
     * @param  array<int>|null  $allowedSubjectIds  null = semua subject; array = batasi;
     *                                              array kosong = tidak ada yang boleh.
     * @return array{copied:int,skipped:int}
     *
     * @throws \InvalidArgumentException bila periode sumber == tujuan.
     */
    public function copy(int $sourcePeriodId, int $targetPeriodId, ?array $allowedSubjectIds = null): array
    {
        if ($sourcePeriodId === $targetPeriodId) {
            throw new \InvalidArgumentException('Periode sumber dan tujuan tidak boleh sama.');
        }

        $query = LearningObjective::where('academic_period_id', $sourcePeriodId);

        // Scope RBAC: batasi ke subject yang diizinkan (bila bukan admin).
        if (is_array($allowedSubjectIds)) {
            if (empty($allowedSubjectIds)) {
                return ['copied' => 0, 'skipped' => 0]; // tidak ada subject → tidak ada kerja
            }
            $query->whereIn('subject_id', $allowedSubjectIds);
        }

        $copied = 0;
        $skipped = 0;

        DB::transaction(function () use ($query, $targetPeriodId, &$copied, &$skipped) {
            foreach ($query->cursor() as $source) {
                if ($this->existsInTarget($source, $targetPeriodId)) {
                    $skipped++;
                    continue;
                }

                LearningObjective::create([
                    'teacher_id' => $source->teacher_id,
                    'subject_id' => $source->subject_id,
                    'academic_period_id' => $targetPeriodId,
                    'grade_level' => $source->grade_level,
                    'phase' => $source->phase,
                    'code' => $source->code,
                    'content' => $source->content,
                    'attribute' => $source->attribute,
                ]);
                $copied++;
            }
        });

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /**
     * Guard idempotensi: apakah TP setara sudah ada di periode tujuan?
     * Kunci utama = (subject_id, code). Bila code kosong (importer mengizinkan),
     * fallback ke (subject_id, content) agar tetap tidak menduplikasi.
     */
    private function existsInTarget(LearningObjective $source, int $targetPeriodId): bool
    {
        return LearningObjective::where('academic_period_id', $targetPeriodId)
            ->where('subject_id', $source->subject_id)
            ->when(
                filled($source->code),
                fn($q) => $q->where('code', $source->code),
                fn($q) => $q->whereNull('code')->where('content', $source->content),
            )
            ->exists();
    }
}
