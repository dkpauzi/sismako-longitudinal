# Panduan Testing — SIPDL (Sistem Informasi Pendidikan Longitudinal)

> Dokumen ini menjelaskan bagaimana automated testing bekerja dalam proyek ini.
> Cocok untuk developer yang baru pertama kali mengimplementasikan pengujian otomatis.

---

## 1. Arsitektur Testing

```
tests/
├── TestCase.php              ← Base class, semua test inherit dari sini
├── Unit/
│   ├── ExampleTest.php       ← Test bawaan Laravel
│   └── CalculateFinalGradeTest.php  ← Test yang kita buat
└── Feature/
    └── ...                   ← Integration test (HTTP, browser, dll)
```

### Unit Test vs Feature Test

| Aspek | Unit Test | Feature Test |
|-------|-----------|-------------|
| **Scope** | 1 method / 1 class | Seluruh request flow (HTTP) |
| **Kecepatan** | Sangat cepat (milidetik) | Lebih lambat |
| **Database** | Minimal data | Sering butuh data lengkap |
| **Contoh** | `calculateFinalGrade()` | "Login → buka halaman → lihat nilai" |

Test yang kita tulis (`CalculateFinalGradeTest`) adalah **Unit Test** karena hanya menguji 1 method secara terisolasi.

---

## 2. Setup Environment Testing

### 2.1 Database In-Memory (SQLite)

File `phpunit.xml` sudah dikonfigurasi agar test menggunakan database SQLite di memori:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**Mengapa SQLite in-memory?**

| Keuntungan | Penjelasan |
|-----------|------------|
| **Kecepatan** | Database hidup di RAM, tidak menulis ke disk |
| **Isolasi** | Data MySQL lokal Anda aman — test tidak menyentuhnya |
| **Clean state** | Setiap test dimulai dengan database kosong |
| **Portable** | Tidak perlu setup database tambahan |

### 2.2 RefreshDatabase Trait

Setiap test class menggunakan trait `RefreshDatabase`:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class CalculateFinalGradeTest extends TestCase
{
    use RefreshDatabase;
    // ...
}
```

Trait ini:
1. Menjalankan **semua migration** di awal test suite
2. Membungkus setiap test dalam **database transaction**
3. Melakukan **rollback** setelah test selesai → database kembali bersih

---

## 3. Anatomi Sebuah Test: Arrange → Act → Assert

Setiap test mengikuti pola **AAA** (Arrange, Act, Assert):

```php
public function test_average_formula_with_two_assessments(): void
{
    // ── ARRANGE (Siapkan data) ──
    $assignment = $this->createAssignment(formula: 'average');
    $this->createAssessmentWithGrade($assignment, 'sumatif_lingkup_materi', 0, 80);
    $this->createAssessmentWithGrade($assignment, 'sumatif_akhir_semester', 0, 90);

    // ── ACT (Jalankan method yang diuji) ──
    $result = $assignment->calculateFinalGrade($this->student->id);

    // ── ASSERT (Verifikasi hasilnya) ──
    $this->assertEquals(85.0, $result);
}
```

### 3.1 ARRANGE — Persiapan Data

**Apa yang terjadi di `setUp()`?**

Method `setUp()` dijalankan otomatis **sebelum setiap test**. Di sinilah kita membuat data referensi (parent tables) yang dibutuhkan oleh foreign key:

```
AcademicPeriod → Classroom → Subject → Teacher → Student
```

Tanpa data ini, `TeachingAssignment::create()` akan gagal karena foreign key constraint.

**Mengapa menggunakan helper method?**

```php
private function createAssignment(string $formula = 'average', ...): TeachingAssignment
```

Helper method menghindari duplikasi kode. Setiap test hanya perlu menentukan parameter yang **berbeda** dari default.

**Mengapa `Grade::withoutEvents()`?**

```php
Grade::withoutEvents(function () use ($assessment, $score) {
    Grade::create([...]);
});
```

Dalam produksi, setiap kali `Grade` dibuat/diupdate, `GradeObserver` otomatis memanggil `calculateFinalGrade()`. Di dalam test, kita ingin mengontrol **kapan** method ini dipanggil — jadi kita mematikan observer saat menyiapkan data.

### 3.2 ACT — Eksekusi

```php
$result = $assignment->calculateFinalGrade($this->student->id);
```

Hanya 1 baris. Kita memanggil method yang ingin diuji dan menyimpan hasilnya.

### 3.3 ASSERT — Verifikasi

```php
$this->assertEquals(85.0, $result);    // ← Apakah nilainya benar?
$this->assertIsFloat($result);         // ← Apakah tipe datanya float?
$this->assertLessThanOrEqual(100, $result); // ← Apakah tidak melebihi 100?
```

Jika assertion gagal, PHPUnit akan menampilkan pesan error yang jelas:

```
FAILED: Expected 85.0, got 84.0
```

---

## 4. Menjalankan Test

### 4.1 Semua Test

```powershell
php artisan test
```

### 4.2 Hanya Unit Test

```powershell
php artisan test --testsuite=Unit
```

### 4.3 File Tertentu

```powershell
php artisan test --filter=CalculateFinalGradeTest
```

### 4.4 Method Tertentu

```powershell
php artisan test --filter=test_average_formula_with_two_assessments
```

### 4.5 Dengan Output Verbose

```powershell
php artisan test --filter=CalculateFinalGradeTest -v
```

---

## 5. Memahami Output Test

### Semua Berhasil ✅

```
PASS  Tests\Unit\CalculateFinalGradeTest
✓ average formula with two assessments                   0.05s
✓ weighting formula with percentage weights              0.03s
✓ percentage formula counts kktp pass rate               0.03s
✓ formative boost adds bonus points                      0.04s
✓ final grade capped at 100                              0.03s
✓ returns zero when no assessments exist                 0.02s
✓ ignores null scores                                    0.03s
✓ only formative assessments without summative           0.03s
✓ formative boost disabled ignores formatif scores       0.03s
✓ return type is always float                            0.02s

