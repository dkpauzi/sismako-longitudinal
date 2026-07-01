# Rancangan Implementasi & Unit Test — Sistem Booster Formatif

> Status: **rancangan** (belum menyentuh kode aplikasi). Acuan desain: `docs/rancangan-booster-formatif.md`.
> Semua potongan kode di bawah adalah **usulan** untuk ditulis saat implementasi.

---

## 0. Temuan & Keputusan Awal

1. **Test gantung.** `tests/Unit/CalculateFinalGradeTest.php` sudah ada dan memakai kolom `use_formative_boost` + `formative_boost_percentage` yang **tidak ada** di migrasi/model, serta menguji booster yang **belum diimplementasikan**. Akibatnya beberapa test booster (no. 4, 5, 8) **gagal** saat ini.
2. **Penamaan kolom.** Desain lama hanya mendukung 1 mode (persen). Karena target 2 mode (`weight` + `point`), dipakai **`booster_mode` (enum) + `booster_value` (decimal)**. Secara matematis Mode `weight` identik dengan formula test lama (`Σ nilai_formatif × %`), sehingga assertion lama tetap berlaku (tinggal ganti nama kolom di helper).
3. **Kompatibilitas migrasi.** Hindari modifier `->after()` (mengikuti konvensi repo agar kompatibel MySQL & SQLite `:memory:` untuk test).

---

# BAGIAN A — Rancangan Implementasi

## A.1 Migrasi (pendekatan bersih — edit migrasi asli)

Karena memakai `migrate:fresh`, kolom booster ditambahkan **langsung** pada migrasi pembuat tabel, **bukan** migrasi `ALTER` terpisah. Edit blok `Schema::create('teaching_assignments', ...)` di `database/migrations/2026_02_17_054129_create_kbm_and_assessment_tables.php` — tambahkan 2 baris setelah `subject_type`:
```php
        // 1. SK MENGAJAR (Teaching Assignments)
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();

            // Config Penilaian Dasar
            $table->string('grading_formula')->default('average');
            $table->integer('kktp')->default(75)->nullable();
            $table->enum('subject_type', ['mandatory', 'kokurikuler', 'elective', 'extracurricular'])
                ->nullable();

            // --- BOOSTER NILAI FORMATIF (BARU) ---
            // weight → persen (mis. 20.00); point → poin per formatif (mis. 2.00)
            $table->enum('booster_mode', ['none', 'weight', 'point'])->default('none');
            $table->decimal('booster_value', 5, 2)->nullable();

            $table->timestamps();
        });
```
> Keunggulan: tidak ada migrasi `ALTER` terpisah, tidak perlu modifier `->after()`, dan skema tetap tunggal/rapi. Terapkan dengan **`php artisan migrate:fresh --seed`**.
> **Catatan:** `migrate:fresh` **menghapus seluruh data** — sesuai kesepakatan (lingkungan dev/skripsi). Untuk test otomatis tidak berdampak: `RefreshDatabase` selalu membangun skema dari nol.

## A.2 Model `app/Models/TeachingAssignment.php`

**(a) `$fillable` + `$casts`** — tambahkan:
```php
protected $fillable = [
    // ... yang sudah ada ...
    'booster_mode',
    'booster_value',
];

protected $casts = [
    'subject_type'  => 'string',
    'kktp'          => 'integer',
    'booster_value' => 'decimal:2',   // ← baru
];
```

**(b) Helper terpusat** — dipakai oleh `calculateFinalGrade()` dan `DescriptionGeneratorService` agar logika booster tidak duplikat:
```php
/**
 * Hitung kontribusi booster dari sekumpulan nilai formatif.
 * $formativeScores: koleksi skor formatif (boleh mengandung null).
 */
public function boosterContribution(\Illuminate\Support\Collection $formativeScores): float
{
    // "Terisi" = tidak null dan > 0
    $scores = $formativeScores->filter(fn ($s) => $s !== null && (float) $s > 0);

    return match ($this->booster_mode) {
        'weight' => (float) $scores->sum(fn ($s) => (float) $s * ((float) $this->booster_value / 100)),
        'point'  => (float) $scores->count() * (float) $this->booster_value,
        default  => 0.0,   // 'none'
    };
}
```

