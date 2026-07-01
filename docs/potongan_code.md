# Potongan Kode — SIPDL

Dokumen ini berisi **potongan kode yang mewakili** pembahasan pada `docs/pembahasan-pengkodean.md`, disusun per Use Case dan per lapisan MVC.

**Cara baca:**
- Tiap potongan diberi header **`File:`** (path relatif dari root) dan **`Baris:`** (nomor baris awal–akhir pada file asli).
- Bila kode dipotong, ditandai `⋮ (baris A–B dihilangkan)` atau `⋮` — **nomor baris awal selalu dipertahankan** agar mudah dirujuk.
- Nomor baris mengacu **kondisi kode saat ini (as-is)**. Jika file diedit kemudian, nomor baris dapat bergeser.

---

## UC1 — Mengelola Data Master (Admin)

**Model — aturan domain "satu periode aktif" (otomatis menonaktifkan periode lain).**
`File:` `app/Models/AcademicPeriod.php` · `Baris:` 83–97
```php
    protected static function booted(): void
    {
        static::saving(function ($model) {
            if ($model->is_active) {
                // Gunakan DB transaction untuk mencegah race condition:
                \Illuminate\Support\Facades\DB::transaction(function () use ($model) {
                    static::where('id', '!=', $model->id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                });
            }
        });
    }
```

> Lapisan View/Controller CRUD master data diimplementasikan oleh Filament Resource: `app/Filament/Resources/StudentResource.php`, `TeacherResource.php`, `ClassroomResource.php`, `SubjectResource.php`, `AcademicPeriodResource.php` (pola `form()`, `table()`, `getPages()`).

---

## UC2 — Mengelola Penugasan Pembelajaran (SK Mengajar)

**Model — saat SK Mengajar dibuat/diubah: isi KKTP default & seed `grade_ranges` (A–E).**
`File:` `app/Models/TeachingAssignment.php` · `Baris:` 257–279
```php
    protected static function booted(): void
    {
        static::creating(function (TeachingAssignment $model) {
            if (is_null($model->kktp)) {
                $defaultKkm = \App\Models\SchoolSetting::first()?->default_kkm ?? 75;
                $model->kktp = $defaultKkm;
            }
        });

        static::created(function (TeachingAssignment $model) {
            GradeRangeResolver::seedDefaults($model);
        });

        static::updated(function (TeachingAssignment $model) {
            if ($model->wasChanged('kktp')) {
                GradeRangeResolver::seedDefaults($model);
            }
        });
    }
```

**Service — generate 5 baris `grade_ranges` per SK Mengajar.**
`File:` `app/Services/GradeRangeResolver.php` · `Baris:` 133–150
```php
    public static function seedDefaults(TeachingAssignment $assignment): void
    {
        $kktp = $assignment->kktp_or_default;
        $defaults = self::calculateDefaultRanges($kktp);

        foreach ($defaults as $letter => $range) {
            GradeRange::updateOrCreate(
                ['teaching_assignment_id' => $assignment->id, 'letter' => $letter],
                ['min_score' => $range['min_score'], 'max_score' => $range['max_score']]
            );
        }
    }
```

---

## UC3 — Kelola Tujuan Pembelajaran (TP)

**Controller/Resource — Guru hanya melihat TP untuk mapel yang ia ampu.**
`File:` `app/Filament/Resources/LearningObjectiveResource.php` · `Baris:` 34–45
```php
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check() && auth()->user()->hasRole('teacher')) {
            $subjectIds = \App\Models\TeachingAssignment::where('teacher_id', auth()->user()->teacher?->id)
                ->pluck('subject_id');
            $query->whereIn('subject_id', $subjectIds);
        }

        return $query;
    }
```

**View — field Kode TP: panduan format via `helperText` + `maxLength` (TIDAK distandarkan otomatis).**
`File:` `app/Filament/Resources/LearningObjectiveResource.php` · `Baris:` 136–141
```php
                        Forms\Components\TextInput::make('code')
                            ->label('Kode TP')
                            ->placeholder('Contoh: MTK-7-1-TP1')
                            ->helperText('Format: [KODE_MAPEL]-[KELAS]-[SEMESTER]-[NOMOR]. Contoh: IPA-8-2-TP3')
                            ->required()
                            ->maxLength(20),
```

---

## UC4 — Mengelola Jurnal KBM dan Absensi

