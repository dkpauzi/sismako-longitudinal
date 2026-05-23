<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class PromotionService
{
    /**
     * Memproses kenaikan kelas untuk daftar siswa secara massal
     * 
     * @param array $promotions Data promosi berisi id enrollment lama dan target kelas baru (atau status).
     * Format yang diharapkan:
     * [
     *     [
     *         'enrollment_id' => 10,
     *         'action' => 'promoted', // 'promoted', 'retained', 'graduated', 'moved', 'dropped'
     *         'target_classroom_id' => 5 // Wajib jika promoted atau retained
     *     ],
     *     ...
     * ]
     * @param int $targetAcademicPeriodId ID Tahun Ajaran Tujuan (wajib jika ada yang promoted/retained)
     * 
     * @return array [success => bool, message => string, processed => int]
     */
    public function processBatchPromotions(array $promotions, ?int $targetAcademicPeriodId = null): array
    {
        try {
            return DB::transaction(function () use ($promotions, $targetAcademicPeriodId) {
                $processedCount = 0;

                foreach ($promotions as $item) {
                    $enrollmentId = $item['enrollment_id'];
                    $action = $item['action'];
                    $targetClassroomId = $item['target_classroom_id'] ?? null;

                    $oldEnrollment = Enrollment::with('student.user')->find($enrollmentId);
                    if (!$oldEnrollment) continue;

                    // Update status enrollment lama
                    $oldEnrollment->update(['status' => $action]);

                    $student = $oldEnrollment->student;

                    if ($action === 'promoted' || $action === 'retained') {
                        if (!$targetAcademicPeriodId || !$targetClassroomId) {
                            throw new Exception("Tahun Ajaran Tujuan dan Kelas Tujuan wajib diisi untuk siswa yang Naik/Tinggal Kelas.");
                        }

                        // Buat enrollment baru di periode dan kelas tujuan
                        Enrollment::updateOrCreate(
                            [
                                'student_id' => $student->id,
                                'academic_period_id' => $targetAcademicPeriodId
                            ],
                            [
                                'classroom_id' => $targetClassroomId,
                                'status' => 'active',
                                'promoted_from_enrollment_id' => $oldEnrollment->id
                            ]
                        );
                    } elseif ($action === 'graduated') {
                        // Jika lulus, ubah status student dan nonaktifkan user
                        $student->update(['status' => 'graduated']);
                        
                        if ($student->user) {
                            $student->user->update(['is_active' => false]);
                        }
                        
                        if ($student->guardian_user_id) {
                            $guardian = User::find($student->guardian_user_id);
                            if ($guardian) {
                                $guardian->update(['is_active' => false]);
                            }
                        }
                    }

                    $processedCount++;
                }

                return [
                    'success' => true,
                    'message' => "$processedCount siswa berhasil diproses.",
                    'processed' => $processedCount
                ];
            });
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Terjadi kesalahan: " . $e->getMessage(),
                'processed' => 0
            ];
        }
    }
}
