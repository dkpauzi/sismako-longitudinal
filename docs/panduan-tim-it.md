# Panduan Sistem untuk Tim IT — SISMAKO
### Sistem Informasi Manajemen Akademik & Rapor · SMP Negeri 45 Sijunjung

> Dokumen serah-terima untuk tim IT sekolah. Menjelaskan **peran pengguna**,
> **alur kerja utama**, **fungsi tiap fitur**, dan **catatan operasional**.
> Tidak memuat data pribadi siswa.

---

## 1. Gambaran Umum

SISMAKO adalah aplikasi web **companion e-Rapor** berbasis Kurikulum Merdeka
(Fase D, kelas 7–9). Fokusnya: mengumpulkan nilai, memantau perkembangan siswa
**lintas tahun (longitudinal)**, dan mencetak rapor.

- **Teknologi:** Laravel 11 + Filament v3 (PHP), database MySQL.
- **Dua wajah aplikasi:**
  1. **Website publik** (`/`) — profil sekolah, berita, galeri (halaman depan).
  2. **Panel aplikasi** (`/admin`) — tempat semua pekerjaan akademik.
- **Prinsip inti — Integritas Longitudinal:** riwayat nilai siswa **tidak boleh
  hilang atau rusak** saat naik kelas / ganti tahun ajaran. Banyak proteksi
  dibangun khusus untuk ini (lihat §7).

### Cara login
Semua peran login di **`/admin/login`** (atau `/login`) dengan:
- **Username** = **NISN** (siswa), **NIP** (guru), atau **Email** (admin).
- Password awal dibuat otomatis sama dengan username saat akun diprovisi.

---

## 2. Peran Pengguna (Role)

Sistem memakai 7 peran. Hak akses ditentukan **peran**, bukan per-orang.

| Peran | Untuk siapa | Ringkas hak akses |
|---|---|---|
| **super_admin** | Teknisi/IT | Akses penuh, termasuk manajemen akun & peran. |
| **admin** | Tata Usaha (TU) | Data induk (siswa, guru, kelas, mapel), impor, naik kelas, kelulusan. |
| **headmaster** (Kepala Sekolah) | Kepsek | **Hanya memantau** — dashboard, rekap, grafik, kinerja guru. Tidak mengubah data. |
| **teacher** (Guru) | Guru mapel & wali kelas | Kelas ajarnya sendiri: input nilai, TP, remedial, narasi. Sebagai **wali kelas** juga: kunci rapor, nilai P5 & ekskul. |
| **guru_bk** | Guru BK | Catatan konseling + kuesioner gaya belajar (VAK). |
| **student** (Siswa) | Siswa | **Hanya lihat** data sendiri: jadwal, nilai, grafik, rapor bayangan. |
| **guardian** (Wali Siswa) | Orang tua | **Hanya lihat** data anaknya (bisa lebih dari satu anak). |

> **Penting untuk IT:** peran ditetapkan lewat menu **Manajemen Akun**. Saat
> impor massal, sistem HANYA memberi peran `student`/`guardian` (impor siswa)
> atau `teacher` (impor guru). Peran `admin`, `headmaster`, `guru_bk` **wajib
> diberikan manual** — ini pencegahan agar tidak ada yang menaikkan hak akses
> sendiri lewat file Excel.

### "Wali Kelas" itu status, bukan peran
Seorang **guru** menjadi **wali kelas** ketika ditetapkan di suatu kelas pada
tahun ajaran tertentu. Status ini yang membuka akses: kunci rapor, nilai P5,
nilai ekskul untuk kelas asuhannya. Wali kelas tahun lalu **tidak** bisa lagi
mengubah nilai tahun berjalan (proteksi longitudinal).

---

## 3. Struktur Menu (Panel `/admin`)

Menu tampil sesuai peran. Kelompok utama:

| Grup Menu | Isi |
|---|---|
| **Manajemen Sistem** | Tahun Ajaran, Manajemen Akun, Pengaturan Sekolah |
| **Akademik** | Data Siswa, Data Guru, Mata Pelajaran, Ruang Kelas, Kelas Ajar (SK Mengajar), Tujuan Pembelajaran (TP), Jurnal KBM, Nilai P5, Nilai Ekstrakurikuler, Rekap Rapor, Grafik Nilai Siswa, Proses Kenaikan Kelas, Lanjut Semester |
| **Bimbingan Konseling** | Rekam Bimbingan, Kuesioner VAK, Hasil VAK Siswa |
| **Web Sekolah** | Profil Sekolah, Berita/Postingan, Kegiatan, Struktur Organisasi |
| **Pengaturan** | Setelan sekolah (KKM default, dsb.) |

---

## 4. Alur Kerja Utama (Urutan Wajib)

Ini urutan penggunaan sistem dari awal tahun ajaran sampai kelulusan.
**Urutannya penting** karena tiap langkah bergantung pada langkah sebelumnya.

### Tahap A — Persiapan (oleh Admin/TU, awal tahun ajaran)
1. **Tahun Ajaran** → pastikan periode yang benar berstatus **aktif**
   (hanya boleh SATU yang aktif).