**View/Controller — form jurnal: pilihan SK Mengajar difilter ke periode aktif + auto nomor pertemuan.**
`File:` `app/Filament/Resources/LessonJournalResource.php` · `Baris:` 64–92
```php
                        Forms\Components\Select::make('teaching_assignment_id')
                            ->label('Kelas & Mata Pelajaran')
                            ->options(function () {
                                $query = TeachingAssignment::with(['subject', 'classroom', 'academicPeriod'])
                                    ->whereHas('academicPeriod', fn($q) => $q->where('is_active', true));

                                if (auth()->user()->hasRole('teacher')) {
                                    $query->where('teacher_id', auth()->user()->teacher?->id);
                                }

                                return $query->get()->mapWithKeys(
                                    fn($ta) => [$ta->id => "{$ta->subject->name} — {$ta->classroom->name}"]
                                );
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (!$state) return;
                                // Auto-isi nomor pertemuan berikutnya
                                $lastMeeting = LessonJournal::where('teaching_assignment_id', $state)
                                    ->max('meeting_number') ?? 0;
                                $set('meeting_number', $lastMeeting + 1);
                            }),
```

**Controller — aksi "Isi Absensi Kelas" menyimpan kehadiran per siswa.**
`File:` `app/Filament/Resources/TeachingAssignmentResource/RelationManagers/AttendancesRelationManager.php` · `Baris:` 239–267
```php
                    ->action(function (array $data, RelationManager $livewire) {
                        $assignment = $livewire->getOwnerRecord();
                        $date = $data['date'];

                        if ($data['is_holiday']) {
                            $studentIds = Enrollment::query()
                                ->where('classroom_id', $assignment->classroom_id)
                                ->where('academic_period_id', $assignment->academic_period_id)
                                ->where('status', 'active')
                                ->pluck('student_id');

                            foreach ($studentIds as $studentId) {
                                Attendance::updateOrCreate(
                                    ['teaching_assignment_id' => $assignment->id, 'student_id' => $studentId, 'date' => $date],
                                    ['status' => 'holiday', 'note' => $data['holiday_note']]
                                );
                            }
                            Notification::make()->title('Dicatat sebagai Libur')->success()->send();
                            return;
                        }

                        foreach ($data['students_attendance'] as $item) {
                            Attendance::updateOrCreate(
                                ['teaching_assignment_id' => $assignment->id, 'student_id' => $item['student_id'], 'date' => $date],
                                ['status' => $item['status'], 'note' => $item['note'] ?? null]
                            );
                        }
                        Notification::make()->title('Data Absensi Tersimpan')->success()->send();
                    }),
```
> Daftar tanggal absensi dibatasi oleh jadwal mengajar pada `getAvailableDates()` — `Baris:` 31–62 di file yang sama.

**Observer — rekap H/I/S/A & persentase dihitung ulang otomatis setelah absensi tersimpan.**
`File:` `app/Observers/AttendanceObserver.php` · `Baris:` 34–85
```php
    private function recalculate(Attendance $attendance): void
    {
        $assignmentId = $attendance->teaching_assignment_id;
        $studentId = $attendance->student_id;

        $attendance->load('teachingAssignment.academicPeriod');
        $semester = $attendance->teachingAssignment->academicPeriod->semester;

        // Hitung ulang dari data AKTUAL (status 'holiday' diabaikan)
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

        $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0.00;

        AttendanceSummary::updateOrCreate(
            ['student_id' => $studentId, 'teaching_assignment_id' => $assignmentId, 'semester' => $semester],
            ['present' => $present, 'permit' => $permit, 'sick' => $sick, 'alpha' => $alpha,
             'attendance_percentage' => $percentage]
        );
    }
```

---

## UC5 — Input Nilai Sumatif/Formatif & Hitung Nilai Akhir

**Controller — validasi total bobot sumatif ≤ 100% sebelum simpan asesmen.**
`File:` `app/Filament/Resources/TeachingAssignmentResource/RelationManagers/AssessmentsRelationManager.php` · `Baris:` 198–207
```php
                    ->before(function (Tables\Actions\CreateAction $action, array $data, RelationManager $livewire) {
                        // Validasi persentase > 100% HANYA untuk Sumatif
                        if ($livewire->getOwnerRecord()->grading_formula === 'weighting' && !str_starts_with($data['category'], 'formatif')) {
                            $newTotal = $livewire->getCurrentTotalWeight() + (int) $data['weight'];
                            if ($newTotal > 100) {
                                Notification::make()->title('Gagal')->body("Total persentase Sumatif melebihi 100% ({$newTotal}%).")->danger()->send();
                                $action->halt();
                            }
                        }
                    }),
```

