# Pembahasan: Pengkodean (Coding) Berbasis MVC — SIPDL

Bab ini membahas implementasi (pengkodean) Sistem Informasi Peserta Didik Longitudinal (SIPDL) untuk setiap *use case* yang telah dirancang. Karena *Sequence Diagram* dirancang dengan pendekatan **MVC (Model–View–Controller)**, pembahasan ditulis sebagai **narasi alur kerja kode menurut lapisan MVC**: bagaimana aksi pengguna pada **View** diteruskan ke **Controller**, lalu memanipulasi **Model**, hingga tersimpan/terbaca dari **Database**.

> **Catatan kejujuran arsitektur.** SIPDL dibangun dengan **TALL Stack + Filament v3** yang berbasis **Livewire**, sehingga tidak menggunakan pola MVC klasik (request → Controller → View) secara murni. Oleh karena itu, istilah MVC pada bab ini dipakai sebagai **pemetaan fungsional** terhadap struktur kode nyata (dijelaskan pada sub-bab A), agar konsisten dengan Sequence Diagram. Seluruh narasi mengacu pada **kondisi kode saat ini (as-is)**; perbedaan dengan rencana perbaikan ditandai secara eksplisit.

---

## A. Pemetaan Arsitektur MVC pada SIPDL

| Lapisan MVC | Peran konseptual | Implementasi nyata di SIPDL |
|-------------|------------------|------------------------------|
| **Model** | Entitas data, relasi, dan sebagian aturan domain | Eloquent Model di `app/Models/` (mis. `User`, `Student`, `TeachingAssignment`, `Grade`, `FinalGrade`). Termasuk logika domain pada model: `TeachingAssignment::calculateFinalGrade()`, event `booted()` (mis. `AcademicPeriod` menjaga satu periode aktif; `TeachingAssignment` men-*seed* `grade_ranges`), dan `FinalGrade::generateNarrative()`. |
| **View** | Antarmuka pengguna | Konfigurasi deklaratif **Filament** (Form, Table, Action, Widget) yang dirender oleh Livewire, serta **Blade** murni untuk keluaran cetak/portal: `resources/views/rapor/print.blade.php`, `resources/views/exports/rapor-print.blade.php`, dan halaman siswa (`filament/pages/student/*`). |
| **Controller** (lapisan kendali) | Orkestrasi aksi & logika aplikasi | Diperankan oleh empat jenis komponen: **(a)** *HTTP Controller* klasik — `RaporPrintController` (satu-satunya pada alur inti); **(b)** **Filament Resource / Page / RelationManager** (komponen Livewire) sebagai penghubung View↔Model dan pelaksana *action*; **(c)** **Service** — `GradeRangeResolver`, `RaporExportService`, `DescriptionGeneratorService`, `NilaiVisualisasiService`, `SchoolIdentityService` (logika bisnis/domain); **(d)** **Observer** — `GradeObserver`, `AttendanceObserver` (kendali berbasis *event* model). |
| *Persistence* | Penyimpanan | **MySQL** diakses melalui **Eloquent ORM** / Query Builder. |

Karena Livewire bersifat *stateful* (interaksi dikirim sebagai pembaruan komponen, bukan siklus request–response halaman penuh), pada narasi berikut **"Controller"** merujuk pada komponen Filament/Service/Observer yang menjalankan logika tersebut, sesuai pemetaan di atas.

---

## B. Pembahasan Pengkodean per Use Case

### UC1 — Mengelola Data Master (Admin)

Pengelolaan data master (Siswa, Guru, Kelas, Mata Pelajaran, Tahun Ajaran) diimplementasikan dengan **Filament Resource** pada `app/Filament/Resources/` (`StudentResource`, `TeacherResource`, `ClassroomResource`, `SubjectResource`, `AcademicPeriodResource`). Pada lapisan **View**, setiap Resource mendefinisikan `form()` (skema input) dan `table()` (daftar data) secara deklaratif; Admin berinteraksi dengan tabel dan tombol *Create/Edit/Delete*.

Ketika Admin menekan "New" lalu menyimpan, **Controller** (komponen Livewire `CreateRecord`/`EditRecord` milik Resource) menjalankan validasi sesuai aturan field (mis. `required`, `unique` untuk `students.nisn` dan `subjects.code`), kemudian memanggil **Model** Eloquent (`Model::create()` / `$record->update()`). **Model** menulis ke **Database** melalui Eloquent (`INSERT`/`UPDATE`). Sebagian model memiliki logika domain yang berjalan otomatis pada tahap ini, misalnya `AcademicPeriod::booted()` yang — ketika sebuah periode di-set `is_active = true` — menonaktifkan periode lain dalam satu transaksi, sehingga konsistensi "satu periode aktif" terjaga di lapisan Model, bukan di UI.

