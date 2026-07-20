<?php
// app/Filament/Resources/RaporResource/Pages/ViewRapor.php

namespace App\Filament\Resources\RaporResource\Pages;

use App\Filament\Resources\RaporResource;
use App\Models\AttendanceSummary;
use App\Models\ClassHomeroom;
use App\Models\Enrollment;
use App\Models\FinalGrade;
use App\Models\StudentReport;
use App\Models\TeachingAssignment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use App\Services\DescriptionGeneratorService;
use App\Services\RaporExportService;

class ViewRapor extends ViewRecord
{
    protected static string $resource = RaporResource::class;

    protected static string $view = 'filament.resources.rapor-resource.pages.view-rapor';
    //protected static string $view = 'filament.pages.view-rapor';

    /**
     * ══════════════════════════════════════════════════════════════
     * STATE CHUNKING GENERATE NARASI (Shared Hosting Safe)
     * ══════════════════════════════════════════════════════════════
     * Generate narasi seluruh kelas bisa berupa (jumlah siswa × jumlah
     * mapel) iterasi yang berat. Diproses per-chunk kecil lewat Livewire
     * event recursion: tiap batch = 1 round-trip browser = timer PHP fresh,
     * sehingga tidak menabrak max_execution_time (30-60 dtk) shared hosting.
     */
    private const NARASI_CHUNK_SIZE = 5; // siswa per batch

    /** Antrian student_id yang belum digenerate narasinya. */
    public array $narasiQueue = [];

    /** Flag apakah proses generate sedang berjalan. */
    public bool $isGeneratingNarasi = false;

    /** Jumlah siswa yang sudah selesai diproses. */
    public int $narasiProcessed = 0;

    /** Total siswa yang harus diproses. */
    public int $narasiTotal = 0;