**Controller — aksi `input_grades`: simpan nilai (observer dimatikan) lalu hitung ulang nilai akhir per siswa.**
`File:` `.../RelationManagers/AssessmentsRelationManager.php` · `Baris:` 290–351
```php
                    ->action(function ($record, array $data) {
                        // Guard defense-in-depth: cegah eksekusi jika periode terkunci
                        if ($this->isPeriodLocked()) {
                            Notification::make()->title('Periode Terkunci')->body('Tidak dapat mengubah nilai pada periode yang sudah dibekukan.')->danger()->send();
                            return;
                        }

                        // Matikan GradeObserver sementara (hindari N+1)
                        Grade::withoutEvents(function () use ($record, $data) {
                            foreach ($data['grades_data'] as $item) {
                                $scoreToSave = $item['score'] ?? null;
                                if ($record->category === 'formatif_poin') {
                                    $scoreToSave = !empty($item['is_completed']) ? $record->weight : 0;
                                }
                                Grade::updateOrCreate(
                                    ['assessment_id' => $record->id, 'student_id' => $item['student_id']],
                                    ['score' => $scoreToSave, 'feedback' => $item['feedback'] ?? null]
                                );
                            }
                        });

                        // Recalculate final grades SEKALI untuk semua siswa
                        $assignment = $record->teachingAssignment;
                        $semester = $assignment->academicPeriod->semester;
                        $kktp = $assignment->kktp_or_default;

                        foreach ($data['grades_data'] as $item) {
                            $finalScore = $assignment->calculateFinalGrade($item['student_id']);
                            $gradeLabel = $finalScore > 0
                                ? GradeRangeResolver::resolve($assignment, $finalScore) : null;
                            \App\Models\FinalGrade::updateOrCreate(
                                ['student_id' => $item['student_id'], 'teaching_assignment_id' => $assignment->id, 'semester' => $semester],
                                ['final_score' => $finalScore > 0 ? $finalScore : null, 'grade_label' => $gradeLabel]
                            );
                        }
                        // ⋮ (notifikasi sukses)
                    }),
```

**Model — algoritma inti perhitungan nilai akhir (3 cabang `grading_formula` + booster formatif).**
`File:` `app/Models/TeachingAssignment.php` · `Baris:` 163–251
```php
    public function calculateFinalGrade(int $studentId): float
    {
        $assessments = $this->assessments()->with([
            'grades' => fn ($q) => $q->where('student_id', $studentId),
        ])->get();

        if ($assessments->isEmpty()) return 0;

        // TAHAP 1: nilai dasar sumatif (average / weighting / percentage)
        $summativeAssessments = $assessments->filter(fn ($a) =>
            in_array($a->category, ['sumatif_lingkup_materi', 'sumatif_akhir_semester']));
        $summativeScore = 0;
        // ⋮ (baris 183–234: 3 cabang grading_formula)

        // TAHAP 1b: BOOSTER FORMATIF (baris 236–243)
        $formativeScores = $assessments
            ->filter(fn ($a) => str_starts_with($a->category, 'formatif'))
            ->map(fn ($a) => $a->grades->first()?->score);
        $booster = $this->boosterContribution($formativeScores);

        // TAHAP 2: GABUNG + CAP 100 + PEMBULATAN (baris 245–249)
        $finalGrade = min(100, $summativeScore + $booster);
        return (float) round($finalGrade);
    }
```

**Model — helper booster (dipakai nilai akhir & skor per-TP narasi).**
`File:` `app/Models/TeachingAssignment.php` · `Baris:` 264–275
```php
    public function boosterContribution(\Illuminate\Support\Collection $formativeScores): float
    {
        $scores = $formativeScores->filter(fn ($s) => $s !== null && (float) $s > 0);

        return match ($this->booster_mode) {
            'weight' => (float) $scores->sum(fn ($s) => (float) $s * ((float) $this->booster_value / 100)),
            'point'  => (float) $scores->count() * (float) $this->booster_value,
            default  => 0.0, // 'none'
        };
    }
```

**Migrasi — kolom booster pada `teaching_assignments`.**
`File:` `database/migrations/2026_02_17_054129_create_kbm_and_assessment_tables.php` · `Baris:` 26–29
```php
            // Booster Nilai Formatif. Mode: none | weight (nilai_formatif × %) | point (poin tetap/formatif terisi).
            $table->enum('booster_mode', ['none', 'weight', 'point'])->default('none');
            $table->decimal('booster_value', 5, 2)->nullable();
```

**UI — setelan booster di form SK Mengajar.**
`File:` `app/Filament/Resources/TeachingAssignmentResource.php` · `Baris:` 188–214 (Select mode + TextInput value kondisional)

**Service — konversi skor → predikat A–E (baca `grade_ranges`, fallback KKTP).**
`File:` `app/Services/GradeRangeResolver.php` · `Baris:` 31–45
```php
    public static function resolve(TeachingAssignment $assignment, float $score): string
    {
        $range = GradeRange::where('teaching_assignment_id', $assignment->id)
            ->where('min_score', '<=', $score)
            ->orderByDesc('min_score')
            ->first();

        if ($range) {
            return $range->letter;
        }

        return self::resolveDefault($score, $assignment->kktp_or_default);
    }
```

