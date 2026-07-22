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

### 1.2 Jangan upload folder ini
- `node_modules/` (besar, tidak dipakai di server)
- `.git/`
- `.env` lokal Anda (akan dibuat ulang di server)
- `database/seeders/Dummy/` dan `docs/data example/` (data asli siswa — privasi)

### 1.3 Wajib upload
- `vendor/` ✅ — shared hosting sering tidak mengizinkan `composer install`.
  Kalau punya akses SSH, boleh saja `composer install --no-dev` di server.

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
Kalau seluruh proyek ditaruh di `public_html`, maka `.env` Anda bisa diakses
orang lain. Pilih salah satu:

### Opsi A — Ubah Document Root (paling rapi, kalau tersedia)
1. Upload seluruh proyek ke `/home/uXXXXXX/sismako/`.
2. hPanel → **Websites → Manage → Advanced → Change Document Root**.
3. Arahkan ke `/home/uXXXXXX/sismako/public`.
4. Selesai — tidak perlu mengubah file apa pun.

### Opsi B — Cara klasik (selalu bisa)
1. Upload seluruh proyek **kecuali isi `public/`** ke `/home/uXXXXXX/sismako/`.
2. Upload **isi** folder `public/` (termasuk `build/`, `index.php`, `.htaccess`,
   `robots.txt`, `favicon.ico`) ke `public_html/`.
3. Edit `public_html/index.php`, ubah dua baris path-nya:

```php
require __DIR__.'/../sismako/vendor/autoload.php';
$app = require_once __DIR__.'/../sismako/bootstrap/app.php';
```

⚠️ Pastikan `.env`, `storage/`, dan `vendor/` **berada di luar** `public_html`.

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
