<?php

namespace App\Http\Controllers;

use App\Models\ClassHomeroom;
use App\Models\Student;
use App\Models\StudentReport;
use App\Services\RaporExportService;
use Illuminate\Http\Request;

class RaporPrintController extends Controller
{
    public function show(ClassHomeroom $homeroom, Student $student)
    {
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
}
