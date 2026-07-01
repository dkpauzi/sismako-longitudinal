# Rancangan Sistem Booster Nilai Formatif — SIPDL

> **Status:** rancangan/desain (belum diimplementasikan ke kode). Disusun untuk keperluan skripsi.
> **Keputusan desain (disepakati):** booster berlaku **konsisten di nilai akhir & skor per-TP (narasi)**; setelan disimpan **per SK Mengajar (`teaching_assignments`)**; penggabungan beberapa formatif pada Mode 1 bersifat **akumulatif dengan batas (cap) 100**.

---

## 1. Latar Belakang

Saat ini (as-is):
- `TeachingAssignment::calculateFinalGrade()` menghitung nilai akhir **hanya dari asesmen sumatif** (3 formula: `average` / `weighting` / `percentage`). Docblock menyebut "Booster Formatif", tetapi **TAHAP 2 tidak menambahkan kontribusi formatif** — jadi booster **belum aktif** (`app/Models/TeachingAssignment.php:161, 233-234`).
- `DescriptionGeneratorService::calculateScorePerTp()` justru **mencampur** nilai formatif & sumatif tanpa pembobotan saat menghitung skor per-TP untuk narasi (`app/Services/DescriptionGeneratorService.php:84-95`) — inilah yang disorot dosen.

Rancangan ini **mengaktifkan booster** secara terkontrol, sekaligus **menyelaraskan** kedua jalur perhitungan agar konsisten.

## 2. Tujuan

1. Memberi guru opsi menambahkan kontribusi **nilai formatif** ke **skor sumatif** (sebagai insentif proses belajar) tanpa membuat formatif mendominasi.
2. Menyediakan **dua mekanisme** booster yang bisa dipilih, atau **dimatikan** sepenuhnya.
3. Menjaga **konsistensi**: nilai akhir rapor dan skor per-TP (narasi) memakai aturan booster yang sama.

## 3. Mode Booster (diatur per SK Mengajar)

| Mode | Nama | Perilaku | Cocok untuk |
|------|------|----------|-------------|
| `none` | **Nonaktif** (default) | Formatif tidak menambah apa pun. **Identik perilaku as-is** (aman, backward-compatible). | — |
| `weight` | **Bobot Persen** | Tambahan = `nilai_formatif × (booster_value% / 100)` per formatif, **diakumulasikan**. | Formatif bernilai angka (mis. `formatif_deskripsi` berisi skor 0–100). |
| `point` | **Poin Tetap** | Tiap formatif **terisi** memberi `+booster_value` poin tetap (berapa pun nilainya). | Formatif ceklis/keaktifan (mis. `formatif_poin`). |

> "Formatif terisi" = memiliki `grade.score` yang **tidak null dan > 0**.
> "Nilai akhir" dan "skor per-TP" sama-sama **dibatasi maksimal 100** setelah booster.

## 4. Formula & Contoh Perhitungan

### 4.1 Skor per-TP (untuk narasi) — sekaligus memperbaiki pencampuran formatif/sumatif

Untuk satu TP `t`:
1. **Basis sumatif** `S_t` = rata-rata skor asesmen **sumatif** yang tertaut `t` (formatif **tidak lagi** masuk basis).
2. **Booster** `B_t` dari formatif yang tertaut `t`:
   - Mode `weight`: `B_t = Σ (score_f × value/100)` untuk tiap formatif `f` terisi.
   - Mode `point`: `B_t = (jumlah formatif terisi) × value`.
   - Mode `none`: `B_t = 0`.
3. **Skor TP akhir** = `min(100, S_t + B_t)` → dikonversi ke predikat A–E oleh `GradeRangeResolver`.

### 4.2 Nilai akhir rapor

1. **Skor sumatif** `summativeScore` = hasil formula `grading_formula` (seperti sekarang, TAHAP 1).
2. **Booster total** `B` dari **seluruh** asesmen formatif di SK Mengajar:
   - Mode `weight`: `B = Σ (score_f × value/100)`.
   - Mode `point`: `B = (jumlah formatif terisi) × value`.
3. **Nilai akhir** = `round( min(100, summativeScore + B) )`.

### 4.3 Contoh