2. **Ruang Kelas** → buat kelas (mis. 7, 8, 9).
3. **Mata Pelajaran** → daftar mapel (biasanya sudah tersedia).
4. **Data Guru** → impor CSV atau input manual (akun guru dibuat otomatis).
5. **Data Siswa** → impor CSV atau input manual (akun siswa **dan** wali dibuat
   otomatis; username = NISN).
6. **Kelas Ajar (SK Mengajar)** → tetapkan guru mengajar mapel apa di kelas apa.
   Di sini juga ditentukan **wali kelas** dan **jadwal pelajaran**.
7. **Tujuan Pembelajaran (TP)** → tiap guru mendefinisikan TP per mapel.
   *Jika sudah pernah dibuat semester lalu, pakai tombol **Salin TP** — tidak
   perlu mengetik ulang.*

### Tahap B — Kegiatan Harian (oleh Guru)
8. **Jurnal KBM** → catatan mengajar + absensi harian.
9. **Kelas Ajar → Input Nilai** → guru membuat "Rencana Nilai" (asesmen), lalu
   memasukkan nilai per siswa. **Nilai akhir dihitung otomatis dari TP** — guru
   tidak mengetik nilai akhir langsung.
10. **Remedial** → untuk siswa di bawah KKTP, guru memasukkan nilai remedial
    (nilai asli tetap tersimpan untuk audit).

### Tahap C — Akhir Semester (Guru sbg Wali Kelas)
11. **Nilai P5** & **Nilai Ekstrakurikuler** → predikat + deskripsi.
12. **Rekap Rapor → Generate Narasi** → sistem menyusun kalimat deskripsi
    otomatis dari capaian TP tiap siswa.
13. **Rekap Rapor → Kunci Semua Nilai** → **membekukan** nilai (jadi arsip).
    ⚠️ **Urutan wajib: Generate Narasi DULU, baru Kunci.** Setelah dikunci,
    narasi tidak bisa ditulis lagi.
14. **Cetak Rapor** → unduh PDF atau Word.

### Tahap D — Pergantian Periode (oleh Admin)
- **Lanjut Semester** (Ganjil → Genap tahun yang sama): siswa **tetap di
  kelasnya**, hanya pindah ke semester berikutnya.
- **Proses Kenaikan Kelas** (Genap → Ganjil tahun berikutnya): naik kelas,
  tinggal kelas, atau **lulus** (khusus kelas 9).
  ⚠️ **Rapor harus dikunci dulu** sebelum bisa naik kelas / lulus.

---

## 5. Fungsi Fitur (Per Modul)

### 5.1 Mesin Nilai (inti)
- Nilai akhir = **nilai sumatif + booster formatif**, dibatasi maksimal 100.
- **Booster** (opsional per SK Mengajar): nilai formatif (PR/keaktifan) bisa
  menambah nilai — mode `weight` (persen) atau `point` (poin tetap).
- **KKTP** (batas tuntas, default 75) menentukan predikat A–E.
- **[Kunci]** Nilai akhir **selalu** dihitung dari nilai per-TP. Tidak ada jalan
  memasukkan nilai akhir secara langsung — ini jaminan keabsahan data.

### 5.2 Rapor
- **Generate Narasi otomatis** dari template + capaian TP.
- **Kunci/Buka Kunci** — nilai terkunci tidak bisa ditimpa (arsip).
- **Cetak PDF & Word.**
- **Rapor lama tetap bisa dicetak ulang** meski tahun ajaran sudah berganti.

### 5.3 Kenaikan Kelas & Lanjut Semester
- Dua alur terpisah (lihat Tahap D). Keduanya menjaga **rantai riwayat**
  (setiap enrollment baru menautkan ke enrollment sebelumnya).
- **Gerbang keamanan:** rapor wajib terkunci; naik kelas hanya dari semester
  Genap ke Ganjil tahun berikutnya; lulus hanya kelas 9.

### 5.4 Salin TP Antar-Periode
- Menyalin Tujuan Pembelajaran dari periode lama ke periode baru sekali klik.
- **Anti-duplikat:** TP yang sudah ada tidak digandakan.
- Guru hanya bisa menyalin TP mapel yang ia ampu; admin bisa semua.

### 5.5 Ekstrakurikuler & P5
- Penilaian gaya P5: **hanya predikat + deskripsi** (tanpa angka).
- **Pembina ekskul dari luar sekolah** didukung — tanpa akun & tanpa NIP
  (cukup diisi namanya di SK Mengajar).

### 5.6 Grafik Nilai Siswa (Longitudinal)
- Menampilkan perkembangan nilai siswa **lintas semester & tahun**.
- Pemilih siswa bertingkat: **Status (Aktif/Lulus) → Kelas → Siswa**.
- **Alumni** dikelompokkan per **tahun kelulusan** (nama kelas dipakai ulang
  tiap angkatan, jadi tahun lulus wajib ditampilkan).