Tests:    10 passed (15 assertions)
Duration: 0.42s
```

### Salah Satu Gagal ❌

```
FAIL  Tests\Unit\CalculateFinalGradeTest
✕ average formula with two assessments                   0.05s
  Expected: 85.0
  Actual:   84.0

Tests:    1 failed, 9 passed (15 assertions)
```

**Langkah debugging jika gagal:**
1. Baca pesan error — bandingkan Expected vs Actual
2. Periksa data di `setUp()` dan helper methods
3. Tambahkan `dd($result)` sebelum assertion untuk melihat data aktual
4. Pastikan migration terbaru sudah tercermin di schema

---

## 6. Matriks Test Coverage

Berikut ringkasan skenario yang sudah dicover:

| # | Test Case | Formula | Boost | Expected |
|---|-----------|---------|-------|----------|
| 1 | Rata-rata 2 ujian | `average` | ❌ | 85.0 |
| 2 | Bobot 40/60 | `weighting` | ❌ | 86.0 |
| 3 | Pass rate KKTP 75 | `percentage` | ❌ | 67.0 |
| 4 | Average + formatif poin | `average` | ✅ 20% | 86.0 |
| 5 | Cap nilai > 100 | `average` | ✅ 50% | 100.0 |
| 6 | Tanpa asesmen | `average` | ❌ | 0.0 |
| 7 | Score NULL | `average` | ❌ | 80.0 |
| 8 | Hanya formatif | `average` | ✅ 20% | 1.0 |
| 9 | Boost dimatikan | `average` | ❌ | 85.0 |
| 10 | Return type | `average` | ❌ | `float` |

---

## 7. Kapan Harus Menulis Test Baru?

Tambahkan test baru ketika:

- ✏️ **Mengubah logika bisnis** di `calculateFinalGrade()` — misalnya menambah formula baru
- 🐛 **Menemukan bug** — tulis test yang mereproduksi bug, **baru** perbaiki kodenya
- 🆕 **Menambah fitur** — misalnya "remedial override" atau "pengurangan karena absensi"
- 🔄 **Refactoring** — test yang ada memastikan behavior tidak berubah setelah refactor

### Contoh: Menambah Test untuk Fitur Baru

```php
/**
 * Skenario baru: Remedial menggantikan nilai terendah.
 */
public function test_remedial_replaces_lowest_score(): void
{
    // ── ARRANGE ──
    $assignment = $this->createAssignment(formula: 'average');
    // ... setup data remedial ...

    // ── ACT ──
    $result = $assignment->calculateFinalGrade($this->student->id);

    // ── ASSERT ──
    $this->assertEquals($expectedScore, $result);
}
```

---

## 8. Referensi

| Topik | Link |
|-------|------|
| PHPUnit Docs | https://docs.phpunit.de/ |
| Laravel Testing | https://laravel.com/docs/11.x/testing |
| RefreshDatabase | https://laravel.com/docs/11.x/database-testing#resetting-the-database-after-each-test |
| Assertions | https://docs.phpunit.de/en/10.5/assertions.html |