**Observer — jalur alternatif (saat satu Grade disimpan di luar aksi massal); berhenti bila nilai terkunci/override.**
`File:` `app/Observers/GradeObserver.php` · `Baris:` 36–85
```php
    private function recalculate(Grade $grade): void
    {
        $grade->load('assessment.teachingAssignment.academicPeriod');
        $assessment = $grade->assessment;
        $assignment = $assessment->teachingAssignment;
        $semester = $assignment->academicPeriod->semester;
        $studentId = $grade->student_id;

        $existing = FinalGrade::where('student_id', $studentId)
            ->where('teaching_assignment_id', $assignment->id)
            ->where('semester', $semester)->first();

        if ($existing?->is_locked) return;            // sudah dikunci → jangan timpa
        if ($existing?->is_manual_override) return;   // nilai manual admin → jangan timpa

        $finalScore = $assignment->calculateFinalGrade($studentId);
        $gradeLabel = $finalScore > 0 ? GradeRangeResolver::resolve($assignment, $finalScore) : null;

        FinalGrade::updateOrCreate(
            ['student_id' => $studentId, 'teaching_assignment_id' => $assignment->id, 'semester' => $semester],
            ['final_score' => $finalScore > 0 ? $finalScore : null, 'grade_label' => $gradeLabel]
        );
    }
```

---

## UC6 — Kunci dan Cetak Rapor

**Controller — simpan catatan wali kelas & absensi ke `StudentReport` (+ guard periode).**
`File:` `app/Filament/Resources/RaporResource/Pages/ViewRapor.php` · Aksi `input_homeroom_notes` mulai `Baris:` 123 · cuplikan simpan `Baris:` 214–245
```php
                ->action(function (array $data) {
                    // Guard defense-in-depth: cegah eksekusi jika periode terkunci
                    if (!$this->record->academicPeriod?->is_active
                        && !auth()->user()->hasAnyRole(['super_admin', 'admin'])) {
                        \Filament\Notifications\Notification::make()
                            ->title('Periode Terkunci')->body('...')->danger()->send();
                        return;
                    }
                    $homeroom = $this->record;
                    $classroomId = $homeroom->classroom_id;
                    $academicPeriodId = $homeroom->academic_period_id;

                    \Illuminate\Support\Facades\DB::transaction(function () use ($data, $classroomId, $academicPeriodId) {
                        foreach ($data['reports'] as $reportData) {
                            StudentReport::updateOrCreate(
                                ['student_id' => $reportData['student_id'], 'academic_period_id' => $academicPeriodId],
                                ['classroom_id' => $classroomId,
                                 'sick_days' => $reportData['sick_days'] ?? 0,
                                 'excused_days' => $reportData['excused_days'] ?? 0,
                                 'unexcused_days' => $reportData['unexcused_days'] ?? 0,
                                 'homeroom_notes' => $reportData['homeroom_notes'] ?? null]
                            );
                        }
                    });
                    // ⋮ (notifikasi sukses, baris 247–250)
                }),
```

**Controller — aksi "Kunci Semua Nilai" (set `is_locked = true`).**
`File:` `app/Filament/Resources/RaporResource/Pages/ViewRapor.php` · Aksi `kunci_semua` mulai `Baris:` 346
```php
                ->action(function () {
                    // ⋮ (ambil $studentIds & $assignmentIds dari kelas + periode)
                    FinalGrade::whereIn('student_id', $studentIds)
                        ->whereIn('teaching_assignment_id', $assignmentIds)
                        ->where('semester', $semester)
                        ->update(['is_locked' => true, 'locked_at' => now()]);
                    // ⋮ (notifikasi)
                }),
```

**Controller — aksi "Cetak Rapor" memanggil route cetak di tab baru.**
`File:` `app/Filament/Resources/RaporResource/Pages/ViewRapor.php` · Aksi `cetak_rapor` mulai `Baris:` 253 · cuplikan aksi `Baris:` 279–285
```php
                ->action(function (array $data) {
                    $url = route('rapor.print', [
                        'homeroom' => $this->record->id,
                        'student' => $data['student_id'],
                    ]);
                    $this->js("window.open('{$url}', '_blank')");
                }),
```

**Controller (HTTP) — endpoint cetak: rakit data via Service lalu render Blade.**
`File:` `app/Http/Controllers/RaporPrintController.php` · `Baris:` 13–34
```php
    public function show(ClassHomeroom $homeroom, Student $student)
    {
        $service = new RaporExportService();
        $data = $service->getRaporData($homeroom, $student->id);

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
```