### UC2 — Mengelola Penugasan Pembelajaran (SK Mengajar)

Penugasan ditangani `TeachingAssignmentResource`. Pada **View**, Admin memilih Guru, Mata Pelajaran, Kelas, Periode Akademik, formula penilaian (`grading_formula`), dan KKTP. Saat disimpan, **Controller** Resource memvalidasi lalu memanggil **Model** `TeachingAssignment` untuk `create()`.

Keistimewaan UC ini berada di lapisan **Model**: pada `TeachingAssignment::booted()` terdapat *hook* `creating` yang mengisi KKTP default dari `SchoolSetting` bila kosong (accessor `kktp_or_default`), serta *hook* `created`/`updated` yang memanggil **Service** `GradeRangeResolver::seedDefaults()`. Service ini menghitung lima rentang nilai (A–E) berbasis KKTP dan menuliskannya ke tabel `grade_ranges` melalui `GradeRange::updateOrCreate()`. Dengan demikian, satu aksi simpan dari **View** memicu rangkaian Model→Service→Database yang menyiapkan parameter penilaian secara otomatis, tanpa input manual tambahan dari Admin.

### UC3 — Kelola Tujuan Pembelajaran (TP)

TP dikelola `LearningObjectiveResource`. Pada **View**, Guru mengisi Mata Pelajaran, Periode, Fase, Tingkat Kelas, **Kode TP**, deskripsi, dan ringkasan (`attribute`). **Controller** memfilter data lewat `getEloquentQuery()` agar Guru hanya melihat TP miliknya, lalu menyimpan ke **Model** `LearningObjective` → **Database**.

Mengenai *standardisasi penamaan TP*: pada kode saat ini **tidak ada logika validasi/standardisasi format otomatis**. Field `code` berupa `TextInput` biasa dengan batas `maxLength(20)` dan sebuah `helperText` yang **memandu** konvensi penamaan (`[KODE_MAPEL]-[KELAS]-[SEMESTER]-[NOMOR]`, contoh `IPA-8-2-TP3`). Artinya standardisasi bersifat **konvensi yang dipandu antarmuka (View)**, bukan ditegakkan oleh Controller/Model. TP yang tersimpan kemudian dirujuk oleh asesmen melalui tabel pivot `assessment_learning_objective` dan menjadi dasar narasi rapor pada UC6.

### UC4 — Mengelola Jurnal KBM dan Absensi

Jurnal harian dikelola `LessonJournalResource`. Pada **View**, pilihan SK Mengajar difilter ke periode aktif (`whereHas('academicPeriod', is_active = true)`), dan nomor pertemuan terisi otomatis melalui `afterStateUpdated` yang membaca `max('meeting_number')` dari **Model** `LessonJournal`. Saat disimpan, **Controller** menulis record jurnal ke **Database**.

Absensi diisi melalui `AttendancesRelationManager` (action **"Isi Absensi Kelas"** / `take_attendance`). **View** action ini menampilkan daftar tanggal yang dibatasi `getAvailableDates()` (hanya hari sesuai jadwal mengajar pada rentang periode), sebuah *toggle* libur, dan *Repeater* status kehadiran per siswa. **Controller** action memanggil **Model** `Attendance::updateOrCreate()` (unik per `teaching_assignment_id + student_id + date`). Setelah penyimpanan, **Observer** `AttendanceObserver::saved()` berjalan otomatis: ia menghitung ulang jumlah Hadir/Izin/Sakit/Alpha (mengabaikan status `holiday`) dan persentase kehadiran, lalu menyimpannya ke `attendance_summaries` via `AttendanceSummary::updateOrCreate()`. Inilah contoh lapisan kendali berbasis *event* — rekap selalu sinkron tanpa perhitungan manual di View.

### UC5 — Input Nilai Sumatif/Formatif & Hitung Nilai Akhir

Inti penilaian berada pada `AssessmentsRelationManager` (di bawah `TeachingAssignmentResource`). Pada **View**, Guru menyusun asesmen (kategori formatif/sumatif, teknik, bobot, kaitan TP) dan menginput nilai per siswa lewat action `input_grades`. Sebelum menyimpan rencana asesmen ber-formula *weighting*, **Controller** menjalankan validasi pada *hook* `before()` untuk memastikan total bobot sumatif tidak melebihi 100% (jika lebih, `action()->halt()` membatalkan simpan).