    /**
     * Siapkan semua data yang dibutuhkan view Blade.
     * Semua query berat dilakukan di sini, bukan di Blade,
     * agar view tetap bersih dan mudah dibaca.
     */
    public function getViewData(): array
    {
        /** @var ClassHomeroom $homeroom */
        $homeroom = $this->record;

        $classroomId = $homeroom->classroom_id;
        $academicPeriodId = $homeroom->academic_period_id;
        $semester = $homeroom->academicPeriod->semester;

        // 1. Ambil semua siswa aktif di kelas ini, urut abjad
        $enrollments = Enrollment::where('classroom_id', $classroomId)
            ->where('academic_period_id', $academicPeriodId)
            ->where('status', 'active')
            ->with('student')
            ->get()
            ->sortBy('student.name');

        $studentIds = $enrollments->pluck('student_id');

        // 2. Ambil semua SK Mengajar yang aktif di kelas ini pada periode ini
        //    Kecuali mapel Kokurikuler (P5) — ditampilkan terpisah
        $teachingAssignments = TeachingAssignment::where('classroom_id', $classroomId)
            ->where('academic_period_id', $academicPeriodId)
            // teacher di-eager-load: dipakai instructorDisplayName() di bawah
            // (hindari N+1 + null-safe untuk pembina ekskul eksternal).
            ->with(['subject', 'teacher'])
            ->get();

        // ✅ PERBAIKAN: Gunakan filter() karena is_kokurikuler adalah accessor (method),
        // bukan kolom database. Collection->where() tidak bisa resolve accessor.
        $akademikAssignments = $teachingAssignments->filter(fn($ta) => !$ta->subject->is_kokurikuler);
        $kokurikulerAssignments = $teachingAssignments->filter(fn($ta) => $ta->subject->is_kokurikuler);

        // 3. Ambil semua final_grades sekaligus (hindari N+1)
        $finalGrades = FinalGrade::whereIn('student_id', $studentIds)
            ->whereIn('teaching_assignment_id', $teachingAssignments->pluck('id'))
            ->where('semester', $semester)
            ->get()
            ->groupBy('student_id'); // Struktur: [student_id => Collection of FinalGrade]

        // 4. Ambil semua attendance_summaries sekaligus
        $attendanceSummaries = AttendanceSummary::whereIn('student_id', $studentIds)
            ->whereIn('teaching_assignment_id', $akademikAssignments->pluck('id'))
            ->where('semester', $semester)
            ->get()
            ->groupBy('student_id');

        // 5. Hitung progress input nilai per Guru Mapel
        $progressGuruMapel = [];
        $totalSiswaDiKelas = $enrollments->count();

        foreach ($akademikAssignments as $ta) {
            $gradedCount = 0;
            if ($totalSiswaDiKelas > 0) {
                foreach ($enrollments as $enrollment) {
                    $studentGrades = $finalGrades[$enrollment->student_id] ?? collect();
                    $grade = $studentGrades->where('teaching_assignment_id', $ta->id)->first();
                    if ($grade && $grade->final_score !== null) {
                        $gradedCount++;
                    }
                }
            }
            
            $progressGuruMapel[] = [
                // FIX null teacher_id: SK ekskul pembina eksternal masuk loop ini
                // (filter di atas hanya kecualikan kokurikuler, bukan ekskul), maka
                // $ta->teacher->name FATAL. instructorDisplayName() = sumber tunggal.
                'teacher' => $ta->instructorDisplayName(),
                'subject' => $ta->subject->name,
                'graded_count' => $gradedCount,
                'total_students' => $totalSiswaDiKelas,
                'percentage' => $totalSiswaDiKelas > 0 ? round(($gradedCount / $totalSiswaDiKelas) * 100) : 0,
            ];
        }

        // 6. Ambil catatan wali kelas (student_reports) jika ada
        $studentReports = StudentReport::whereIn('student_id', $studentIds)
            ->where('academic_period_id', $academicPeriodId)
            ->get()
            ->keyBy('student_id');

        return [
            'homeroom' => $homeroom,
            'enrollments' => $enrollments,
            'akademikAssignments' => $akademikAssignments,
            'kokurikulerAssignments' => $kokurikulerAssignments,
            'finalGrades' => $finalGrades,
            'attendanceSummaries' => $attendanceSummaries,
            'studentReports' => $studentReports,
            'progressGuruMapel' => collect($progressGuruMapel)->sortBy('percentage')->values(),
            'semester' => $semester,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('input_homeroom_notes')
                ->label('Input Catatan & Absensi')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn() =>
                    auth()->user()->hasAnyRole(['super_admin', 'admin', 'teacher'])
                    && ($this->record->academicPeriod?->is_active
                        || auth()->user()->hasAnyRole(['super_admin', 'admin']))
                )
                ->form(function () {
                    return [
                        \Filament\Forms\Components\Repeater::make('reports')
                            ->label('Daftar Siswa')
                            ->schema([
                                \Filament\Forms\Components\Hidden::make('student_id'),
                                \Filament\Forms\Components\TextInput::make('student_name')
                                    ->label('Nama Siswa')
                                    ->disabled()
                                    ->dehydrated(false),
                                \Filament\Forms\Components\Grid::make(3)->schema([
                                    \Filament\Forms\Components\TextInput::make('sick_days')
                                        ->numeric()
                                        ->minValue(0)
                                        ->label('Sakit'),
                                    \Filament\Forms\Components\TextInput::make('excused_days')
                                        ->numeric()
                                        ->minValue(0)
                                        ->label('Izin'),
                                    \Filament\Forms\Components\TextInput::make('unexcused_days')
                                        ->numeric()
                                        ->minValue(0)
                                        ->label('Alpha'),
                                ]),
                                \Filament\Forms\Components\Textarea::make('homeroom_notes')
                                    ->label('Catatan Wali Kelas')
                                    ->rows(2),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull()
                    ];
                })
                ->mountUsing(function (\Filament\Forms\Form $form) {
                    $homeroom = $this->record;
                    $classroomId = $homeroom->classroom_id;
                    $academicPeriodId = $homeroom->academic_period_id;

                    $enrollments = Enrollment::where('classroom_id', $classroomId)
                        ->where('academic_period_id', $academicPeriodId)
                        ->where('status', 'active')
                        ->with('student')
                        ->get()
                        ->sortBy('student.name');

                    $studentIds = $enrollments->pluck('student_id');

                    // Ambil attendance otomatis (jika ada) sebagai prepopulate default
                    $attendanceSummaries = AttendanceSummary::whereIn('student_id', $studentIds)
                        ->whereIn('teaching_assignment_id', TeachingAssignment::where('classroom_id', $classroomId)
                            ->where('academic_period_id', $academicPeriodId)->pluck('id'))
                        ->get()
                        ->groupBy('student_id');

                    $existingReports = StudentReport::whereIn('student_id', $studentIds)
                        ->where('academic_period_id', $academicPeriodId)
                        ->get()
                        ->keyBy('student_id');

                    $form->fill([
                        'reports' => $enrollments->map(function ($enrollment) use ($existingReports, $attendanceSummaries) {
                            $studentId = $enrollment->student_id;
                            $report = $existingReports[$studentId] ?? null;
                            $absensi = $attendanceSummaries[$studentId] ?? collect();

                            $sick = $report ? $report->sick_days : $absensi->sum('sick');
                            $permit = $report ? $report->excused_days : $absensi->sum('permit');
                            $alpha = $report ? $report->unexcused_days : $absensi->sum('alpha');

                            return [
                                'student_id' => $studentId,
                                'student_name' => $enrollment->student->name,
                                'sick_days' => $sick,
                                'excused_days' => $permit,
                                'unexcused_days' => $alpha,
                                'homeroom_notes' => $report->homeroom_notes ?? null,
                            ];
                        })->values()->toArray()
                    ]);
                })
                ->action(function (array $data) {
                    // Guard defense-in-depth: cegah eksekusi jika periode terkunci
                    if (!$this->record->academicPeriod?->is_active
                        && !auth()->user()->hasAnyRole(['super_admin', 'admin'])) {
                        \Filament\Notifications\Notification::make()
                            ->title('Periode Terkunci')
                            ->body('Tidak dapat mengubah catatan pada periode yang sudah dibekukan.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $homeroom = $this->record;
                    $classroomId = $homeroom->classroom_id;
                    $academicPeriodId = $homeroom->academic_period_id;

                    \Illuminate\Support\Facades\DB::transaction(function () use ($data, $classroomId, $academicPeriodId) {
                        foreach ($data['reports'] as $reportData) {
                            StudentReport::updateOrCreate(
                                [
                                    'student_id' => $reportData['student_id'],
                                    'academic_period_id' => $academicPeriodId,
                                ],
                                [
                                    'classroom_id' => $classroomId,
                                    'sick_days' => $reportData['sick_days'] ?? 0,
                                    'excused_days' => $reportData['excused_days'] ?? 0,
                                    'unexcused_days' => $reportData['unexcused_days'] ?? 0,
                                    'homeroom_notes' => $reportData['homeroom_notes'] ?? null,
                                ]
                            );
                        }
                    });

                    \Filament\Notifications\Notification::make()
                        ->title('Catatan berhasil disimpan')
                        ->success()
                        ->send();
                }),

            // Aksi cetak/unduh dikelompokkan ke dalam dropdown agar header rapor
            // tidak penuh sesak (Issue #4 — 7 tombol sebaris → overflow).
            \Filament\Actions\ActionGroup::make([

            Action::make('cetak_rapor')
                ->label('Cetak Rapor')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->visible(fn() => auth()->user()->hasAnyRole(['student', 'guardian', 'teacher', 'admin', 'super_admin']))
                ->form([
                    \Filament\Forms\Components\Select::make('student_id')
                        ->label('Pilih Siswa')
                        ->options(function () {
                            $query = \App\Models\Enrollment::where('classroom_id', $this->record->classroom_id)
                                ->where('academic_period_id', $this->record->academic_period_id)
                                ->where('status', 'active')
                                ->with('student');
                            
                            if (auth()->user()->hasRole('student')) {
                                $student = \App\Models\Student::where('nisn', auth()->user()->username)->first();
                                if ($student) {
                                    $query->where('student_id', $student->id);
                                }
                            }
                            
                            return $query->get()->pluck('student.name', 'student.id');
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $url = route('rapor.print', [
                        'homeroom' => $this->record->id,
                        'student' => $data['student_id'],
                    ]);
                    
                    $this->js("window.open('{$url}', '_blank')");
                }),

            Action::make('export_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->visible(fn() => auth()->user()->hasAnyRole(['student', 'guardian', 'teacher', 'admin', 'super_admin']))
                ->form([
                    \Filament\Forms\Components\Select::make('student_id')
                        ->label('Pilih Siswa')
                        ->options(function () {
                            $query = \App\Models\Enrollment::where('classroom_id', $this->record->classroom_id)
                                ->where('academic_period_id', $this->record->academic_period_id)
                                ->where('status', 'active')
                                ->with('student');
                            
                            // If user is a student, only show their own name
                            if (auth()->user()->hasRole('student')) {
                                $student = \App\Models\Student::where('nisn', auth()->user()->username)->first();
                                if ($student) {
                                    $query->where('student_id', $student->id);
                                }
                            }
                            
                            // If guardian, only show their kids (assuming guardian username is wali_nisn)
                            // This depends on the specific project logic, but filtering it minimally works.
                            
                            return $query->get()->pluck('student.name', 'student.id');
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $service = new RaporExportService();
                    return $service->exportPdf($this->record, $data['student_id']);
                }),

            Action::make('export_word')
                ->label('Download Word')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->visible(fn() => auth()->user()->hasAnyRole(['teacher', 'admin', 'super_admin']))
                ->form([
                    \Filament\Forms\Components\Select::make('student_id')
                        ->label('Pilih Siswa')
                        ->options(function () {
                            return \App\Models\Enrollment::where('classroom_id', $this->record->classroom_id)
                                ->where('academic_period_id', $this->record->academic_period_id)
                                ->where('status', 'active')
                                ->with('student')
                                ->get()
                                ->pluck('student.name', 'student.id');
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $service = new RaporExportService();
                    return $service->exportWord($this->record, $data['student_id']);
                }),

            ])
                ->label('Cetak & Unduh Rapor')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->button(),

            Action::make('kunci_semua')
                ->label('Kunci Semua Nilai')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'admin', 'headmaster']))
                // Periode non-aktif = read-only (Audit MED-1).
                ->hidden(fn() => ! ($this->record->academicPeriod?->is_active ?? false))
                ->requiresConfirmation()
                ->modalHeading('Kunci Semua Nilai Rapor?')
                ->modalDescription(
                    'Setelah dikunci, nilai tidak akan berubah meskipun guru menginput nilai baru. ' .
                    'Lakukan ini hanya saat rapor siap dicetak.'
                )
                ->action(function () {
                    $homeroom = $this->record;
                    $semester = $homeroom->academicPeriod->semester;
                    $studentIds = Enrollment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->where('status', 'active')
                        ->pluck('student_id');

                    $assignmentIds = TeachingAssignment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->pluck('id');

                    // ── GUARD: jangan kunci jika deskripsi rapor masih kosong ──
                    // snapshot() menolak menimpa record terkunci (FinalGrade::snapshot),
                    // sehingga bila dikunci saat narasi kosong, "Generate Deskripsi"
                    // sesudahnya menjadi no-op dan narasi tak pernah masuk rapor.
                    // Wajibkan generate deskripsi LEBIH DULU (mapel akademik saja).
                    $akademikAssignmentIds = TeachingAssignment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->whereHas('subject', fn($q) => $q->whereNotIn('type', ['kokurikuler', 'extracurricular']))
                        ->pluck('id');

                    $expectedNarratives = $studentIds->count() * $akademikAssignmentIds->count();
                    $filledNarratives = FinalGrade::whereIn('student_id', $studentIds)
                        ->whereIn('teaching_assignment_id', $akademikAssignmentIds)
                        ->where('semester', $semester)
                        ->whereNotNull('narrative_description')
                        ->where('narrative_description', '!=', '')
                        ->count();

                    if ($expectedNarratives > 0 && $filledNarratives < $expectedNarratives) {
                        $kosong = $expectedNarratives - $filledNarratives;
                        \Filament\Notifications\Notification::make()
                            ->title('Deskripsi rapor belum lengkap')
                            ->body("Masih ada {$kosong} deskripsi mapel yang kosong. " .
                                'Klik "Generate Semua Deskripsi" dan pastikan terisi sebelum mengunci ' .
                                '— nilai yang sudah dikunci tidak bisa lagi diisi deskripsinya.')
                            ->warning()
                            ->persistent()
                            ->send();
                        return;
                    }

                    FinalGrade::whereIn('student_id', $studentIds)
                        ->whereIn('teaching_assignment_id', $assignmentIds)
                        ->where('semester', $semester)
                        ->update([
                            'is_locked' => true,
                            'locked_at' => now(),
                        ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Semua nilai berhasil dikunci')
                        ->success()
                        ->send();
                }),

            Action::make('buka_kunci')
                ->label('Buka Kunci Nilai')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'admin']))
                // Periode non-aktif = read-only: cegah pencairan freeze rapor lampau (Audit MED-1).
                ->hidden(fn() => ! ($this->record->academicPeriod?->is_active ?? false))
                ->requiresConfirmation()
                ->modalHeading('Buka Kunci Nilai Rapor?')
                ->modalDescription('Nilai akan bisa diperbarui kembali oleh guru.')
                ->action(function () {
                    $homeroom = $this->record;
                    $semester = $homeroom->academicPeriod->semester;
                    $studentIds = Enrollment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->where('status', 'active')
                        ->pluck('student_id');

                    $assignmentIds = TeachingAssignment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->pluck('id');

                    FinalGrade::whereIn('student_id', $studentIds)
                        ->whereIn('teaching_assignment_id', $assignmentIds)
                        ->where('semester', $semester)
                        ->update([
                            'is_locked' => false,
                            'locked_at' => null,
                        ]);

                    \Filament\Notifications\Notification::make()
                        ->title('Kunci nilai berhasil dibuka')
                        ->warning()
                        ->send();
                }),
            Action::make('generate_narasi')
                ->label('Generate Semua Deskripsi')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                // Hanya Admin & Wali Kelas yang boleh memicu generasi massal.
                // Peran pemantau (headmaster/guru_bk) yang kini bisa membuka halaman
                // ini tetap read-only (Audit MED).
                ->visible(fn() => auth()->user()->hasAnyRole(['super_admin', 'admin', 'teacher']))
                // Periode non-aktif = read-only: jangan timpa narasi rapor lampau (Audit MED-1).
                ->hidden(fn() => ! ($this->record->academicPeriod?->is_active ?? false))
                ->requiresConfirmation()
                ->modalHeading('Generate Deskripsi Otomatis?')
                ->modalDescription(
                    'Sistem akan membuat narasi rapor untuk semua siswa di semua mapel ' .
                    'berdasarkan data nilai dan TP yang sudah diinput. ' .
                    'Deskripsi yang sudah ditulis manual AKAN DITIMPA. Lanjutkan?'
                )
                ->action(function () {
                    // Cegah start ganda saat proses masih berjalan.
                    if ($this->isGeneratingNarasi) {
                        return;
                    }

                    $homeroom = $this->record;

                    // Kumpulkan siswa aktif; proses per-chunk via event recursion.
                    $studentIds = Enrollment::where('classroom_id', $homeroom->classroom_id)
                        ->where('academic_period_id', $homeroom->academic_period_id)
                        ->where('status', 'active')
                        ->pluck('student_id')
                        ->all();

                    if (empty($studentIds)) {
                        Notification::make()
                            ->title('Tidak ada siswa aktif untuk diproses.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $this->narasiQueue = $studentIds;
                    $this->narasiTotal = count($studentIds);
                    $this->narasiProcessed = 0;
                    $this->isGeneratingNarasi = true;

                    // Mulai batch pertama. Browser round-trip memberi timer PHP fresh.
                    $this->dispatch('generate-next-narasi-batch');
                }),
        ];
    }

    /**
     * Proses satu batch generate narasi (NARASI_CHUNK_SIZE siswa).
     *
     * Dipicu berulang oleh dispatch('generate-next-narasi-batch'):
     * setiap batch dijalankan pada request HTTP baru sehingga
     * max_execution_time di-reset — aman untuk shared hosting.
     */
    #[On('generate-next-narasi-batch')]
    public function generateNextNarasiBatch(): void
    {
        if (empty($this->narasiQueue)) {
            $this->finishNarasiGeneration();
            return;
        }

        $chunk = array_splice($this->narasiQueue, 0, self::NARASI_CHUNK_SIZE);

        $homeroom = $this->record;
        $semester = $homeroom->academicPeriod->semester;
        $service = new DescriptionGeneratorService();

        // SK Mengajar AKADEMIK di kelas ini (P5 & ekskul dikecualikan).
        // Filter kolom DB asli `type` — `is_kokurikuler` hanya accessor PHP.
        // ✅ HEMAT RAM: grades di-scope HANYA ke siswa dalam chunk ini,
        // bukan seluruh kelas, sehingga payload hydration tetap kecil.
        $assignments = TeachingAssignment::where('classroom_id', $homeroom->classroom_id)
            ->where('academic_period_id', $homeroom->academic_period_id)
            ->whereHas('subject', fn($q) => $q->whereNotIn('type', ['kokurikuler', 'extracurricular']))
            ->with([
                'subject',
                'academicPeriod',
                'assessments.learningObjectives',
                'assessments.grades' => fn($q) => $q->whereIn('student_id', $chunk)->whereNotNull('score'),
            ])
            ->get();

        foreach ($chunk as $studentId) {
            foreach ($assignments as $assignment) {
                $narrative = $service->generate($assignment, $studentId);

                // Lewat snapshot(): narasi pada nilai yang sudah dikunci/override
                // manual TIDAK ditimpa (Audit 3.6).
                FinalGrade::snapshot(
                    $studentId,
                    $assignment->id,
                    $semester,
                    ['narrative_description' => $narrative]
                );
            }
            $this->narasiProcessed++;
        }

        // Masih ada antrian → batch berikutnya (round-trip baru); jika habis → selesai.
        if (!empty($this->narasiQueue)) {
            $this->dispatch('generate-next-narasi-batch');
        } else {
            $this->finishNarasiGeneration();
        }
    }

    /**
     * Persentase progres generate narasi untuk progress bar blade.
     */
    public function getNarasiProgressPercentageProperty(): int
    {
        if ($this->narasiTotal === 0) {
            return 0;
        }

        return (int) round(($this->narasiProcessed / $this->narasiTotal) * 100);
    }

    private function finishNarasiGeneration(): void
    {
        $this->isGeneratingNarasi = false;

        Notification::make()
            ->title("Berhasil generate deskripsi untuk {$this->narasiProcessed} siswa")
            ->body('Semua narasi rapor sudah dibuat. Silakan review sebelum dikunci.')
            ->success()
            ->send();

        $this->narasiQueue = [];
    }
}