**Service — agregasi seluruh data rapor (nilai akhir, kokurikuler, ekskul, absensi).**
`File:` `app/Services/RaporExportService.php` · `Baris:` 18–90 (cuplikan)
```php
    public function getRaporData(ClassHomeroom $homeroom, int $studentId): array
    {
        $classroom = $homeroom->classroom;
        $period = $homeroom->academicPeriod;
        $semester = $period->semester;
        // ⋮ (baris 24–31: enrollment + student)

        $akademikAssignments = TeachingAssignment::where('classroom_id', $classroom->id)
            ->where('academic_period_id', $period->id)
            ->whereHas('subject', fn($q) => $q->where('type', '!=', 'kokurikuler')->where('type', '!=', 'extracurricular'))
            ->with('subject')->get();

        $finalGrades = FinalGrade::where('student_id', $studentId)
            ->whereIn('teaching_assignment_id', $akademikAssignments->pluck('id'))
            ->where('semester', $semester)->get()->keyBy('teaching_assignment_id');
        // ⋮ (baris 47–66: kokurikuler, ekstrakurikuler, rekap absensi)

        $isOfficial = true;
        if (auth()->check() && auth()->user()->hasAnyRole(['student', 'guardian', 'Siswa', 'Wali Siswa'])) {
            $isOfficial = false;   // rapor bayangan / unofficial
        }
        $schoolIdentity = app(SchoolIdentityService::class)->getIdentity();

        return [ /* ⋮ baris 74–89: paket data ke view */ ];
    }
```

**Service — generator narasi deskripsi rapor (dipakai aksi `generate_narasi`).**
`File:` `app/Services/DescriptionGeneratorService.php` · `Baris:` 33–53
```php
    public function generate(TeachingAssignment $assignment, int $studentId): string
    {
        $kktp = $assignment->kktp_or_default;
        $tpResults = $this->calculateScorePerTp($assignment, $studentId, $kktp);

        if ($tpResults->isEmpty()) {
            return $this->buildDefaultNarrative($assignment);
        }

        $tpWithGrades = $tpResults->map(function ($tp) use ($assignment) {
            $tp['grade'] = GradeRangeResolver::resolve($assignment, $tp['average_score']);
            return $tp;
        });

        return $this->buildGradeBasedNarrative($assignment, $tpWithGrades);
    }
```
> Aksi `generate_narasi` di `ViewRapor.php` (mulai `Baris:` 416) memanggil service ini lalu `FinalGrade::updateOrCreate([...], ['narrative_description' => $narrative])`. **Catatan as-is:** aksi ini **belum** mengecek `is_locked` (berbeda dengan `ViewGradebook::generate_narasi` yang sudah `if ($finalGrade->is_locked) continue;`).

### Pendalaman — Mesin Generator Deskripsi (Rule Engine)

**Skor rata-rata per TP (dengan eager-load anti N+1).**
`File:` `app/Services/DescriptionGeneratorService.php` · `Baris:` 63–113
```php
    public function calculateScorePerTp(TeachingAssignment $assignment, int $studentId, int $kktp): \Illuminate\Support\Collection
    {
        $learningObjectives = LearningObjective::whereHas('assessments',
            fn($q) => $q->where('teaching_assignment_id', $assignment->id))
            ->where('subject_id', $assignment->subject_id)->get();

        // ✅ PERBAIKAN N+1: load semua asesmen + grades + TP dalam 1 query
        $assessments = $assignment->assessments()
            ->with([
                'learningObjectives',
                'grades' => fn($q) => $q->where('student_id', $studentId)->whereNotNull('score'),
            ])->get();

        return $learningObjectives->map(function (LearningObjective $lo) use ($assessments, $assignment, $kktp) {
            // BASIS: hanya SUMATIF yang tertaut TP (formatif tidak mencampuri)
            $sumScores = $assessments
                ->filter(fn($a) => str_starts_with($a->category, 'sumatif')
                                && $a->learningObjectives->contains('id', $lo->id))
                ->flatMap(fn($a) => $a->grades->pluck('score'));
            if ($sumScores->isEmpty()) return null;
            $base = round($sumScores->avg(), 2);

            // BOOSTER: formatif tertaut TP (konsisten dg calculateFinalGrade)
            $formativeScores = $assessments
                ->filter(fn($a) => str_starts_with($a->category, 'formatif')
                                && $a->learningObjectives->contains('id', $lo->id))
                ->flatMap(fn($a) => $a->grades->pluck('score'));

            $average = min(100, $base + $assignment->boosterContribution($formativeScores));
            return ['id'=>$lo->id, 'code'=>$lo->code, 'attribute'=>$lo->attribute,
                    'average_score'=>$average, 'is_tuntas'=>$average >= $kktp];
        })->filter()->values();
    }
```

**Penyusun kalimat dari template (hierarki Guru→Admin→fallback).**
`File:` `app/Services/DescriptionGeneratorService.php` · `Baris:` 115–164 (cuplikan)
```php
    private function buildGradeBasedNarrative(TeachingAssignment $assignment, \Illuminate\Support\Collection $tpWithGrades): string
    {
        $gradePriority = ['A'=>1,'B'=>2,'C'=>3,'D'=>4,'E'=>5];
        $sorted = $tpWithGrades->sortBy(fn($tp) => $gradePriority[$tp['grade']] ?? 5);
        $maxGrade = $sorted->first()['grade'];
        $minGrade = $sorted->last()['grade'];

        // CASE 1: semua TP bergrade sama → 1 kalimat
        if ($maxGrade === $minGrade) {
            $combinedTp = $this->combineAttributes($tpWithGrades->pluck('attribute')->toArray());
            $template = NarrativeTemplate::getTemplate($maxGrade, $assignment->id);
            return str_replace('[TP]', $combinedTp, $template);
        }

        // CASE 2: Max != Min → 2 kalimat + konjungsi
        // ⋮ (baris 142–157: bangun $maxSentence & $minSentence dari NarrativeTemplate)
        $conjunction = $this->resolveConjunction($maxGrade, $minGrade);
        return rtrim($maxSentence, '. ') . $conjunction . lcfirst(ltrim($minSentence));
    }
```