Saat nilai disimpan, **Controller** action menulis ke **Model** `Grade::updateOrCreate()` di dalam `Grade::withoutEvents()` (mematikan observer sementara untuk menghindari perhitungan berulang/N+1), lalu **secara eksplisit** memanggil logika domain `TeachingAssignment::calculateFinalGrade($studentId)` untuk tiap siswa. Method Model ini menghitung skor akhir sesuai cabang `grading_formula` (**average**, **weighting**, atau **percentage** berbasis KKTP), membatasi maksimal 100, dan membulatkannya. Hasilnya dikonversi menjadi predikat A–E oleh **Service** `GradeRangeResolver::resolve()` (membaca `grade_ranges`, dengan *fallback* berbasis KKTP), kemudian disimpan ke **Model** `FinalGrade::updateOrCreate()` → **Database**. Sebagai jalur alternatif, jika sebuah `Grade` tersimpan di luar aksi massal, **Observer** `GradeObserver` akan menjalankan perhitungan yang sama, dengan penjagaan: jika `FinalGrade` sudah `is_locked` atau `is_manual_override`, observer berhenti agar nilai rapor tidak tertimpa.

**Penanganan nilai formatif & Booster.** Nilai akhir dihitung dari asesmen **sumatif** (TAHAP 1, tiga formula), lalu ditambah kontribusi **booster formatif** (TAHAP 1b) sesuai setelan `booster_mode` **per SK Mengajar**: `none` (nonaktif — identik sumatif murni), `weight` (`nilai_formatif × booster_value%`, **akumulatif**), atau `point` (tiap formatif terisi memberi `+booster_value` poin). Hasil dibatasi maksimal 100 (TAHAP 2). Helper terpusat `TeachingAssignment::boosterContribution()` dipakai bersama oleh `calculateFinalGrade()` dan `calculateScorePerTp()` sehingga **nilai akhir & narasi konsisten**. Guru memilih mode/nilai pada form SK Mengajar.

> **Spesifikasi & pengujian:** `docs/rancangan-booster-formatif.md` (desain), `docs/rancangan-implementasi-booster.md` (implementasi). Diuji oleh `tests/Unit/CalculateFinalGradeTest.php` (13 kasus) & `tests/Unit/CalculateScorePerTpBoosterTest.php` (4 kasus konsistensi per-TP) — seluruhnya lulus.

### UC6 — Kunci dan Cetak Rapor

UC ini berpusat pada halaman `ViewRapor` (`RaporResource`). **(1) Catatan & absensi:** action `input_homeroom_notes` pada **View** menampilkan *Repeater* per siswa; **Controller** menyimpan ke **Model** `StudentReport::updateOrCreate()` dalam transaksi. **(2) Generate deskripsi:** action `generate_narasi` memanggil **Service** `DescriptionGeneratorService::generate()`, yang menghitung rata-rata nilai per TP, mengubahnya ke predikat via `GradeRangeResolver`, lalu menyusun kalimat dari `NarrativeTemplate`; hasilnya ditulis ke `FinalGrade.narrative_description`. **(3) Kunci:** action `kunci_semua` mengubah **Model** `FinalGrade` menjadi `is_locked = true` beserta `locked_at`. **(4) Cetak:** action `cetak_rapor` membuka `route('rapor.print')` di tab baru, yang ditangani **HTTP Controller** `RaporPrintController::show()`. Controller ini memanggil **Service** `RaporExportService::getRaporData()` untuk mengagregasi nilai akhir, narasi, kokurikuler, ekstrakurikuler, dan rekap absensi dari **Database**, menimpa data absensi/catatan dengan `StudentReport`, lalu merender **View** Blade `resources/views/rapor/print.blade.php`.

> **Catatan as-is (untuk diperbaiki):** pada langkah *generate deskripsi*, `ViewRapor::generate_narasi` saat ini **tidak memeriksa `is_locked`**, sehingga menjalankan generate setelah penguncian akan menimpa narasi siswa yang sudah dikunci (dan menimpa deskripsi manual). Hal ini **berbeda** dengan `ViewGradebook::generate_narasi` yang sudah menyertakan `if ($finalGrade->is_locked) continue;`. Penyelarasan kedua jalur ini menjadi rencana perbaikan.