Misal TP "Aljabar": sumatif UH1=80, UH2=60 → `S_t = 70`. Formatif tertaut: F1=100, F2=80 (keduanya terisi).

- **Mode `weight`, value = 20%:**
  `B_t = 100×0,2 + 80×0,2 = 20 + 16 = 36` → skor TP = `min(100, 70 + 36) = 100` (kena cap).
  *(Jika value = 10%: `B_t = 10 + 8 = 18` → `70 + 18 = 88`.)*
- **Mode `point`, value = 2:**
  `B_t = 2 formatif × 2 = 4` → skor TP = `70 + 4 = 74`.
- **Mode `none`:** skor TP = `70`.

> Catatan akumulasi (sesuai keputusan): pada Mode `weight`, banyak formatif **menumpuk** dan bisa cepat mencapai cap 100. Disarankan guru memakai `value` kecil (mis. 5–10%). Akan diberi *helperText* peringatan di UI.

## 5. Perubahan Struktur Data (rencana migrasi)

Tabel `teaching_assignments` (+2 kolom):

| Kolom | Tipe | Keterangan |
|-------|------|------------|
| `booster_mode` | `ENUM('none','weight','point')` `DEFAULT 'none'` | Mode booster aktif. Default `none` → kompatibel dengan data lama. |
| `booster_value` | `DECIMAL(5,2)` `NULLABLE` | Mode `weight` → persen (mis. 20.00); Mode `point` → poin per formatif (mis. 2.00). Diabaikan saat `none`. |

> Tidak ada perubahan pada `grades`/`assessments` — nilai formatif tetap disimpan seperti sekarang (`formatif_deskripsi` skor opsional; `formatif_poin` skor = poin). Booster hanya **membaca** nilai yang sudah ada.

## 6. Perubahan Logika (pseudocode)

### 6.1 `TeachingAssignment::calculateFinalGrade()` — sisipkan TAHAP 1b
```text
// ... TAHAP 1: hitung $summativeScore (tetap, sumatif saja) ...

// TAHAP 1b (BARU): kontribusi booster formatif
$booster = 0;
$formatives = $assessments->filter(kategori diawali 'formatif');
if ($this->booster_mode === 'weight') {
    foreach ($formatives as $f) {
        $g = $f->grades->first();
        if ($g && $g->score > 0) $booster += $g->score * ($this->booster_value / 100);
    }
} elseif ($this->booster_mode === 'point') {
    $terisi = $formatives->filter(fn($f) => ($f->grades->first()->score ?? 0) > 0)->count();
    $booster = $terisi * $this->booster_value;
}

// TAHAP 2: gabung + cap + pembulatan
$finalGrade = min(100, $summativeScore + $booster);
return (float) round($finalGrade);
```

### 6.2 `DescriptionGeneratorService::calculateScorePerTp()` — pisahkan basis & booster
```text
foreach (TP $lo) {
    // BASIS: hanya sumatif yang tertaut $lo
    $sumScores = $assessments
        ->filter(sumatif AND learningObjectives->contains($lo->id))
        ->flatMap(grades->pluck('score'));   // (whereNotNull)
    if ($sumScores->isEmpty()) continue;     // TP tanpa sumatif → dilewati
    $base = round($sumScores->avg(), 2);

    // BOOSTER: formatif yang tertaut $lo
    $forms = $assessments->filter(formatif AND learningObjectives->contains($lo->id));
    $b = 0;
    if (mode weight) foreach ($forms as $f) if (score>0) $b += score * value/100;
    if (mode point)  $b = (jumlah formatif terisi) * value;

    $average = min(100, $base + $b);
    // ... lanjut konversi predikat & narasi seperti biasa ...
}
```

### 6.3 Re-kalkulasi saat setelan berubah
Pada `TeachingAssignment::booted()` (event `updated`): jika `booster_mode` atau `booster_value` berubah, jalankan **re-hitung `FinalGrade`** untuk seluruh siswa di SK Mengajar tersebut (mengikuti pola yang sudah ada saat `kktp` berubah → `GradeRangeResolver::seedDefaults`). Hormati `is_locked` / `is_manual_override` (jangan timpa nilai terkunci/override).