**Konjungsi adaptif berdasarkan kombinasi grade.**
`File:` `app/Services/DescriptionGeneratorService.php` · `Baris:` 202–220
```php
    public function resolveConjunction(string $maxGrade, string $minGrade): string
    {
        $passingGrades = ['A', 'B', 'C'];
        $minPassing = in_array($minGrade, $passingGrades);

        if ($minPassing) return ', serta ';                                          // kedua sisi lulus
        if (in_array($maxGrade, $passingGrades) && !$minPassing) return ', namun ';   // kontras lulus–tidak
        return ', dan juga ';                                                          // keduanya belum tuntas
    }
```

---

## UC7 — Memantau Kinerja Penilaian Guru (Kepala Sekolah)

**Service — agregasi metrik kinerja guru (kelengkapan nilai + jumlah jurnal).**
`File:` `app/Services/NilaiVisualisasiService.php` · `Baris:` 175–247 (cuplikan)
```php
    public function getKinerjaGuru(): array
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();
        if (!$activePeriod) return [];

        $assignments = TeachingAssignment::where('academic_period_id', $activePeriod->id)
            ->whereHas('subject', fn($q) => $q->where('type', 'mandatory'))
            ->with([
                'teacher.user', 'subject', 'classroom',
                'finalGrades' => fn($q) => $q->where('semester', $activePeriod->semester)->whereNotNull('final_score'),
                'lessonJournals',
            ])->get()->groupBy('teacher_id');
        // ⋮ (baris 197–203: hitung jumlah siswa aktif per kelas)

        foreach ($assignments as $teacherId => $teacherAssignments) {
            // ⋮ (baris 206–224: akumulasi total siswa, nilai terisi, jurnal)
            $persentaseNilai = $totalStudents > 0 ? round(($totalGraded / $totalStudents) * 100, 1) : 0;
            $result[] = [
                'nama_guru' => $teacher->user->name,
                'persen_nilai' => $persentaseNilai,
                'jurnal_masuk' => $totalJournals,
                'status' => $persentaseNilai >= 100 ? 'Lengkap' : ($persentaseNilai >= 50 ? 'Sebagian' : 'Belum'),
                // ⋮ (field lain)
            ];
        }
        // ⋮ (baris 243–246: urut dari persentase terendah)
        return $result;
    }
```
> Dipanggil oleh widget read-only `KinerjaGuruWidget` (`canView()` dibatasi `headmaster`/`super_admin`). Ringkasan per kelas: `getRingkasanNilaiKelas()` — `Baris:` 137–169.

---

## UC8 — Memantau Perkembangan Akademik (Siswa & Orang Tua)

**Controller — batasi akses & filter data per sesi login.**
`File:` `app/Filament/Pages/Student/MyGrades.php` · `Baris:` 32–35
```php
    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasRole('student') && Auth::user()->student !== null;
    }
```

**Controller — ambil nilai akhir, rincian nilai, & rekap kehadiran milik siswa.**
`File:` `app/Filament/Pages/Student/MyGrades.php` · `Baris:` 86–146 (cuplikan)
```php
    protected function getViewData(): array
    {
        $student = Auth::user()->student;
        $activePeriod = AcademicPeriod::find($this->selectedPeriodId);
        // ⋮ (baris 93–109: cek enrollment aktif)

        $teachingAssignments = TeachingAssignment::with('subject', 'teacher')
            ->where('academic_period_id', $activePeriod->id)
            ->where('classroom_id', $classroomId)->get();
        $assignmentIds = $teachingAssignments->pluck('id')->toArray();

        $finalGrades = FinalGrade::where('student_id', $student->id)
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where('semester', $activePeriod->semester)->get()->keyBy('teaching_assignment_id');

        // ⋮ (baris 130–139: rincian Grade formatif/sumatif)

        $attendanceSummaries = AttendanceSummary::where('student_id', $student->id)
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->where('semester', $activePeriod->semester)->get()->keyBy('teaching_assignment_id');
        // ⋮ (baris 148–184: susun data akademik vs kokurikuler)
    }
```