#### Pendalaman — Mesin Generator Deskripsi Rapor (Rule Engine)

Generator deskripsi adalah salah satu **keunikan SIPDL**: narasi rapor tidak diketik manual, melainkan **dihasilkan otomatis berbasis aturan (*rule engine*)** oleh `DescriptionGeneratorService::generate()`. Tahapannya:

1. **Skor per TP.** `calculateScorePerTp()` menelusuri rantai `assessment → assessment_learning_objective → learning_objective`, lalu menghitung **rata-rata nilai siswa per Tujuan Pembelajaran (TP)**. TP tanpa nilai dilewati. (Seluruh asesmen + nilai + TP dimuat dalam satu kueri ber-*eager-load* untuk mencegah N+1 — lihat sub-bab C.)
2. **Konversi ke predikat.** Tiap rata-rata TP diubah ke huruf **A–E** lewat `GradeRangeResolver::resolve()` (anchor KKTP).
3. **Identifikasi TP terkuat & terlemah.** TP diurutkan berdasarkan prioritas grade; diambil **TP-Max** dan **TP-Min**.
4. **Penyusunan kalimat.** `buildGradeBasedNarrative()` mengambil template dari `NarrativeTemplate::getTemplate()` dengan **hierarki: template Guru → default Admin → *fallback* hardcoded**, lalu mengganti placeholder `[TP]` dengan atribut TP. Jika semua TP bergrade sama → **satu kalimat**; jika berbeda → **dua kalimat** (kompetensi terkuat & yang perlu ditingkatkan).
5. **Konjungsi adaptif.** `resolveConjunction()` memilih penghubung sesuai kombinasi grade: `", serta "` (kedua sisi lulus), `", namun "` (kontras lulus–tidak lulus), atau `", dan juga "` (keduanya belum tuntas).

Hasil akhir disimpan ke `FinalGrade.narrative_description` dan tampil di rapor cetak (UC6 langkah Cetak). Dengan kata lain, generator ini adalah **jembatan antara data kuantitatif (nilai per TP) dan deskripsi kualitatif rapor** Kurikulum Merdeka — memangkas beban penulisan manual guru sekaligus menjaga konsistensi bahasa.

> **Catatan:** `calculateScorePerTp` memakai **basis asesmen sumatif saja**, lalu menambahkan **booster formatif** melalui `boosterContribution()` (sama seperti `calculateFinalGrade`, dibatasi maksimal 100). Dengan begitu nilai formatif tidak lagi mendistorsi basis skor per-TP, dan perhitungan narasi konsisten dengan nilai akhir.

### UC7 — Memantau Kinerja Penilaian Guru (Kepala Sekolah)

Pemantauan kinerja bersifat **read-only** dan disajikan lewat **Widget** Filament pada dasbor: `KinerjaGuruWidget` dan `RingkasanNilaiKelasWidget`. Pada **View**, `KinerjaGuruWidget` dibatasi `canView()` hanya untuk peran `headmaster`/`super_admin`. **Controller** (kelas Widget) tidak melakukan kueri agregasi sendiri, melainkan **mendelegasikan** ke **Service** `NilaiVisualisasiService::getKinerjaGuru()`.

Service inilah lapisan kendali domain sesungguhnya: ia mengambil seluruh `TeachingAssignment` (mapel wajib, periode aktif) beserta relasi `finalGrades` (semester aktif) dan `lessonJournals` dari **Model/Database**, menghitung jumlah siswa per kelas (`Enrollment` ter-*batch*), lalu menurunkan metrik **kelengkapan nilai** (`persen_nilai`) dan **jumlah jurnal**, serta status "Lengkap/Sebagian/Belum". Hasil (array) dikembalikan ke Widget, yang menampilkannya sebagai tabel pada **View**. Karena tidak ada operasi tulis, alur ini murni Model→Service→View.

### UC8 — Memantau Perkembangan Akademik (Siswa & Orang Tua)

Pemantauan oleh siswa diimplementasikan pada halaman `MyGrades` (`app/Filament/Pages/Student/MyGrades.php`). Pada **View/Controller**, `canAccess()` membatasi akses ke peran `student` yang memiliki profil `Student`, dan `getViewData()` menyaring data **berdasarkan sesi login** (`Auth::user()->student`). Controller mengambil `Enrollment` aktif pada periode terpilih, lalu memuat **Model** `FinalGrade`, `Grade` (rincian formatif/sumatif), dan `AttendanceSummary`, menyusunnya menjadi data akademik vs kokurikuler untuk dirender Blade `my-grades`.