## 7. Perubahan Antarmuka (UI)

Pada form **SK Mengajar** (`TeachingAssignmentResource::form`):
- `Select booster_mode` → opsi: *Nonaktif* / *Bobot Persen* / *Poin Tetap*.
- `TextInput booster_value` (numeric) → **muncul hanya** jika `booster_mode !== 'none'`; label & `helperText` dinamis:
  - `weight`: "Persentase per nilai formatif (%)" + peringatan akumulasi.
  - `point`: "Poin tetap per formatif terisi".
- Validasi: `booster_value` `required` & `> 0` saat mode aktif.

> Catatan: field bobot per-asesmen yang ada sekarang (`AssessmentsRelationManager`, `minValue(1)`) tetap untuk **bobot sumatif** (`grading_formula = weighting`). Booster formatif adalah mekanisme terpisah di level SK Mengajar.

## 8. Dampak & Kompatibilitas

- **Backward-compatible:** default `booster_mode = 'none'` membuat seluruh SK Mengajar lama berperilaku **persis seperti sekarang** (nilai akhir = sumatif murni).
- **Memperbaiki temuan dosen:** `calculateScorePerTp` tidak lagi mencampur formatif ke basis; formatif hanya masuk via booster yang eksplisit & dapat dimatikan.
- **Konsistensi:** nilai akhir & narasi per-TP memakai aturan booster identik.
- **Snapshot:** karena `final_grades` adalah snapshot, perubahan setelan booster perlu memicu re-hitung (lihat 6.3).

## 9. Edge Cases

1. **Tidak ada formatif / belum terisi** → `B = 0`, nilai = sumatif murni.
2. **Cap 100** diterapkan di kedua jalur agar predikat & nilai tidak melebihi skala.
3. **Skor null** dilewati (tidak dianggap 0).
4. **Mode `weight` pada `formatif_poin`** (skor = poin kecil seperti 1) kurang bermakna → diarahkan lewat *helperText* agar `formatif_poin` memakai Mode `point`.
5. **Nilai terkunci** (`is_locked`) / **override** (`is_manual_override`) → re-hitung booster tidak menimpa.
6. **Formatif tanpa TP** → tidak berkontribusi pada skor per-TP (tak bisa diatribusikan); pada nilai akhir tetap dihitung sebagai bagian "seluruh formatif".

## 10. Rencana Pengujian (acuan Bab Pengujian)

| Skenario | Input | Ekspektasi |
|----------|-------|-----------|
| Mode none | sumatif 70, formatif 100 | nilai akhir 70 |
| Mode weight 20% | sumatif 70, 1 formatif 100 | nilai akhir 90 |
| Mode weight akumulasi | sumatif 70, formatif 100 & 80 @20% | nilai akhir 100 (cap) |
| Mode point 2 | sumatif 70, 2 formatif terisi | nilai akhir 74 |
| Konsistensi narasi | data sama | skor per-TP = nilai akhir-logika (sama-sama +booster, cap 100) |
| Ubah setelan | ganti mode/value | `FinalGrade` ter-hitung ulang (kecuali terkunci) |

---

## 11. Daftar Berkas yang Akan Disentuh (saat implementasi nanti)

| Lapisan | Berkas | Perubahan |
|---------|--------|-----------|
| Migrasi | `database/migrations/xxxx_add_booster_to_teaching_assignments.php` (baru) | tambah `booster_mode`, `booster_value` |
| Model | `app/Models/TeachingAssignment.php` | `$fillable`, `calculateFinalGrade()` (TAHAP 1b + cap), `booted()` (re-hitung on update) |
| Service | `app/Services/DescriptionGeneratorService.php` | `calculateScorePerTp()` (pisah basis sumatif + booster) |
| View | `app/Filament/Resources/TeachingAssignmentResource.php` | field `booster_mode` + `booster_value` |
| (Docs) | `pembahasan-pengkodean.md`, `potongan_code.md` | sinkronkan setelah implementasi |

> Implementasi **belum dijalankan**. Setelah rancangan ini Anda setujui, langkah berikutnya: buat migrasi + ubah `calculateFinalGrade` & `calculateScorePerTp`, lalu uji sesuai bagian 10.
