# Panduan Deploy ke Hostinger (Business — Shared Hosting)

Panduan ini ditulis untuk pemula. Ikuti berurutan. Bagian bertanda ⚠️ adalah
hal yang paling sering bikin website error/blank.

**Ringkas:** Laravel 11 + Filament v3, PHP 8.2+, MySQL, `QUEUE_CONNECTION=sync`
(shared hosting tidak punya worker).

---

## 0. Kenapa database lokal Anda berbeda dengan hosting

Di XAMPP, MySQL memakai user `root` **tanpa password** — itu sebabnya `.env`
lokal Anda seperti ini:

```
DB_USERNAME=root
DB_PASSWORD=
```

Di Hostinger **tidak ada user `root`**. Anda harus **membuat sendiri** database
dan user-nya, dan user itu **wajib punya password**. Nama database & user juga
otomatis diberi prefix akun, contoh `u123456789_sismako`.

⚠️ **Jangan** memakai `root` / password kosong di server. Selain tidak akan
berhasil connect, itu lubang keamanan.

---

## 1. Persiapan di komputer lokal (sebelum upload)

### 1.1 Build aset frontend
Shared hosting **tidak punya Node/npm**, jadi aset harus di-*build* di lokal:

```bash
npm install
npm run build
```

Pastikan folder **`public/build/`** muncul (berisi `manifest.json` + `assets/`).

⚠️ Kalau folder ini tidak ikut ter-upload, website akan error
**“Vite manifest not found”**. Di proyek ini `public/build` sudah sengaja
**tidak** di-*gitignore* supaya ikut terkirim.

### 1.2 Buat paket unggah otomatis (DISARANKAN)

Jangan memilih folder satu per satu — itu sumber kesalahan tersering, dan
risikonya data asli siswa ikut terunggah. Pakai skrip yang sudah disediakan:

```bash
bash buat-paket-upload.sh
```

⚠️ **WAJIB dijalankan lewat Git Bash.** Klik kanan di dalam folder proyek →
**“Open Git Bash here”** (Windows 11: klik *Show more options* dulu).
Kalau Anda mengetik `bash` dari **PowerShell/CMD**, Windows akan memanggil
**WSL** — bukan Git Bash — dan gagal dengan pesan *“WSL tidak terpasang”*.
Anda **tidak perlu** memasang WSL.

Cara memastikan sudah benar: ketik `pwd`.
- `/c/xampp/htdocs/sismako-longitudinal` → ✅ Git Bash
- `/mnt/c/...` → ❌ WSL
- `C:\...` → ❌ PowerShell

Hasilnya satu berkas `sismako-upload-<tanggal>.zip` (± 45 MB) yang
**otomatis mengecualikan** berkas sensitif:

| Dikecualikan | Alasan |
|---|---|
| `.env` | berisi password database |
| `database/seeders/Dummy/` | data asli siswa (NISN, alamat, nama orang tua) |
| `docs/` & `docs/data example/` | NISN & NIP asli |
| `tests/`, `node_modules/`, `.git/` | tidak dipakai di server |
| `test.php`, `test_zilian.php` | berkas coba-coba |
| `public/storage` | symlink; dibuat ulang di server |
| cache lokal | menyebabkan error kalau terbawa |

Isi yang **ikut**: `vendor/` ✅ (shared hosting sering tidak mengizinkan
`composer install`), `public/build/` ✅, dan `.env.production.example` yang
dikirim sebagai `.env.example`.

> Seeder yang ikut hanya versi produksi (`DatabaseSeeder`, `SchoolSeeder`,
> `RolePermissionSeeder`). Seeder data sidang **tidak ikut**. Kalau nanti butuh
> untuk demo di server, unggah folder `Dummy/` terpisah lalu **hapus setelah
> selesai**.

### 1.3 Kalau ingin menyalin manual (tanpa skrip)
Wajib ikut: `vendor/`, `public/build/`.
Jangan ikut: `node_modules/`, `.git/`, `.env`, `database/seeders/Dummy/`,
`docs/data example/`.

---

## 2. Buat database di hPanel

1. Login hPanel → **Databases → MySQL Databases**.
2. **Create New Database**:
   - Database name: `sismako` → jadi `u123456789_sismako`
   - Username: `admin` → jadi `u123456789_admin`
   - Password: **buat password kuat**, lalu **CATAT** (tidak bisa dilihat lagi).
3. Simpan ketiganya. Nilai ini yang masuk ke `.env` nanti.

> Host database di Hostinger adalah **`localhost`**, bukan IP.

---

## 3. Upload file & mengatur “document root”