**(c) `calculateFinalGrade()`** — sisipkan **TAHAP 1b** & ubah **TAHAP 2** (bagian TAHAP 1 sumatif tetap):
```php
        // ... TAHAP 1: hasilkan $summativeScore (tidak berubah) ...

        // --- TAHAP 1b: BOOSTER FORMATIF (BARU) ---
        $formativeScores = $assessments
            ->filter(fn ($a) => str_starts_with($a->category, 'formatif'))
            ->map(fn ($a) => $a->grades->first()?->score);

        $booster = $this->boosterContribution($formativeScores);

        // --- TAHAP 2: GABUNG + CAP 100 + PEMBULATAN ---
        $finalGrade = min(100, $summativeScore + $booster);

        return (float) round($finalGrade);
```

**(d) Re-kalkulasi saat setelan booster diubah** — pada `booted()`:
```php
static::updated(function (TeachingAssignment $model) {
    if ($model->wasChanged('kktp')) {
        GradeRangeResolver::seedDefaults($model);
    }
    // BARU: setelan booster berubah → hitung ulang nilai akhir
    if ($model->wasChanged(['booster_mode', 'booster_value'])) {
        $model->recalculateFinalGrades();
    }
});
```
```php
/**
 * Hitung ulang FinalGrade seluruh siswa aktif di SK Mengajar ini.
 * Menghormati is_locked & is_manual_override (tidak menimpa).
 */
public function recalculateFinalGrades(): void
{
    $semester   = $this->academicPeriod->semester;
    $studentIds = \App\Models\Enrollment::where('classroom_id', $this->classroom_id)
        ->where('academic_period_id', $this->academic_period_id)
        ->where('status', 'active')
        ->pluck('student_id');

    foreach ($studentIds as $studentId) {
        $existing = FinalGrade::where('student_id', $studentId)
            ->where('teaching_assignment_id', $this->id)
            ->where('semester', $semester)->first();

        if ($existing?->is_locked || $existing?->is_manual_override) {
            continue;
        }

        $score = $this->calculateFinalGrade($studentId);
        FinalGrade::updateOrCreate(
            ['student_id' => $studentId, 'teaching_assignment_id' => $this->id, 'semester' => $semester],
            [
                'final_score' => $score > 0 ? $score : null,
                'grade_label' => $score > 0 ? GradeRangeResolver::resolve($this, $score) : null,
            ]
        );
    }
}
```

## A.3 Service `app/Services/DescriptionGeneratorService.php`

Ubah `calculateScorePerTp()` — **basis sumatif saja** + booster per TP (memperbaiki pencampuran formatif/sumatif):
```php
    return $learningObjectives->map(function (LearningObjective $lo) use ($assessments, $assignment) {
        // BASIS: hanya asesmen SUMATIF yang tertaut TP ini
        $sumScores = $assessments
            ->filter(fn ($a) => str_starts_with($a->category, 'sumatif')
                              && $a->learningObjectives->contains('id', $lo->id))
            ->flatMap(fn ($a) => $a->grades->pluck('score'));

        if ($sumScores->isEmpty()) {
            return null; // TP tanpa nilai sumatif → dilewati
        }
        $base = round($sumScores->avg(), 2);

        // BOOSTER: formatif yang tertaut TP ini
        $formScores = $assessments
            ->filter(fn ($a) => str_starts_with($a->category, 'formatif')
                              && $a->learningObjectives->contains('id', $lo->id))
            ->flatMap(fn ($a) => $a->grades->pluck('score'));

        $average = min(100, $base + $assignment->boosterContribution($formScores));

        return [
            'id'            => $lo->id,
            'code'          => $lo->code,
            'attribute'     => $lo->attribute,
            'average_score' => $average,
            'is_tuntas'     => $average >= $assignment->kktp_or_default,
        ];
    })->filter()->values();
```
> Catatan: parameter `$kktp` lama boleh diganti `$assignment->kktp_or_default`. Signature `generate()` yang memanggilnya tidak berubah.

## A.4 UI `app/Filament/Resources/TeachingAssignmentResource.php`

Tambahkan pada `form()` (di grup konfigurasi penilaian):
```php
Forms\Components\Select::make('booster_mode')
    ->label('Booster Nilai Formatif')
    ->options([
        'none'   => 'Nonaktif — nilai formatif tidak menambah',
        'weight' => 'Bobot Persen — nilai_formatif × %',
        'point'  => 'Poin Tetap — per formatif terisi',
    ])
    ->default('none')
    ->live()
    ->required(),

Forms\Components\TextInput::make('booster_value')
    ->label(fn (Get $get) => $get('booster_mode') === 'point'
        ? 'Poin per formatif terisi' : 'Persentase per nilai formatif (%)')
    ->numeric()
    ->minValue(0)
    ->helperText(fn (Get $get) => $get('booster_mode') === 'weight'
        ? 'Kontribusi tiap formatif diakumulasi & dibatasi maksimal 100. Disarankan nilai kecil (5–10%).'
        : ($get('booster_mode') === 'point' ? 'Contoh: 2 → tiap formatif terisi menambah 2 poin.' : null))
    ->visible(fn (Get $get) => $get('booster_mode') !== 'none')
    ->required(fn (Get $get) => $get('booster_mode') !== 'none'),
```

