# Sistem Penilaian Sumatif–Formatif dengan Booster (versi siap tempel)

> Teks di bawah ini ditulis sebagai prosa pembahasan skripsi (tanpa penanda status) agar mudah disalin. Versi ber-catatan *as-is* tetap tersedia di `pembahasan-pengkodean.md`, dan spesifikasi teknisnya di `rancangan-booster-formatif.md`.

---

## Sistem Penilaian Sumatif dan Formatif

Penilaian akhir setiap mata pelajaran pada SIPDL dihitung melalui logika inti pada `TeachingAssignment::calculateFinalGrade()`. Nilai dasar diperoleh dari **asesmen sumatif** menggunakan salah satu dari tiga formula yang dapat dipilih guru pada konfigurasi SK Mengajar, yaitu rata-rata murni (`average`), pembobotan persentase (`weighting`), atau persentase ketuntasan terhadap KKTP (`percentage`). Asesmen sumatif inilah yang menjadi fondasi nilai rapor.

Di atas nilai sumatif tersebut, sistem menyediakan mekanisme **booster nilai formatif** yang memungkinkan guru memberikan nilai tambahan dari hasil asesmen formatif (penilaian proses belajar) ke skor sumatif. Mekanisme ini menjawab kebutuhan bahwa nilai formatif sebaiknya berperan sebagai apresiasi proses, bukan sekadar dirata-ratakan langsung dengan nilai sumatif. Booster diatur **per SK Mengajar** dan memiliki tiga mode:

1. **Nonaktif.** Nilai formatif tidak menambah nilai akhir; nilai rapor murni berasal dari asesmen sumatif.
2. **Bobot Persen.** Setiap nilai formatif memberi tambahan sebesar `nilai_formatif × persentase bobot`. Sebagai contoh, nilai formatif 100 dengan bobot 20% menambahkan 20 poin ke skor sumatif. Apabila terdapat beberapa nilai formatif, kontribusinya dijumlahkan secara akumulatif dan dibatasi maksimum 100.
3. **Poin Tetap.** Setiap nilai formatif yang terisi memberikan sejumlah poin tetap tanpa memandang besar nilainya. Sebagai contoh, bila poin per formatif ditetapkan 2, maka tiap formatif yang terisi menambah 2 poin — sehingga nilai formatif 100 pun hanya menyumbang 2 poin.

Guru dapat memilih untuk mengaktifkan salah satu mode atau menonaktifkannya sama sekali, sehingga sistem penilaian tetap fleksibel mengikuti kebijakan masing-masing guru mata pelajaran. Secara ringkas, nilai akhir dirumuskan sebagai:

```
nilai_akhir = pembulatan( min(100, skor_sumatif + total_booster) )
```

dengan `total_booster` bernilai 0 pada mode Nonaktif, akumulasi `nilai_formatif × bobot%` pada mode Bobot Persen, atau `jumlah formatif terisi × poin tetap` pada mode Poin Tetap.

## Konsistensi dengan Deskripsi Rapor

Mekanisme booster yang sama diterapkan pada perhitungan skor per **Tujuan Pembelajaran (TP)** yang menjadi dasar pembuatan deskripsi naratif rapor oleh `DescriptionGeneratorService`. Skor setiap TP dihitung dari rata-rata nilai **sumatif** yang tertaut pada TP tersebut, kemudian ditambah kontribusi booster dari nilai formatif yang tertaut pada TP yang sama, dengan batas maksimum 100. Skor TP inilah yang dikonversi menjadi predikat (A–E) melalui `GradeRangeResolver` dan dirangkai menjadi kalimat deskripsi.

Dengan pendekatan ini, perhitungan **nilai akhir** dan **deskripsi rapor** menggunakan aturan penilaian yang konsisten: nilai sumatif sebagai dasar, dan nilai formatif sebagai penambah yang terukur sesuai mode booster yang dipilih guru.

## Ilustrasi Perhitungan

Misalkan sebuah TP memiliki nilai sumatif dengan rata-rata 70, serta dua nilai formatif (100 dan 80) yang keduanya terisi:

| Mode booster | Perhitungan tambahan | Skor akhir |
|--------------|----------------------|-----------|
| Nonaktif | 0 | 70 |
| Bobot Persen (10%) | (100×10%) + (80×10%) = 18 | 88 |
| Bobot Persen (20%) | (100×20%) + (80×20%) = 36 | 100 (dibatasi) |
| Poin Tetap (2) | 2 formatif × 2 = 4 | 74 |

Tabel di atas memperlihatkan bahwa mode Bobot Persen memberi dampak proporsional terhadap besar nilai formatif, sedangkan mode Poin Tetap memberi penghargaan yang seragam atas keterisian/keaktifan tanpa membuat nilai formatif mendominasi nilai akhir.