### 5.7 Impor Data (CSV)
Tersedia impor untuk: **Siswa, Guru, SK Mengajar, Jadwal, TP**.
- Format **CSV** (pemisah `;`). File `.xlsx` harus di-*Save As* ke CSV dulu.
- Baris kosong (ghost row Excel) otomatis dilewati.
- **Impor = Manual:** membuat siswa/guru manual menghasilkan akun yang sama
  persis dengan hasil impor.

### 5.8 Bimbingan Konseling (guru_bk)
- **Rekam Bimbingan** — catatan konseling dengan pengaturan visibilitas
  (siswa/wali/wali kelas/kepsek boleh lihat atau tidak).
- **Kuesioner VAK** — tes gaya belajar (Visual/Auditori/Kinestetik), berbasis
  aturan (bukan AI). Hasil disembunyikan dari siswa sampai dievaluasi guru BK.

### 5.9 Dashboard & Widget
- **Kepala Sekolah/Admin:** ringkasan statistik, kinerja input nilai guru,
  matriks absensi bulanan, tren akademik.
- **Guru:** jadwal mengajar, ringkasan nilai kelas.
- **Siswa/Wali:** jadwal pelajaran, grafik nilai, ekskul.

### 5.10 Website Publik (CMS)
- **Profil Sekolah** (nama, alamat, logo, banner, visi-misi, sambutan Kepsek).
- **Berita/Postingan, Kegiatan/Galeri, Struktur Organisasi.**
- Data profil ini **juga dipakai untuk SEO** (agar muncul di Google).

---

## 6. Catatan Operasional untuk IT

### 6.1 Manajemen Akun
- Buat/atur peran di **Manajemen Akun** (khusus super_admin/admin).
- **Reset password:** admin bisa mengatur ulang dari menu pengguna. Fitur
  "lupa password" lewat email memerlukan setelan SMTP yang benar (lihat 6.4).
- Saat siswa **lulus**, akun siswa & wali otomatis dinonaktifkan (kecuali wali
  masih punya anak lain yang aktif).

### 6.2 Backup (WAJIB rutin)
- **Database** adalah aset paling berharga (semua nilai & riwayat).
- Backup berkala lewat **hPanel → Databases → phpMyAdmin → Export**, atau fitur
  backup otomatis hosting. Simpan salinan di luar server.
- Backup juga folder **`storage/app`** (lampiran BK, gambar postingan).

### 6.3 Setelah Update Kode / Pindah Device
Jika tampilan tidak berubah padahal file sudah diganti, bersihkan cache:
```
php artisan optimize:clear
```
Lalu restart web server (Apache). Untuk produksi, setelah stabil jalankan lagi:
```
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
⚠️ Setiap kali `.env` diubah, **wajib** `php artisan config:cache` ulang.

### 6.4 Berkas Konfigurasi `.env`
- Berisi **password database & rahasia aplikasi** — jangan pernah dibagikan
  atau di-commit ke GitHub.
- Yang wajib benar di produksi: `APP_ENV=production`, `APP_DEBUG=false`,
  `APP_URL=https://...`, kredensial `DB_*`, dan `QUEUE_CONNECTION=sync`.
- ⚠️ `APP_DEBUG=false` mutlak — jika `true`, halaman error membocorkan isi
  `.env` (termasuk password) ke pengunjung.

### 6.5 Kinerja
- Aplikasi didesain untuk **shared hosting** (tanpa server antrian/Redis).
  Proses berat (impor, generate narasi, naik kelas) dibuat bertahap agar tidak
  timeout.
- Sudah ada indeks database untuk mempercepat query rekap.

### 6.6 Keamanan
- ID pada URL disamarkan (obfuscated) — pengunjung tidak bisa menebak data
  orang lain dengan mengganti angka di alamat.
- Akses tiap menu dibatasi kebijakan peran. Cetak rapor hanya oleh yang berhak
  (admin, kepsek, wali kelas terkait, siswa ybs, atau walinya).

---

## 7. Aturan "Jangan Diubah" (Proteksi Longitudinal)

Hal-hal berikut **sengaja dibuat** demi keabsahan data. Jangan "diperbaiki"
tanpa memahami dampaknya:

1. **Nilai akhir tidak boleh diisi langsung** — selalu dihitung dari nilai TP.
2. **Nilai terkunci tidak bisa ditimpa** — ini yang membuat rapor jadi arsip sah.
3. **Data siswa yang punya riwayat tidak bisa dihapus** dari menu — mencegah
   kehilangan data penelitian/arsip.
4. **`is_current` wali kelas bersifat per-periode** — satu kelas bisa punya wali
   berbeda tiap tahun. Ini benar, bukan bug.
5. **Hanya satu tahun ajaran yang boleh aktif** dalam satu waktu.

---

## 8. Kontak & Dokumen Terkait
- **Panduan deploy hosting:** `docs/panduan-deploy-hostinger.md`
- **Spesifikasi teknis lengkap:** `srs-v3.md`
- Untuk perubahan besar (skema database, alur inti, hak akses), selalu perbarui
  `srs-v3.md` di commit yang sama.
