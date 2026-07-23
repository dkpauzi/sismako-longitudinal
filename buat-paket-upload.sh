#!/usr/bin/env bash
#
# =============================================================================
#  PEMBUAT PAKET UNGGAH — SISMAKO (SMP Negeri 45 Sijunjung)
# =============================================================================
#  Membuat satu berkas ZIP siap unggah ke Hostinger, dengan berkas sensitif
#  DIKECUALIKAN otomatis (jangan memilih folder manual — itu sumber kesalahan
#  tersering).
#
#  CARA MENJALANKAN (WAJIB lewat Git Bash):
#    Klik kanan di dalam folder proyek → "Open Git Bash here"
#    (Windows 11: klik "Show more options" dulu), lalu:
#
#      bash buat-paket-upload.sh
#
#  JANGAN mengetik `bash` dari PowerShell/CMD — Windows akan memanggil WSL,
#  bukan Git Bash, dan gagal dengan error "WSL tidak terpasang".
#  Pastikan `pwd` menampilkan /c/xampp/htdocs/... (bukan /mnt/c/...).
# =============================================================================

set -euo pipefail

NAMA_APP="sismako"
STAMP="$(date +%Y%m%d-%H%M)"
TMP=".paket-upload-tmp"
ZIP="${NAMA_APP}-upload-${STAMP}.zip"

cd "$(dirname "$0")"

echo "==> 1/5 Memeriksa prasyarat"

# bsdtar bawaan Windows. `zip` TIDAK tersedia di Git Bash, dan bsdtar lebih
# tahan terhadap path panjang — penting karena struktur vendor/ sangat dalam.
TAR="/c/Windows/System32/tar.exe"
if [ ! -x "$TAR" ]; then
    echo "GAGAL: $TAR tidak ditemukan. Butuh Windows 10/11 (punya bsdtar bawaan)."
    exit 1
fi

for wajib in artisan vendor/autoload.php public/index.php bootstrap/app.php public/build/manifest.json; do
    if [ ! -e "$wajib" ]; then
        echo "GAGAL: '$wajib' tidak ada."
        [ "$wajib" = "public/build/manifest.json" ] && echo "       Jalankan dulu: npm run build"
        [ "$wajib" = "vendor/autoload.php" ] && echo "       Jalankan dulu: composer install"
        exit 1
    fi
done
echo "    OK — semua berkas inti tersedia."

echo "==> 2/5 Menyiapkan folder sementara"
rm -rf "$TMP"
mkdir -p "$TMP/$NAMA_APP"

echo "==> 3/5 Menyalin berkas aplikasi"

# Hanya folder & berkas yang BENAR-BENAR dibutuhkan di server.
SALIN=(
    app bootstrap config database lang public resources routes storage vendor
    artisan composer.json composer.lock
)
for item in "${SALIN[@]}"; do
    [ -e "$item" ] && cp -R "$item" "$TMP/$NAMA_APP/" || true
done

# .env.production.example dikirim sebagai .env.example agar mudah disalin
# menjadi .env di server (isinya sudah disetel untuk production).
cp .env.production.example "$TMP/$NAMA_APP/.env.example"
[ -f public/.htaccess ] && cp public/.htaccess "$TMP/$NAMA_APP/public/.htaccess"

echo "==> 4/5 Membersihkan berkas sensitif & sampah"

cd "$TMP/$NAMA_APP"

# --- WAJIB: data pribadi asli (NISN, NIK, NIP, alamat, nama orang tua) -------
rm -rf "database/seeders/Dummy"        # seeder sidang & data asli sekolah
rm -rf "docs"                          # jaga-jaga bila tersalin
find . -maxdepth 2 -name "data example" -type d -exec rm -rf {} + 2>/dev/null || true

# --- WAJIB: rahasia & lingkungan lokal --------------------------------------
rm -f .env .env.backup .env.production

# --- Symlink public/storage -------------------------------------------------
# Git Bash menyalin symlink menjadi FOLDER SUNGGUHAN di Windows, sehingga
# `rm -f` gagal dengan "Is a directory". Harus -rf. Di server nanti dibuat
# ulang lewat `php artisan storage:link`.
rm -rf public/storage

# --- Cache lokal (bikin error kalau terbawa ke server) ----------------------
rm -f bootstrap/cache/*.php
rm -rf storage/framework/cache/data/* storage/framework/sessions/* \
       storage/framework/views/* storage/logs/* 2>/dev/null || true

# Pertahankan struktur folder storage (Laravel butuh foldernya tetap ada).
for d in framework/cache/data framework/sessions framework/views framework/testing logs app/public; do
    mkdir -p "storage/$d"
    touch "storage/$d/.gitignore" 2>/dev/null || true
done

# --- Berkas uji coba & pengembangan ----------------------------------------
rm -f test.php test_zilian.php
rm -rf tests node_modules .git .github .claude

cd ../..

echo "==> 5/5 Mengemas menjadi ZIP"
rm -f "$ZIP"
cd "$TMP/$NAMA_APP"

# PENTING: berikan daftar nama secara EKSPLISIT, JANGAN pakai titik (".").
# Dengan ".", bsdtar menulis entri sebagai "./artisan", "./app/" — dan Windows
# Explorer TIDAK mengenali format itu sehingga ZIP terlihat KOSONG (0 item),
# padahal isinya ada. Akibatnya Anda tak bisa memverifikasi isi sebelum unggah.
"$TAR" -a -c -f "../../$ZIP" $(ls -A)

cd ../..
rm -rf "$TMP"

UKURAN="$(du -h "$ZIP" 2>/dev/null | cut -f1 || echo '?')"

echo
echo "============================================================"
echo " SELESAI"
echo "============================================================"
echo " Berkas : $ZIP"
echo " Ukuran : $UKURAN"
echo
echo " Setelah diekstrak di server akan menjadi folder: $NAMA_APP/"
echo " Lanjutkan ke docs/panduan-deploy-hostinger.md (Langkah 3)."
echo "============================================================"