Laravel aman hanya bila yang bisa diakses publik **cuma folder `public/`**.
Kalau seluruh proyek ditaruh di `public_html`, `.env` Anda (berisi password
database) bisa dibuka siapa saja lewat browser.

### Struktur target

```
/home/uXXXXXXXX/
├── public_html/          ← isi folder public/ (boleh diakses browser)
├── sismako/              ← seluruh aplikasi (TIDAK bisa diakses browser)
└── DO_NOT_UPLOAD_HERE    ← penanda bawaan Hostinger
```

Folder `sismako/` diletakkan **sejajar** dengan `public_html/` — persis di
tempat berkas `DO_NOT_UPLOAD_HERE` berada. Penanda itu justru konfirmasi bahwa
lokasi tersebut **aman dari akses publik**.

### Langkah

1. hPanel → **Files → File Manager**.
2. Klik ikon **Upload** → pilih **“Files”**, **bukan “Folder”**.

   ⚠️ Kalau judul jendelanya *“Select Folder to Upload”* dan `.zip` tidak
   terlihat, itu pemilih **folder**. Tekan **Cancel**, klik Upload lagi, lalu
   pilih **Files**. Judul yang benar: *“Open”* / *“Select File to Upload”*.

3. Unggah `sismako-upload-*.zip` ke direktori **home** (sejajar `public_html`),
   lalu klik kanan → **Extract**. Akan terbentuk folder `sismako/`.

   > Kalau unggahan 45 MB lewat browser sering putus, pakai **SFTP/FileZilla**.
   > Kredensial ada di hPanel → **Files → FTP Accounts**.

4. Masuk ke `sismako/public/`, **pilih semua isinya**, lalu **Move** ke
   `public_html/` (termasuk `build/`, `index.php`, `.htaccess`, `robots.txt`,
   `favicon.ico`). Setelah kosong, folder `sismako/public/` boleh dibiarkan.

5. Edit `public_html/index.php` — ubah **3 baris** yang menunjuk `__DIR__.'/../'`
   menjadi `__DIR__.'/../sismako/'`. Hasil akhirnya utuh seperti ini:

```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../sismako/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../sismako/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../sismako/bootstrap/app.php')
    ->handleRequest(Request::capture());
```

⚠️ Pastikan `.env`, `storage/`, dan `vendor/` **tetap berada di dalam
`sismako/`**, bukan di `public_html/`.

---

## 4. Buat file `.env` di server

Salin `.env.production.example` (ada di repo ini) menjadi `.env` di folder
aplikasi, lalu isi:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com
APP_TIMEZONE=Asia/Jakarta

DB_HOST=localhost
DB_DATABASE=u123456789_sismako
DB_USERNAME=u123456789_admin
DB_PASSWORD=password-yang-tadi-dicatat

QUEUE_CONNECTION=sync
SESSION_DRIVER=database
CACHE_STORE=database
```

⚠️ `APP_DEBUG=false` itu **wajib**. Kalau `true`, setiap error akan menampilkan
isi `.env` (termasuk password database) ke pengunjung.

---

## 5. Jalankan perintah setup (via SSH)

hPanel → **Advanced → SSH Access** (aktifkan), lalu:

```bash
cd ~/sismako

php artisan key:generate      # WAJIB, mengisi APP_KEY
php artisan migrate --force   # buat semua tabel
php artisan db:seed --force   # role, permission, profil sekolah
php artisan storage:link      # agar gambar di /storage bisa tampil
```

> `--force` diperlukan karena `APP_ENV=production`.

**Kalau tidak punya akses SSH:** jalankan migrasi lewat import SQL manual
(export database lokal via phpMyAdmin, lalu import di phpMyAdmin Hostinger),
dan ganti `storage:link` dengan membuat folder `public_html/storage` yang
menunjuk ke `storage/app/public` (bisa lewat File Manager → symlink, atau
salin manual isinya).

---

## 6. Optimasi (bikin website jauh lebih cepat)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

⚠️ **Setiap kali mengubah `.env`, ulangi `php artisan config:cache`**, kalau
tidak perubahan tidak akan terbaca.

Kalau nanti ada error aneh setelah update, bersihkan dulu:
```bash
php artisan optimize:clear
```

---

## 7. Hak akses folder

```bash
chmod -R 775 storage bootstrap/cache
```
Kalau masih error “failed to open stream / permission denied”, coba `755`.

---

## 8. SSL & paksa HTTPS

1. hPanel → **Security → SSL** → aktifkan (gratis, Let's Encrypt).
2. Setelah SSL aktif, set `APP_URL=https://...` dan
   `SESSION_SECURE_COOKIE=true`, lalu `php artisan config:cache`.

---

## 9. Cek PHP version