**Service — otorisasi & data longitudinal (siswa hanya diri sendiri; wali hanya anaknya).**
`File:` `app/Services/NilaiVisualisasiService.php` · `Baris:` 21–49 (cuplikan `canViewStudent`)
```php
    public function canViewStudent(int $studentId): bool
    {
        $user = Auth::user();
        if ($user->hasAnyRole(['super_admin', 'headmaster'])) return true;
        if ($user->hasRole('student'))   return $user->student?->id === $studentId;
        if ($user->hasRole('wali_siswa')) return $user->guardianStudents()->where('id', $studentId)->exists();
        if ($user->hasRole('teacher'))   return $this->guruMengajarSiswa($user->teacher->id, $studentId);
        return false;
    }
```

### Pendalaman — Grafik Perkembangan Longitudinal

**Mesin: rangkai tren nilai lintas periode (load FinalGrade sekali, filter dari koleksi).**
`File:` `app/Services/NilaiVisualisasiService.php` · `Baris:` 63–131 (cuplikan)
```php
    public function getNilaiLongitudinal(int $studentId): array
    {
        // ⋮ (baris 65–91: deteksi peran guru & batas mapel yang boleh dilihat)

        $enrollments = Enrollment::where('student_id', $studentId)
            ->with('academicPeriod')->get()
            ->sortBy(fn($e) => $e->academicPeriod?->start_date)->values();   // sumbu waktu

        // ✅ PERBAIKAN N+1: load SEMUA FinalGrade siswa dalam 1 query
        $allGrades = FinalGrade::where('student_id', $studentId)
            ->with('teachingAssignment.subject')->get();

        $result = [];
        foreach ($enrollments as $enrollment) {
            $period = $enrollment->academicPeriod;
            $grades = $allGrades->filter(fn($g) =>
                $g->semester === $period->semester &&
                $g->teachingAssignment &&
                $g->teachingAssignment->academic_period_id === $period->id &&
                $g->teachingAssignment->classroom_id === $enrollment->classroom_id
            );
            $result[$period->name] = [];
            foreach ($grades as $grade) {
                // ⋮ (baris 117–124: pembatasan mapel utk guru bukan wali kelas)
                $result[$period->name][$grade->teachingAssignment->subject->name] = (float) $grade->final_score;
            }
        }
        return $result;
    }
```

**View: grafik garis + batang (Chart.js).**
`File:` `app/Filament/Widgets/NilaiSiswaWidget.php` · `Baris:` 16–19 (status) & 41–110 (data)
```php
    public static function canView(): bool
    {
        return false; // Dinonaktifkan sesuai permintaan pengguna
    }
    // ⋮
    protected function getData(): array
    {
        $service   = app(NilaiVisualisasiService::class);
        $studentId = Auth::user()->student?->id;
        if (!$studentId) return ['datasets' => [], 'labels' => []];

        $longitudinal = $service->getNilaiLongitudinal($studentId);
        $periods = array_keys($longitudinal);              // label sumbu-X
        // ⋮ (baris 52–105: bentuk dataset 'line' + 'bar' untuk mapel terpilih)
        return ['datasets' => $datasets, 'labels' => $periods];
    }
```
> **Catatan as-is:** widget dinonaktifkan (`canView() === false`); mesin `getNilaiLongitudinal()` tetap dipakai halaman `DetailNilaiSiswa`.

---

## UC9 — Login

**Controller — deteksi kredensial fleksibel (Username/NISN/NIP atau Email).**
`File:` `app/Filament/Pages/Auth/Login.php` · `Baris:` 44–54
```php
    protected function getCredentialsFromFormData(array $data): array
    {
        // Jika mengandung format email (@) → kolom 'email', selain itu → 'username'
        $login_type = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $login_type => $data['login'],
            'password' => $data['password'],
        ];
    }
```

**Model — kebijakan akses panel: hanya akun aktif yang boleh masuk.**
`File:` `app/Models/User.php` · `Baris:` 80–84
```php
    public function canAccessPanel(Panel $panel): bool
    {
        // Hanya user yang statusnya AKTIF yang boleh login ke dashboard
        return $this->is_active;
    }
```
> Peran (role) dikelola Spatie via trait `HasRoles` pada `app/Models/User.php` (`Baris:` 25).

---

## Optimasi & Kesiapan Shared Hosting

**Konfigurasi runtime tanpa daemon (queue sync, cache/session DB, log harian).**
`File:` `.env.example` · `Baris:` 17–41 (cuplikan)
```ini
LOG_CHANNEL=daily
LOG_STACK=daily
LOG_DAILY_DAYS=7
LOG_LEVEL=error
# ⋮
SESSION_DRIVER=database
# ⋮
QUEUE_CONNECTION=sync
# ⋮
CACHE_STORE=database
```

**Caching identitas sekolah via View Composer (TTL 30 menit).**
`File:` `app/Providers/AppServiceProvider.php` · `Baris:` 52–68
```php
        View::composer(
            ['layouts.app', 'partials.navbar', 'dashboard.*'],
            function (\Illuminate\View\View $view) {
                if (!Schema::hasTable('school_profiles')) {
                    $view->with('schoolProfile', null);
                    return;
                }
                $profile = Cache::remember(
                    'school_profile_global',
                    now()->addMinutes(30),
                    fn() => SchoolProfile::first()
                );
                $view->with('schoolProfile', $profile);
            }
        );
```

