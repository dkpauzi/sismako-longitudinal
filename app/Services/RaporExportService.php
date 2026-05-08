<?php
// app/Services/RaporExportService.php

namespace App\Services;

use App\Models\AttendanceSummary;
use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Models\KokurikulerGrade;
use App\Models\SchoolSetting;
use App\Models\TeachingAssignment;
use App\Services\SchoolIdentityService;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Table as TableStyle;

class RaporExportService
{
    // Warna sesuai format Kemendikdasmen
    const COLOR_HEADER = '4472C4'; // Biru header tabel
    const COLOR_HEADER_TXT = 'FFFFFF';
    const COLOR_ROW_ALT = 'EBF3FB'; // Baris alternating
    const COLOR_BORDER = 'BFBFBF';

    private SchoolSetting $setting;
    private SchoolIdentityService $schoolIdentity;

    public function __construct()
    {
        $this->setting = SchoolSetting::first() ?? new SchoolSetting();
        $this->schoolIdentity = app(SchoolIdentityService::class);
    }

    /**
     * Export rapor satu siswa menjadi file Word.
     */
    public function exportSingleStudent(
        ClassHomeroom $homeroom,
        int $studentId
    ): string {
        $enrollment = Enrollment::where('classroom_id', $homeroom->classroom_id)
            ->where('academic_period_id', $homeroom->academic_period_id)
            ->where('student_id', $studentId)
            ->with('student')
            ->firstOrFail();

        $phpWord = $this->createDocument();
        $section = $phpWord->addSection($this->getSectionStyle($homeroom));

        $this->addRaporContent($section, $homeroom, $enrollment);

        return $this->saveToTemp($phpWord, "rapor_{$enrollment->student->nisn}");
    }

    /**
     * Export rapor seluruh kelas menjadi satu file Word.
     * Setiap siswa dipisah dengan page break.
     */
    public function exportWholeClass(ClassHomeroom $homeroom): string
    {
        $enrollments = Enrollment::where('classroom_id', $homeroom->classroom_id)
            ->where('academic_period_id', $homeroom->academic_period_id)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.name');

        // ✅ PERBAIKAN N+1: Pre-load semua data bersama sebelum looping per siswa.
        // Sebelumnya addRaporContent() menjalankan 3+ query per siswa.
        $studentIds = $enrollments->pluck('student_id');
        $classroomId = $homeroom->classroom_id;
        $periodId = $homeroom->academic_period_id;
        $semester = $homeroom->academicPeriod->semester;

        // Batch load: semua teaching assignments + final grades
        $allAssignments = TeachingAssignment::where('classroom_id', $classroomId)
            ->where('academic_period_id', $periodId)
            ->with(['subject', 'finalGrades' => fn($q) => $q->whereIn('student_id', $studentIds)->where('semester', $semester)])
            ->get();

        // Batch load: semua kokurikuler grades
        $allKokurikulerGrades = KokurikulerGrade::whereIn('student_id', $studentIds)
            ->whereHas('teachingAssignment', fn($q) => $q->where('classroom_id', $classroomId)->where('academic_period_id', $periodId))
            ->with('teachingAssignment.subject')
            ->get()
            ->keyBy('student_id');

        // Batch load: semua attendance summaries
        $allAttendanceSummaries = AttendanceSummary::whereIn('student_id', $studentIds)
            ->whereIn('teaching_assignment_id', $allAssignments->pluck('id'))
            ->where('semester', $semester)
            ->get()
            ->groupBy('student_id');

        $preloadedData = [
            'assignments' => $allAssignments,
            'kokurikulerGrades' => $allKokurikulerGrades,
            'attendanceSummaries' => $allAttendanceSummaries,
        ];

        $phpWord = $this->createDocument();
        $section = $phpWord->addSection($this->getSectionStyle($homeroom));
        $isFirst = true;

        foreach ($enrollments as $enrollment) {
            if (!$isFirst) {
                $section->addPageBreak();
            }
            $this->addRaporContent($section, $homeroom, $enrollment, $preloadedData);
            $isFirst = false;
        }

        $className = $homeroom->classroom->name;
        return $this->saveToTemp($phpWord, "rapor_kelas_{$className}");
    }

