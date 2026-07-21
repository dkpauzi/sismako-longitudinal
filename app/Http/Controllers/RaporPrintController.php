<?php

namespace App\Http\Controllers;

use App\Models\ClassHomeroom;
use App\Models\Student;
use App\Models\StudentReport;
use App\Models\User;
use App\Services\RaporExportService;
use Illuminate\Http\Request;

class RaporPrintController extends Controller
{
    public function show(ClassHomeroom $homeroom, Student $student)
    {
        // GERBANG IDOR: rute ini hanya di balik middleware `auth` — tanpa cek ini,
        // siapa pun yang login bisa mencetak rapor siswa lain dengan menukar id di
        // URL. Cek otorisasi SEBELUM mengambil data apa pun (fail-fast 403).
        abort_unless(self::canAccess(auth()->user(), $homeroom, $student), 403);

        // 1. Re-use data fetching dari RaporExportService
        $service = new RaporExportService();
        $data = $service->getRaporData($homeroom, $student->id);
        
        // 2. Override absensi & catatan dari StudentReport (jika ada)
        $report = StudentReport::where('student_id', $student->id)
            ->where('academic_period_id', $homeroom->academic_period_id)
            ->first();
            
        if ($report) {
            $data['totalSakit'] = $report->sick_days;
            $data['totalIzin'] = $report->excused_days;
            $data['totalAlpha'] = $report->unexcused_days;
            $data['homeroomNotes'] = $report->homeroom_notes;
        } else {
            $data['homeroomNotes'] = null;
        }
        
        return view('rapor.print', $data);
    }

    /**
     * Siapa yang boleh mencetak rapor siswa $student pada kelas-wali $homeroom?
     *  - Staf pemantau (super_admin/admin/headmaster/guru_bk): semua.
     *  - Wali kelas dari $homeroom tersebut (teacher_id cocok).
     *  - Siswa ybs sendiri.
     *  - Wali (guardian) dari siswa ybs.
     * Guru mata pelajaran non-wali & akun lain DITOLAK (menutup IDOR).
     * Publik (static) agar bisa diuji unit tanpa merender blade.
     */
    public static function canAccess(?User $user, ClassHomeroom $homeroom, Student $student): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'admin', 'headmaster', 'guru_bk'])) {
            return true;
        }

        if ($user->hasRole('teacher') && $user->teacher && (int) $homeroom->teacher_id === (int) $user->teacher->id) {
            return true;
        }

        if ($user->hasRole('student') && $user->student && (int) $user->student->id === (int) $student->id) {
            return true;
        }

        if ($user->hasRole('guardian') && (int) $student->guardian_user_id === (int) $user->id) {
            return true;
        }

        return false;
    }
}