hPanel → **Advanced → PHP Configuration** → pilih **PHP 8.2 atau 8.3**.
Aktifkan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
`ctype`, `json`, `bcmath`, `fileinfo`, `gd` (untuk gambar/PDF).

Naikkan juga bila tersedia: `max_execution_time = 120`, `memory_limit = 256M`
(membantu impor CSV & cetak PDF).

---

## 10. Checklist setelah deploy

### 🔴 Uji keamanan WAJIB (lakukan paling pertama)

Buka di browser: **`https://domain-anda.com/.env`**

- **404 / Not Found** → ✅ benar, struktur folder aman.
- **Isi file muncul** (terlihat `DB_PASSWORD=...`) → 🚨 **HENTIKAN**. Berarti
  aplikasi tertaruh di dalam `public_html` dan password database Anda terekspos
  ke publik. Ulangi Bagian 3, lalu **ganti password database** di hPanel.

Uji juga `https://domain-anda.com/storage/logs/laravel.log` — harus 404.

### Checklist fungsional

- [ ] Beranda terbuka tanpa error
- [ ] Login admin berhasil
- [ ] Gambar (logo/banner/postingan) tampil → tanda `storage:link` sukses
- [ ] Cetak rapor PDF & Word berhasil diunduh
- [ ] Impor CSV siswa berjalan (tidak timeout)
- [ ] Buka `https://domain-anda.com/sitemap.xml` → muncul XML
- [ ] Buka `https://domain-anda.com/robots.txt` → muncul, dan baris `Sitemap:`
      sudah memakai domain asli

---

## 11. Troubleshooting cepat

| Gejala | Penyebab paling sering |
|---|---|
| Halaman putih / 500 | `APP_KEY` kosong, atau `storage/` tidak writable |
| “Vite manifest not found” | Folder `public/build` belum ter-upload |
| “SQLSTATE… Access denied” | User/password/nama DB salah, atau masih pakai `root` |
| “SQLSTATE… no such table” | Belum `php artisan migrate --force` |
| Gambar tidak muncul | Belum `php artisan storage:link` |
| Perubahan `.env` tidak berefek | Belum `php artisan config:cache` |
| Impor CSV timeout | Naikkan `max_execution_time`; jangan ubah `QUEUE_CONNECTION` |
| CSS berantakan | Aset lama ter-cache → `php artisan optimize:clear` + hard refresh |

---

## 12. Langkah SEO setelah website online

Kode SEO-nya sudah disiapkan (meta description, Open Graph, structured data
`School`, `robots.txt`, `sitemap.xml`). Sisanya **harus Anda lakukan manual** —
tanpa langkah ini Google tidak akan tahu website Anda ada.

### 12.1 Ganti domain di robots.txt
Edit `public/robots.txt`, ganti baris `Sitemap:` dengan domain asli Anda.

### 12.2 Daftarkan ke Google Search Console (paling penting)
1. Buka <https://search.google.com/search-console>.
2. **Add Property → URL prefix** → masukkan `https://domain-anda.com`.
3. Verifikasi (paling mudah: **HTML tag** → salin meta tag → tempel di
   `resources/views/layouts/app.blade.php` di dalam `<head>`).
4. Menu **Sitemaps** → masukkan `sitemap.xml` → **Submit**.
5. Menu **URL Inspection** → masukkan URL beranda → **Request Indexing**.

### 12.3 Buat Google Business Profile
Untuk pencarian nama sekolah, ini **sering lebih berpengaruh** daripada SEO
website. Daftar di <https://business.google.com> sebagai **Sekolah**, isi nama
resmi *SMP Negeri 45 Sijunjung*, alamat, titik peta, telepon, jam, dan foto.

### 12.4 Isi profil sekolah di aplikasi
Login admin → **Pengaturan Sekolah**, lengkapi: nama resmi, alamat, kode pos,
telepon, email, logo, banner, dan link media sosial.
Data ini otomatis dipakai untuk meta description + structured data.

### 12.5 Rutin isi berita
Google menyukai website yang aktif. Menambah 1–2 pengumuman/berita per bulan
sangat membantu peringkat.

### ⏱️ Ekspektasi realistis
Website baru **tidak langsung** muncul di Google. Umumnya:
- 1–3 hari: terindeks setelah *Request Indexing*
- 2–8 minggu: muncul stabil untuk pencarian nama sekolah

Yang paling menentukan untuk kata kunci *“SMP 45 Sijunjung”*, *“SMPN 45
Sijunjung”*, *“SMP Negeri 45 Sijunjung”* adalah: domain yang relevan (idealnya
`.sch.id`), Google Business Profile aktif, dan nama sekolah muncul konsisten di
`<title>`, `<h1>`, dan structured data — ketiganya sudah dipasang di kode.