    // ─── PRIVATE HELPERS ────────────────────────────────────────────────────

    private function createDocument(): PhpWord
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);

        // Register styles
        $phpWord->addTableStyle('RaporTable', [
            'borderSize' => 6,
            'borderColor' => self::COLOR_BORDER,
            'cellMargin' => 80,
        ]);

        return $phpWord;
    }

    private function getSectionStyle(ClassHomeroom $homeroom): array
    {
        $gradeLevel = $homeroom->classroom->grade_level;

        return [
            'paperSize' => 'A4',
            'marginLeft' => 1134, // ~2cm dalam twips
            'marginRight' => 1134,
            'marginTop' => 1134,
            'marginBottom' => 1134,
            'orientation' => 'portrait',
        ];
    }

    /**
     * Inti: generate satu halaman rapor untuk satu siswa.
     * Format disesuaikan berdasarkan grade_level.
     */
    private function addRaporContent(
        $section,
        ClassHomeroom $homeroom,
        Enrollment $enrollment,
        ?array $preloadedData = null
    ): void {
        $student = $enrollment->student;
        $classroom = $homeroom->classroom;
        $period = $homeroom->academicPeriod;
        $gradeLevel = $classroom->grade_level;
        $semester = $period->semester;

        // Tentukan format berdasarkan jenjang
        $isSMP = $gradeLevel >= 7 && $gradeLevel <= 9;
        $isSMA = $gradeLevel >= 10 && $gradeLevel <= 12;
        $isSD = $gradeLevel >= 1 && $gradeLevel <= 6;

        // ✅ PERBAIKAN N+1: Gunakan preloaded data jika tersedia (dari exportWholeClass).
        // Jika null (dari exportSingleStudent), fallback ke query individual.
        if ($preloadedData) {
            $assignments = $preloadedData['assignments'];
            $kokurikulerGrade = $preloadedData['kokurikulerGrades'][$student->id] ?? null;
            $absenceSummary = $preloadedData['attendanceSummaries'][$student->id] ?? collect();
        } else {
            // Fallback: query per siswa (untuk export single student)
            $assignments = $this->getAssignmentsForStudent(
                classroomId: $classroom->id,
                academicPeriodId: $period->id,
                studentId: $student->id,
                semester: $semester,
            );

            $kokurikulerGrade = KokurikulerGrade::whereHas(
                'teachingAssignment',
                fn($q) =>
                $q->where('classroom_id', $classroom->id)
                    ->where('academic_period_id', $period->id)
            )
                ->where('student_id', $student->id)
                ->with('teachingAssignment.subject')
                ->first();

            $absenceSummary = AttendanceSummary::where('student_id', $student->id)
                ->whereIn(
                    'teaching_assignment_id',
                    $assignments->pluck('id')->merge(
                        TeachingAssignment::where('classroom_id', $classroom->id)
                            ->where('academic_period_id', $period->id)
                            ->pluck('id')
                    )->unique()
                )
                ->where('semester', $semester)
                ->get();
        }

        $totalSakit = $absenceSummary->sum('sick');
        $totalIzin = $absenceSummary->sum('permit');
        $totalAlpha = $absenceSummary->sum('alpha');

        // ── 1. JUDUL RAPOR ──────────────────────────────────────────────
        $this->addTitle($section, $gradeLevel);

        // ── 2. IDENTITAS SISWA ───────────────────────────────────────────
        $this->addStudentIdentity($section, $student, $classroom, $period);

        $section->addTextBreak(1);

        // ── 3. TABEL NILAI ───────────────────────────────────────────────
        if ($isSD) {
            $this->addSDGradeTable($section, $assignments, $student, $semester, $gradeLevel);
        } else {
            // SMP & SMA menggunakan format yang sama
            $this->addSMPGradeTable($section, $assignments, $student, $semester);
        }

        $section->addTextBreak(1);

        // ── 4. KOKURIKULER ───────────────────────────────────────────────
        $this->addKokurikulerSection($section, $kokurikulerGrade);

        $section->addTextBreak(1);

        // ── 5. EKSTRAKURIKULER ───────────────────────────────────────────
        $this->addExtracurricularSection($section, $student);

        $section->addTextBreak(1);

        // ── 6. KETIDAKHADIRAN + CATATAN WALI KELAS ──────────────────────
        $this->addAbsenceAndNotes($section, $totalSakit, $totalIzin, $totalAlpha);

        $section->addTextBreak(1);

        // ── 7. TANGGAPAN ORANG TUA ───────────────────────────────────────
        $this->addParentResponse($section);

        $section->addTextBreak(1);

        // ── 8. TTD PENUTUP ───────────────────────────────────────────────
        $this->addSignatureRow($section, $homeroom, $period);
    }

    // ─── SECTION BUILDERS ───────────────────────────────────────────────────

    private function addTitle($section, int $gradeLevel): void
    {
        $jenjang = match (true) {
            $gradeLevel <= 6 => 'SD',
            $gradeLevel <= 9 => 'SMP',
            default => 'SMA',
        };

        $section->addText(
            'LAPORAN HASIL BELAJAR',
            ['bold' => true, 'size' => 14, 'name' => 'Arial'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addText(
            "Jenjang {$jenjang}",
            ['bold' => true, 'size' => 12, 'name' => 'Arial'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addText(
            $this->schoolIdentity->schoolName(),
            ['size' => 10, 'name' => 'Arial'],
            [
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                'spaceAfter' => 120
            ]
        );
    }

    private function addStudentIdentity($section, $student, $classroom, $period): void
    {
        $semLabel = $period->semester === 'odd' ? 'Ganjil' : 'Genap';
        $faseLabel = $this->getFaseLabel($classroom->grade_level);

        // Tabel 2 kolom untuk identitas
        $table = $section->addTable([
            'borderSize' => 0,
            'cellMargin' => 60,
        ]);

        $identityLeft = [
            ['label' => 'Nama Murid', 'value' => $student->name],
            ['label' => 'NISN', 'value' => $student->nisn ?? '-'],
            ['label' => 'Sekolah', 'value' => $this->schoolIdentity->schoolName()],
            ['label' => 'Alamat', 'value' => $student->address ?? '-'],
        ];

        $identityRight = [
            ['label' => 'Kelas', 'value' => $classroom->name],
            ['label' => 'Fase', 'value' => $faseLabel],
            ['label' => 'Semester', 'value' => $semLabel],
            ['label' => 'Tahun Ajaran', 'value' => $period->name],
        ];

        $maxRows = max(count($identityLeft), count($identityRight));

        for ($i = 0; $i < $maxRows; $i++) {
            $row = $table->addRow();

            // Kolom kiri
            $left = $identityLeft[$i] ?? null;
            $cell = $row->addCell(3800, ['borderSize' => 0]);
            if ($left) {
                $cell->addText(
                    "{$left['label']}  :  {$left['value']}",
                    ['size' => 10]
                );
            }

            // Spacer
            $row->addCell(500, ['borderSize' => 0])->addText('');

            // Kolom kanan
            $right = $identityRight[$i] ?? null;
            $cell = $row->addCell(3800, ['borderSize' => 0]);
            if ($right) {
                $cell->addText(
                    "{$right['label']}  :  {$right['value']}",
                    ['size' => 10]
                );
            }
        }
    }

    private function addSMPGradeTable($section, $assignments, $student, string $semester): void
    {
        // Header
        $section->addText('', [], ['spaceBefore' => 60, 'spaceAfter' => 60]);

        $table = $section->addTable('RaporTable');

        // Header Row
        $headerRow = $table->addRow(400);
        $headerStyle = [
            'bgColor' => self::COLOR_HEADER,
            'borderSize' => 6,
            'borderColor' => self::COLOR_BORDER
        ];
        $headerFont = [
            'bold' => true,
            'color' => self::COLOR_HEADER_TXT,
            'size' => 10,
            'name' => 'Arial'
        ];
        $centerPara = [
            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            'spaceAfter' => 0
        ];

        $headerRow->addCell(400, $headerStyle)
            ->addText('No', $headerFont, $centerPara);
        $headerRow->addCell(3200, $headerStyle)
            ->addText('Mata Pelajaran', $headerFont, $centerPara);
        $headerRow->addCell(1000, $headerStyle)
            ->addText('Nilai Akhir', $headerFont, $centerPara);
        $headerRow->addCell(4000, $headerStyle)
            ->addText('Capaian Kompetensi', $headerFont, $centerPara);

        // Data Rows
        foreach ($assignments as $index => $assignment) {
            $finalGrade = $assignment->finalGrades->first();
            $score = $finalGrade?->final_score;
            $narrative = $finalGrade?->narrative_description ?? '';

            $rowBg = ($index % 2 === 1)
                ? ['bgColor' => self::COLOR_ROW_ALT]
                : [];

            $borderStyle = array_merge(
                ['borderSize' => 6, 'borderColor' => self::COLOR_BORDER],
                $rowBg
            );

            $row = $table->addRow();
            $row->addCell(400, $borderStyle)
                ->addText((string) ($index + 1), ['size' => 10], $centerPara);
            $row->addCell(3200, $borderStyle)
                ->addText($assignment->subject->name, ['size' => 10]);
            $row->addCell(1000, $borderStyle)
                ->addText($score ? (string) $score : '', ['size' => 10], $centerPara);
            $row->addCell(4000, $borderStyle)
                ->addText($narrative, ['size' => 10]);
        }

        // Baris kosong jika kurang dari 10
        $currentCount = $assignments->count();
        $minRows = 10;
        for ($i = $currentCount; $i < $minRows; $i++) {
            $rowBg = ($i % 2 === 1)
                ? ['bgColor' => self::COLOR_ROW_ALT]
                : [];
            $borderStyle = array_merge(
                ['borderSize' => 6, 'borderColor' => self::COLOR_BORDER],
                $rowBg
            );
            $row = $table->addRow(300);
            $row->addCell(400, $borderStyle)->addText(($i + 1) === $minRows ? 'dst.' : '', ['size' => 10]);
            $row->addCell(3200, $borderStyle)->addText('');
            $row->addCell(1000, $borderStyle)->addText('');
            $row->addCell(4000, $borderStyle)->addText('');
        }
    }

    private function addSDGradeTable(
        $section,
        $assignments,
        $student,
        string $semester,
        int $gradeLevel
    ): void {
        $showScore = $this->setting->show_score_sd ?? true;

        // SD Fase A (1-2): tanpa nilai angka berdasarkan setting
        // SD Fase B-C (3-6): dengan nilai angka
        // Untuk saat ini gunakan format dengan angka (setting akan diimplementasi di SchoolSettingResource)
        $showScore = ($gradeLevel >= 3); // Fase A default tanpa angka

        $table = $section->addTable('RaporTable');
        $headerStyle = [
            'bgColor' => self::COLOR_HEADER,
            'borderSize' => 6,
            'borderColor' => self::COLOR_BORDER
        ];
        $headerFont = [
            'bold' => true,
            'color' => self::COLOR_HEADER_TXT,
            'size' => 10
        ];
        $centerPara = [
            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            'spaceAfter' => 0
        ];

        $headerRow = $table->addRow(400);
        $headerRow->addCell(400, $headerStyle)->addText('No', $headerFont, $centerPara);
        $headerRow->addCell(3200, $headerStyle)->addText('Mata Pelajaran', $headerFont, $centerPara);
        if ($showScore) {
            $headerRow->addCell(1000, $headerStyle)->addText('Nilai Akhir', $headerFont, $centerPara);
            $headerRow->addCell(4000, $headerStyle)->addText('Capaian Kompetensi', $headerFont, $centerPara);
        } else {
            $headerRow->addCell(5000, $headerStyle)->addText('Capaian Kompetensi', $headerFont, $centerPara);
        }

        foreach ($assignments as $index => $assignment) {
            $finalGrade = $assignment->finalGrades->first();
            $score = $finalGrade?->final_score;
            $narrative = $finalGrade?->narrative_description ?? '';

            $rowBg = ($index % 2 === 1) ? ['bgColor' => self::COLOR_ROW_ALT] : [];
            $bs = array_merge(['borderSize' => 6, 'borderColor' => self::COLOR_BORDER], $rowBg);

            $row = $table->addRow();
            $row->addCell(400, $bs)->addText((string) ($index + 1), ['size' => 10], $centerPara);
            $row->addCell(3200, $bs)->addText($assignment->subject->name, ['size' => 10]);
            if ($showScore) {
                $row->addCell(1000, $bs)->addText($score ? (string) $score : '', ['size' => 10], $centerPara);
                $row->addCell(4000, $bs)->addText($narrative, ['size' => 10]);
            } else {
                $row->addCell(5000, $bs)->addText($narrative, ['size' => 10]);
            }
        }
    }
    // 2. Tambah method baru untuk query mapel SMA
    private function getAssignmentsForStudent(
        int $classroomId,
        int $academicPeriodId,
        int $studentId,
        string $semester
    ): \Illuminate\Support\Collection {
        // Ambil SEMUA teaching assignments di kelas ini
        $allAssignments = TeachingAssignment::where('classroom_id', $classroomId)
            ->where('academic_period_id', $academicPeriodId)
            ->with([
                'subject',
                'finalGrades' => fn($q) => $q
                    ->where('student_id', $studentId)
                    ->where('semester', $semester)
            ])
            ->get();

        // Ambil daftar elective yang diikuti siswa ini
        $enrolledElectiveIds = \App\Models\StudentSubjectEnrollment::where('student_id', $studentId)
            ->pluck('teaching_assignment_id')
            ->toArray();

        // Filter berdasarkan effective type
        return $allAssignments->filter(function (TeachingAssignment $ta) use ($enrolledElectiveIds) {
            $effectiveType = $ta->getEffectiveType();

            return match ($effectiveType) {
                // Auto-enroll: tampilkan ke semua siswa
                'mandatory', 'kokurikuler' => true,
                // Manual-enroll: hanya jika siswa terdaftar
                'elective', 'extracurricular' => in_array($ta->id, $enrolledElectiveIds),
                default => true,
            };
        })
            // Kokurikuler ditampilkan terpisah, bukan di tabel nilai
            ->filter(fn($ta) => $ta->getEffectiveType() !== 'kokurikuler')
            ->sortBy('subject.name')
            ->values();
    }

    private function addKokurikulerSection($section, $kokurikulerGrade): void
    {
        $table = $section->addTable('RaporTable');

        // Header
        $row = $table->addRow(300);
        $row->addCell(8600, [
            'bgColor' => self::COLOR_HEADER,
            'borderSize' => 6,
            'borderColor' => self::COLOR_BORDER,
            'gridSpan' => 1,
        ])->addText(
                'Kokurikuler',
                ['bold' => true, 'color' => self::COLOR_HEADER_TXT, 'size' => 10],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
            );

        // Isi
        $row = $table->addRow(500);
        $row->addCell(8600, ['borderSize' => 6, 'borderColor' => self::COLOR_BORDER])
            ->addText(
                $kokurikulerGrade?->description ?? '',
                ['size' => 10]
            );
    }

    private function addExtracurricularSection($section, $student): void
    {
        $table = $section->addTable('RaporTable');
        $hStyle = [
            'bgColor' => self::COLOR_HEADER,
            'borderSize' => 6,
            'borderColor' => self::COLOR_BORDER
        ];
        $hFont = ['bold' => true, 'color' => self::COLOR_HEADER_TXT, 'size' => 10];
        $center = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0];
        $bStyle = ['borderSize' => 6, 'borderColor' => self::COLOR_BORDER];

        $headerRow = $table->addRow(300);
        $headerRow->addCell(400, $hStyle)->addText('No', $hFont, $center);
        $headerRow->addCell(3200, $hStyle)->addText('Ekstrakurikuler', $hFont, $center);
        $headerRow->addCell(5000, $hStyle)->addText('Keterangan', $hFont, $center);

        // Ambil data ekskul siswa
        $extracurriculars = $student->extracurriculars ?? collect();

        if ($extracurriculars->isNotEmpty()) {
            foreach ($extracurriculars as $i => $ekskul) {
                $row = $table->addRow();
                $row->addCell(400, $bStyle)->addText((string) ($i + 1), ['size' => 10], $center);
                $row->addCell(3200, $bStyle)->addText($ekskul->name, ['size' => 10]);
                $row->addCell(5000, $bStyle)->addText($ekskul->pivot->description ?? '', ['size' => 10]);
            }
        }

        // Minimal 2 baris kosong
        $current = $extracurriculars->count();
        for ($i = $current; $i < max(2, $current); $i++) {
            $row = $table->addRow(300);
            $row->addCell(400, $bStyle)->addText($i === 0 ? '1' : ($i + 1 <= 2 ? (string) ($i + 1) : 'dst.'), ['size' => 10]);
            $row->addCell(3200, $bStyle)->addText('');
            $row->addCell(5000, $bStyle)->addText('');
        }

        // Baris "dst."
        $dstRow = $table->addRow(300);
        $dstRow->addCell(400, $bStyle)->addText('dst.', ['size' => 10]);
        $dstRow->addCell(3200, $bStyle)->addText('');
        $dstRow->addCell(5000, $bStyle)->addText('');
    }

    private function addAbsenceAndNotes($section, int $sakit, int $izin, int $alpha): void
    {
        // Dua tabel berdampingan: Ketidakhadiran | Catatan Wali Kelas
        $table = $section->addTable([
            'borderSize' => 0,
            'cellMargin' => 0,
        ]);
        $bStyle = ['borderSize' => 6, 'borderColor' => self::COLOR_BORDER];
        $hStyle = array_merge($bStyle, ['bgColor' => self::COLOR_HEADER]);
        $hFont = ['bold' => true, 'color' => self::COLOR_HEADER_TXT, 'size' => 10];
        $center = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 0];

        // Baris 1: Header kiri + Header kanan
        $row = $table->addRow(300);
        $row->addCell(1600, $hStyle)
            ->addText('Ketidakhadiran', $hFont, $center);
        $row->addCell(2400, $hStyle)
            ->addText('', $hFont); // kolom nilai
        $row->addCell(600, ['borderSize' => 0])
            ->addText(''); // spacer
        $row->addCell(4000, $hStyle)
            ->addText('Catatan Wali Kelas', $hFont, $center);

        // Baris 2: Sakit + (rowspan catatan)
        $this->addAbsenceRow($table, 'Sakit', "{$sakit} hari", $bStyle);
        $this->addAbsenceRow($table, 'Izin', "{$izin} hari", $bStyle);
        $this->addAbsenceRow($table, 'Tanpa Keterangan', "{$alpha} hari", $bStyle);
    }

    private function addAbsenceRow($table, string $label, string $value, array $bStyle): void
    {
        $row = $table->addRow(280);
        $row->addCell(1600, $bStyle)->addText($label, ['size' => 10]);
        $row->addCell(2400, $bStyle)->addText($value, ['size' => 10]);
        $row->addCell(600, ['borderSize' => 0])->addText('');
        $row->addCell(4000, $bStyle)->addText('');
    }

    private function addParentResponse($section): void
    {
        $table = $section->addTable('RaporTable');
        $hStyle = [
            'bgColor' => self::COLOR_HEADER,
            'borderSize' => 6,
            'borderColor' => self::COLOR_BORDER
        ];

        $table->addRow(300)
            ->addCell(8600, $hStyle)
            ->addText(
                'Tanggapan Orang Tua / Wali Murid',
                ['bold' => true, 'color' => self::COLOR_HEADER_TXT, 'size' => 10],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
            );

        $table->addRow(800)
            ->addCell(8600, ['borderSize' => 6, 'borderColor' => self::COLOR_BORDER])
            ->addText('');
    }

    private function addSignatureRow($section, ClassHomeroom $homeroom, $period): void
    {
        $section->addTextBreak(1);

        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 80]);

        $row = $table->addRow();

        // Orang Tua
        $row->addCell(2800, ['borderSize' => 0])
            ->addText(
                'Orang Tua / Wali Murid',
                ['size' => 10],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
            );

        // Kepala Sekolah
        $row->addCell(2800, ['borderSize' => 0])
            ->addText(
                'Kepala Sekolah',
                ['size' => 10],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
            );

        // Wali Kelas — dengan tanggal
        $dateStr = now()->translatedFormat('d F Y');
        $city = explode(',', $this->schoolIdentity->schoolAddress() ?? '')[0] ?? 'Sijunjung';
        $cell = $row->addCell(3000, ['borderSize' => 0]);
        $cell->addText(
            "Tempat, Tanggal rapor",
            ['size' => 9, 'color' => '666666'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $cell->addText(
            "Wali Kelas",
            ['size' => 10],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );

        // Baris TTD (spasi tanda tangan)
        $ttdRow = $table->addRow(1200);
        $ttdRow->addCell(2800, ['borderSize' => 0])
            ->addText(
                'TTD',
                ['bold' => true, 'size' => 10],
                [
                    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                    'spaceAfter' => 0,
                    'spaceBefore' => 900
                ]
            );
        $ttdRow->addCell(2800, ['borderSize' => 0])
            ->addText(
                'TTD',
                ['bold' => true, 'size' => 10],
                [
                    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                    'spaceAfter' => 0,
                    'spaceBefore' => 900
                ]
            );

        $waliCell = $ttdRow->addCell(3000, ['borderSize' => 0]);
        $waliCell->addText(
            $homeroom->teacher->name ?? '',
            ['bold' => true, 'size' => 10],
            [
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
                'spaceAfter' => 0,
                'spaceBefore' => 900
            ]
        );
    }

    private function getFaseLabel(int $gradeLevel): string
    {
        return match (true) {
            $gradeLevel <= 2 => 'Fase A',
            $gradeLevel <= 4 => 'Fase B',
            $gradeLevel <= 6 => 'Fase C',
            $gradeLevel <= 9 => 'Fase D',
            $gradeLevel <= 11 => 'Fase E',
            default => 'Fase F',
        };
    }

    private function saveToTemp(PhpWord $phpWord, string $filename): string
    {
        // ✅ PERBAIKAN KEAMANAN: Sanitasi nama file untuk mencegah path traversal.
        // Contoh berbahaya: "../../../etc/passwd" → "________etc_passwd"
        $safeFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
        $path = storage_path('app/temp/' . $safeFilename . '_' . time() . '.docx');

        // Pastikan folder temp ada
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);

        // Bersihkan file temp lama (> 1 jam) untuk mencegah penumpukan
        $this->cleanOldTempFiles(dirname($path));

        return $path;
    }

    /**
     * Bersihkan file .docx di folder temp yang sudah lebih dari 1 jam.
     * Dipanggil setiap kali file baru dibuat untuk self-maintenance.
     */
    private function cleanOldTempFiles(string $dir): void
    {
        foreach (glob($dir . '/*.docx') as $file) {
            if (filemtime($file) < now()->subHour()->timestamp) {
                @unlink($file);
            }
        }
    }
}