## A.5 Urutan langkah eksekusi
1. Edit migrasi pembuat `teaching_assignments` (A.1) → jalankan **`php artisan migrate:fresh --seed`**.
2. Ubah Model `TeachingAssignment` (fillable, casts, `boosterContribution`, TAHAP 1b, `booted`, `recalculateFinalGrades`).
3. Ubah `DescriptionGeneratorService::calculateScorePerTp`.
4. Tambah field UI di `TeachingAssignmentResource`.
5. Selaraskan & jalankan unit test (Bagian B).
6. Perbarui `potongan_code.md` dengan snippet + nomor baris **asli** (setelah kode final).

---

# BAGIAN B — Rancangan Unit Test

## B.1 Kerangka
- Framework: **PHPUnit 10** (`Tests\TestCase`, `RefreshDatabase`), DB `sqlite :memory:` (sudah dikonfigurasi di `phpunit.xml`).
- Berkas:
  - **Perbarui** `tests/Unit/CalculateFinalGradeTest.php` (ganti kolom boost → `booster_mode`/`booster_value`, tambah mode `point`).
  - **Baru** `tests/Unit/CalculateScorePerTpBoosterTest.php` (konsistensi narasi & perbaikan pencampuran).
  - **Baru (opsional, Feature)** `tests/Feature/BoosterRecalculationTest.php` (re-kalkulasi saat setelan berubah).

## B.2 Perbarui helper di `CalculateFinalGradeTest.php`
```php
private function createAssignment(
    string $formula = 'average',
    int $kktp = 75,
    string $boosterMode = 'none',
    float $boosterValue = 0,
): TeachingAssignment {
    return TeachingAssignment::create([
        'academic_period_id' => $this->academicPeriod->id,
        'teacher_id'         => $this->teacher->id,
        'subject_id'         => $this->subject->id,
        'classroom_id'       => $this->classroom->id,
        'grading_formula'    => $formula,
        'kktp'               => $kktp,
        'booster_mode'       => $boosterMode,   // ← ganti dari use_formative_boost
        'booster_value'      => $boosterValue,  // ← ganti dari formative_boost_percentage
    ]);
}
```
Helper `createAssessmentWithGrade()` tetap seperti sekarang.

## B.3 Kasus uji `calculateFinalGrade()`

| # | Nama test | Arrange (sumatif / formatif) | Setelan booster | Ekspektasi |
|---|-----------|------------------------------|-----------------|-----------|
| 1 | average dua asesmen | 80, 90 | none | **85.0** |
| 2 | weighting persen | 80@40%, 90@60% | none | **86.0** |
| 3 | percentage KKTP | 80, 60, 90 (kktp 75) | none | **67.0** |
| 4 | weight menambah | sumatif 80,90; formatif 2,3 | weight, 20 | **86.0** — (2+3)×20%=1 |
| 5 | weight cap 100 | sumatif 99,99; formatif 10 | weight, 50 | **100.0** — 99+5=104→cap |
| 6 | none mengabaikan formatif | sumatif 80,90; formatif 10 | none | **85.0** |
| 7 | weight tanpa sumatif | formatif 5 | weight, 20 | **1.0** — 0+1 |
| 8 | **point menambah** | sumatif 80,90; 2 formatif terisi | point, 2 | **89.0** — 85+ (2×2)=4 |
| 9 | **point cap 100** | sumatif 99,99; 3 formatif terisi | point, 2 | **100.0** — 99+6=105→cap |
| 10 | **point abaikan skor 0/null** | sumatif 80,90; formatif 0 & null | point, 2 | **85.0** — 0 formatif "terisi" |
| 11 | tanpa asesmen | — | none | **0.0** |
| 12 | abaikan null sumatif | 80, null | none | **80.0** |
| 13 | return float | 100 | none | `assertIsFloat` |