Untuk grafik perkembangan **longitudinal** dan akses Orang Tua, lapisan kendali memakai **Service** `NilaiVisualisasiService`: `canViewStudent()` menegakkan otorisasi (siswa hanya dirinya; wali hanya anak via `guardian_user_id`; guru sesuai yang diajar), `getNilaiLongitudinal()` merangkai `FinalGrade` lintas periode menjadi tren nilai per mapel, dan `getAccessibleStudents()` menyediakan daftar siswa yang boleh dilihat. Seluruh alur bersifat baca; ketika rapor dicetak melalui jalur UC6, `RaporExportService` menandai `isOfficial = false` bagi peran siswa/wali sehingga keluaran berupa **rapor bayangan (*unofficial*)**.

#### Pendalaman — Grafik Perkembangan Longitudinal

Sesuai nama sistem (*Longitudinal*), **keunikan SIPDL** yang kedua adalah penyajian **tren nilai siswa lintas semester/tahun ajaran**, bukan sekadar nilai satu periode. Mesinnya `NilaiVisualisasiService::getNilaiLongitudinal($studentId)`:

1. **Bangun sumbu waktu.** Mengambil seluruh `Enrollment` siswa lalu mengurutkannya berdasarkan `academicPeriod.start_date` — menjadi sumbu-X (label periode, mis. `2024/2025 Ganjil`).
2. **Muat nilai sekali jalan.** Seluruh `FinalGrade` siswa dimuat dalam **satu kueri** (`with('teachingAssignment.subject')`), kemudian **difilter dari koleksi** per periode (cocok `semester` + `academic_period_id` + `classroom_id`) — sehingga tidak ada kueri berulang antar periode (anti-N+1, lihat sub-bab C).
3. **Bentuk struktur `[periode][mapel] => nilai`.** Inilah dataset grafik.
4. **Pembatasan per peran.** Bila pengakses adalah **guru mapel (bukan wali kelas)** siswa tersebut, hanya mapel yang ia ajar yang ditampilkan; otorisasi per-siswa ditegakkan `canViewStudent()`.

Dataset dirender sebagai **grafik garis + batang (Chart.js)** oleh `NilaiSiswaWidget` (`Filament\ChartWidget`, lengkap dengan filter per mapel dan sumbu-Y 0–100) serta halaman `DetailNilaiSiswa`. Grafik inilah yang memvisualkan apakah seorang siswa **menanjak, stagnan, atau menurun** dari semester ke semester.

> **Catatan as-is:** `NilaiSiswaWidget::canView()` saat ini mengembalikan `false` (widget grafik sengaja dinonaktifkan), namun mesin `getNilaiLongitudinal()` tetap aktif dan dipakai oleh halaman `DetailNilaiSiswa`.

### UC9 — Login (Semua Aktor)

Autentikasi memakai halaman `App\Filament\Pages\Auth\Login` yang meng-*extend* halaman login bawaan Filament. Pada **View**, form login menerima satu field fleksibel (Username/NISN/NIP atau Email) + password. **Controller** (komponen Login) menjalankan `getCredentialsFromFormData()` yang mendeteksi tipe kredensial — mengandung "@" diperlakukan sebagai `email`, selain itu sebagai `username` — lalu mencocokkannya ke **Model** `User` di **Database**.

Setelah kredensial cocok, lapisan Model menegakkan kebijakan akses: `User::canAccessPanel()` mengembalikan nilai `is_active`, sehingga hanya akun aktif yang boleh masuk panel. Peran pengguna dikelola melalui **Spatie Permission** (trait `HasRoles` pada Model `User`), dan navigasi/menu yang tampil mengikuti peran tersebut (mis. `shouldRegisterNavigation()` / `canAccess()` di tiap Resource/Page). Bila autentikasi gagal, Controller memanggil `throwFailureValidationException()` untuk menampilkan pesan kesalahan tanpa membuat sesi.

---

## C. Optimasi & Kesiapan untuk Shared Hosting

**Keunikan teknis SIPDL** yang ketiga: logika dibangun **ketat dengan asumsi deployment di shared hosting** — sumber daya kecil (CPU/RAM terbatas), **tanpa worker/daemon**, dan kuota kueri/disk yang sensitif. Empat strategi diterapkan:

