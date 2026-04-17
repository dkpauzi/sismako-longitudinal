# 🎓 Sismako-Longitudinal

**Sistem Informasi Akademik Terintegrasi - SMPN 45 Sijunjung**

Sismako-Longitudinal adalah sistem informasi akademik modern berbasis web yang dirancang khusus untuk memfasilitasi manajemen data sekolah, penilaian guru, hingga pemantauan perkembangan siswa secara longitudinal. Sistem ini dibangun dengan fokus pada kecepatan, keamanan, dan kemudahan penggunaan (UX) bagi administrator sekolah di Indonesia.

---

## 🛠️ Tech Stack

Sistem ini dibangun menggunakan arsitektur modern (TALL Stack):

- **Framework:** Laravel 11 (PHP)
- **Admin Panel:** Filament v3
- **Frontend:** Blade Templating + Tailwind CSS / Bootstrap
- **Database:** MySQL
- **Manajemen Hak Akses:** Spatie Permission

---

## 🚀 Fitur Utama (Versi 1.0)

- **Smart Login System:** Mendukung multi-format autentikasi (Login menggunakan NIP, NISN, atau Email).
- **Smart Data Importer:** Algoritma import Excel/CSV cerdas dengan fitur _auto-generate password_ dan penerjemah format tanggal otomatis (DD-MM-YYYY ke YYYY-MM-DD).
- **Dynamic Landing Page (CMS):** Pengaturan konten profil sekolah, visi misi, dan fasilitas yang terintegrasi langsung dari _database_.
- **Master Data Management:** Manajemen sistem berbasis Tahun Ajaran aktif dan pengelolaan kelas.
- **Role-Based Access Control:** Manajemen hak akses yang dinamis untuk Admin, Guru (Mapel/Wali Kelas), dan Siswa.

---

## 💻 Panduan Instalasi (Untuk Tim Developer)

Jika Anda baru pertama kali mengunduh (clone) project ini dari Github, ikuti 7 langkah wajib di bawah ini agar project dapat berjalan di komputer Anda.

### Persyaratan Sistem

- PHP >= 8.2
- Composer
- MySQL (XAMPP / Laragon)
- Git

### Langkah Instalasi

**1. Clone Repository**
Unduh cetak biru project dari Github.

```bash
git clone <isi-dengan-url-github-project-anda-disini>
cd sismako-longitudinal

**2. Install Dependencies (Perabotan)**
composer install

**3. Buat File Konfigurasi (.env)**
cp .env.example .env
# Pengguna Windows CMD: copy .env.example .env

**4. Konfigurasi Database**
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sismako_db
DB_USERNAME=root
DB_PASSWORD=

**5. Konfigurasi Database**
php artisan key:generate

**6. Konfigurasi Database**
php artisan migrate:fresh --seed
php artisan storage:link

**7. Jalankan Local Server**
php artisan serve
```