Contoh implementasi 2 test baru (mode `point`):
```php
public function test_point_mode_adds_fixed_points_per_filled_formative(): void
{
    $assignment = $this->createAssignment(boosterMode: 'point', boosterValue: 2);
    $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
    $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 90);
    $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 1);   // terisi
    $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 1);   // terisi

    // 85 + (2 formatif × 2) = 89
    $this->assertEquals(89.0, $assignment->calculateFinalGrade($this->student->id));
}

public function test_point_mode_ignores_zero_and_null_formative(): void
{
    $assignment = $this->createAssignment(boosterMode: 'point', boosterValue: 2);
    $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
    $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 90);
    $this->createAssessmentWithGrade($assignment, 'formatif_poin', 0, 0);     // tidak terisi
    $this->createAssessmentWithGrade($assignment, 'formatif_deskripsi', 0, null); // null

    // Tidak ada formatif "terisi" → 85
    $this->assertEquals(85.0, $assignment->calculateFinalGrade($this->student->id));
}
```

## B.4 Test baru — `CalculateScorePerTpBoosterTest.php` (konsistensi narasi)

Tujuan: membuktikan (a) basis per-TP **tidak lagi** mencampur formatif, (b) booster diterapkan sama seperti nilai akhir.

Kebutuhan setup tambahan: `LearningObjective` + pivot `assessment_learning_objective` (tautkan asesmen ke TP). Pola:
```php
$lo = LearningObjective::create([
    'subject_id' => $this->subject->id,
    'academic_period_id' => $this->academicPeriod->id,
    'phase' => 'D', 'attribute' => 'Aljabar', 'code' => 'MTK-7-1-TP1',
]);
$assessment->learningObjectives()->attach($lo->id);   // tautkan
```

| # | Skenario | Data (tertaut TP) | Booster | Ekspektasi skor TP |
|---|----------|-------------------|---------|--------------------|
| 1 | basis sumatif murni | sumatif 80,60 ; formatif 100 | none | **70** (formatif TIDAK menurunkan) |
| 2 | weight per-TP | sumatif 80,60 ; formatif 100 | weight, 10 | **80** — 70 + 100×10% |
| 3 | point per-TP | sumatif 80,60 ; 1 formatif terisi | point, 2 | **72** — 70 + 2 |
| 4 | cap per-TP | sumatif 95,95 ; formatif 100 | weight, 20 | **100** (95+20 capped) |
| 5 | konsistensi vs nilai akhir | data sama | weight, 10 | skor TP == pola `calculateFinalGrade` (sama-sama +booster, cap 100) |

Contoh:
```php
public function test_per_tp_base_excludes_formative_when_booster_none(): void
{
    $assignment = $this->createAssignmentWithTp(boosterMode: 'none');   // helper lokal
    // sumatif tertaut TP: 80, 60 → basis 70 ; formatif 100 tertaut TP → harus DIABAIKAN
    // ... attach assessments ke $lo ...

    $service = new \App\Services\DescriptionGeneratorService();
    $tp = $service->calculateScorePerTp($assignment, $this->student->id, 75)->first();

    $this->assertEquals(70, $tp['average_score']); // bukan (80+60+100)/3 = 80
}
```

## B.5 Test opsional (Feature) — re-kalkulasi
`BoosterRecalculationTest.php`:
- Buat SK Mengajar + nilai → `FinalGrade` awal (mis. 85, mode none).
- Ubah `booster_mode='point', booster_value=2` (dengan formatif terisi) → assert `FinalGrade` ter-update (mis. 89).
- Set `FinalGrade.is_locked = true`, ubah setelan lagi → assert nilai **tidak berubah** (dihormati).

## B.6 Menjalankan
```bash
php artisan test --filter=CalculateFinalGradeTest
php artisan test tests/Unit/CalculateScorePerTpBoosterTest.php
php artisan test                       # seluruh suite (pastikan tak ada regresi)
```

---

## C. Daftar Berkas (ringkas)
| Aksi | Berkas |
|------|--------|
| Ubah | `database/migrations/2026_02_17_054129_create_kbm_and_assessment_tables.php` (tambah 2 kolom di blok `teaching_assignments`) — lalu `migrate:fresh` |
| Ubah | `app/Models/TeachingAssignment.php` |
| Ubah | `app/Services/DescriptionGeneratorService.php` |
| Ubah | `app/Filament/Resources/TeachingAssignmentResource.php` |
| Ubah | `tests/Unit/CalculateFinalGradeTest.php` |
| Baru | `tests/Unit/CalculateScorePerTpBoosterTest.php` |
| Baru (opsional) | `tests/Feature/BoosterRecalculationTest.php` |
| Sesudah implementasi | perbarui `docs/potongan_code.md` (snippet + baris asli) |