**Cache per-request profil sekolah (hindari `SchoolProfile::first()` berulang).**
`File:` `app/Providers/Filament/AdminPanelProvider.php` · `Baris:` 118–130
```php
    private static ?SchoolProfile $cachedProfile = null;
    private static bool $profileLoaded = false;

    private function getSchoolProfile(): ?SchoolProfile
    {
        if (!static::$profileLoaded) {
            static::$profileLoaded = true;
            if (Schema::hasTable('school_profiles')) {
                static::$cachedProfile = SchoolProfile::first();
            }
        }
        return static::$cachedProfile;
    }
```

**Batching anti N+1 (contoh: hitung jumlah siswa per kelas sekali jalan).**
`File:` `app/Services/NilaiVisualisasiService.php` · `Baris:` 197–203
```php
        // ✅ PERBAIKAN N+1: Batch query enrollment counts dalam 1 query.
        $enrollmentCounts = Enrollment::where('academic_period_id', $activePeriod->id)
            ->where('status', 'active')
            ->selectRaw('classroom_id, COUNT(*) as total')
            ->groupBy('classroom_id')
            ->pluck('total', 'classroom_id');
```

> Contoh anti-N+1 lain yang sudah ditampilkan: `Grade::withoutEvents()` (UC5), eager-load rantai relasi di `GradeObserver`/`AttendanceObserver` (UC4/UC5), dan pemuatan asesmen+nilai+TP sekali jalan di `DescriptionGeneratorService::calculateScorePerTp` (UC6). Pola **snapshot** `final_grades`/`attendance_summaries` membuat pembacaan rapor & dasbor tidak menghitung ulang.

---

## Lampiran — Indeks File & Lokasi

| UC | Lapisan | File | Baris kunci |
|----|---------|------|-------------|
| 1 | Model | `app/Models/AcademicPeriod.php` | 83–97 |
| 2 | Model | `app/Models/TeachingAssignment.php` | 257–279 |
| 2 | Service | `app/Services/GradeRangeResolver.php` | 133–150 |
| 3 | Controller | `app/Filament/Resources/LearningObjectiveResource.php` | 34–45 |
| 3 | View | `app/Filament/Resources/LearningObjectiveResource.php` | 136–141 |
| 4 | View | `app/Filament/Resources/LessonJournalResource.php` | 64–92 |
| 4 | Controller | `.../RelationManagers/AttendancesRelationManager.php` | 171–267 |
| 4 | Observer | `app/Observers/AttendanceObserver.php` | 34–85 |
| 5 | Controller | `.../RelationManagers/AssessmentsRelationManager.php` | 198–207, 290–351 |
| 5 | Model | `app/Models/TeachingAssignment.php` | 158–238 |
| 5 | Service | `app/Services/GradeRangeResolver.php` | 31–45 |
| 5 | Observer | `app/Observers/GradeObserver.php` | 36–85 |
| 6 | Controller | `app/Filament/Resources/RaporResource/Pages/ViewRapor.php` | 123, 253, 346, 416 |
| 6 | Controller (HTTP) | `app/Http/Controllers/RaporPrintController.php` | 13–34 |
| 6 | Service | `app/Services/RaporExportService.php` | 18–90 |
| 6 | Service | `app/Services/DescriptionGeneratorService.php` | 33–53 |
| 7 | Service | `app/Services/NilaiVisualisasiService.php` | 175–247 |
| 8 | Controller | `app/Filament/Pages/Student/MyGrades.php` | 32–35, 86–146 |
| 8 | Service | `app/Services/NilaiVisualisasiService.php` | 21–49 |
| 9 | Controller | `app/Filament/Pages/Auth/Login.php` | 44–54 |
| 9 | Model | `app/Models/User.php` | 80–84 |
| 6 (pendalaman) | Service — rule engine deskripsi | `app/Services/DescriptionGeneratorService.php` | 63–105, 115–164, 202–220 |
| 8 (pendalaman) | Service — longitudinal | `app/Services/NilaiVisualisasiService.php` | 63–131 |
| 8 (pendalaman) | View — grafik (nonaktif) | `app/Filament/Widgets/NilaiSiswaWidget.php` | 16–19, 41–110 |
| Shared Hosting | Config | `.env.example` | 17–41 |
| Shared Hosting | Service Provider | `app/Providers/AppServiceProvider.php` | 52–68 |
| Shared Hosting | Panel Provider | `app/Providers/Filament/AdminPanelProvider.php` | 118–130 |
| Shared Hosting | Service (batching) | `app/Services/NilaiVisualisasiService.php` | 197–203 |