### C.1 Konfigurasi runtime tanpa daemon
`.env.example` disiapkan khusus untuk shared hosting (tanpa Redis/Supervisor/Octane):
- `QUEUE_CONNECTION=sync` — job dieksekusi **inline** pada request, tidak butuh *queue worker* yang umumnya tak tersedia di shared hosting.
- `CACHE_STORE=database` & `SESSION_DRIVER=database` — memakai MySQL, tanpa Redis/Memcached.
- `LOG_CHANNEL=daily`, `LOG_DAILY_DAYS=7`, `LOG_LEVEL=error` — rotasi log harian, retensi 7 hari, hanya level *error*, agar file log tidak membengkak memenuhi kuota disk.

### C.2 Pencegahan N+1 query (hemat kueri DB)
Karena setiap kueri berbiaya di shared hosting, jalur berat diberi *eager loading* & batching eksplisit (ditandai komentar `✅ PERBAIKAN N+1` di kode):
- **Bulk input nilai** mematikan observer sementara (`Grade::withoutEvents()`) lalu menghitung ulang sekali per siswa — mencegah ledakan 60+ kueri untuk ~30 siswa (UC5).
- **Observer** memuat rantai relasi dalam satu kueri (`assessment.teachingAssignment.academicPeriod`; `teachingAssignment.academicPeriod`).
- **`NilaiVisualisasiService`** mem-*batch* jumlah siswa per kelas via satu kueri `GROUP BY`, dan memuat seluruh `FinalGrade` sekali lalu memfilter dari koleksi (UC7 & UC8).
- **`DescriptionGeneratorService`** memuat semua asesmen + nilai + TP dalam satu kueri sebelum perulangan per siswa (UC6).

### C.3 Pola Snapshot (geser beban hitung ke waktu tulis)
`final_grades` dan `attendance_summaries` adalah **snapshot** yang dihitung & disimpan saat data berubah (oleh observer/aksi). Akibatnya **render rapor & dasbor cukup membaca** tanpa menghitung ulang — operasi baca yang murah, ideal untuk shared hosting.

### C.4 Caching identitas sekolah
`SchoolProfile` yang dipakai lintas tampilan di-*cache* agar tidak dikueri berulang:
- `AppServiceProvider` memakai `Cache::remember('school_profile_global', 30 menit, …)` (komentar kode menjelaskan alasan memilih cache ketimbang `static` closure yang bisa *stale* di Octane/worker).
- `AdminPanelProvider` menyimpan profil pada properti `static` per-request, menggantikan `SchoolProfile::first()` yang sebelumnya dipanggil 3× per request.

Kombinasi keempatnya membuat aplikasi **ringan, minim kueri, dan tidak bergantung pada layanan eksternal (Redis/worker)** — sesuai batasan shared hosting.

---

## D. Catatan Keterbatasan & Diskrepansi (as-is)

Agar pembahasan tetap jujur terhadap kode yang berjalan:

1. **Arsitektur bukan MVC murni.** Mayoritas "Controller" adalah komponen **Filament/Livewire** (stateful), bukan HTTP Controller klasik; satu-satunya HTTP Controller pada alur inti adalah `RaporPrintController`. Pemetaan MVC dipakai sebagai kerangka analisis (lihat sub-bab A).
2. **UC3 — standardisasi kode TP** bersifat **konvensi via `helperText`** dan batas `maxLength(20)`, **bukan** validasi format otomatis.
3. **UC6 — `generate_narasi` di `ViewRapor`** belum memeriksa `is_locked` (dapat menimpa narasi terkunci/manual), berbeda dengan `ViewGradebook` yang sudah menghormatinya — menjadi rencana perbaikan.
4. **Inkonsistensi penamaan peran wali.** Sebagian kode memakai slug `guardian` (seeder) sementara `NilaiVisualisasiService`/`MyQuestionnaires` memeriksa `wali_siswa`. Untuk dokumentasi formal, otorisasi dijelaskan melalui mekanisme Spatie `HasRoles`.
5. **Cakupan.** Pembahasan ini berfokus pada sembilan use case akademik inti; modul di luar itu (mis. BK) tidak dibahas.
6. **UC5/UC6 — Booster formatif (aktif).** `calculateFinalGrade()` memakai sumatif **+ booster** (`none`/`weight`/`point`, diatur per SK Mengajar), dan `calculateScorePerTp()` memakai **basis sumatif + booster** yang sama — keduanya via `boosterContribution()`, dibatasi maksimal 100. Detail: `docs/rancangan-booster-formatif.md` & `docs/rancangan-implementasi-booster.md`.
