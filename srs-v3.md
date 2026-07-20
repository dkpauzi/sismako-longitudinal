# SYSTEM_SPECIFICATION: SIPDL — Sistem Informasi Peserta Didik Longitudinal
**Version:** 3.0.0
**Institution:** SMPN 45 Sijunjung
**Architecture:** TALL Stack (TailwindCSS, Alpine.js, Laravel 11, Livewire) + Filament Panel Builder v3.3.
**Type:** Companion System (internal grade compilation, longitudinal tracking & real-time monitoring). Not a replacement for the national e-Rapor.

> **Status of this document:** v3 supersedes v1/v2 and is written to match the ACTUAL implemented state after a full architectural audit & hardening cycle. Where v2 described planned or SMA-scoped behaviour, this document records what the code truly does today. A changelog vs v2 is at §9.

> **[MUST] Living-document rule.** Any change to the **database schema**, a **core workflow**, or the **RBAC/permission matrix** MUST be reflected in this file **within the same commit** as the code change. A PR/commit that alters those surfaces without a matching SRS edit is considered incomplete.

---

## 1. SCOPE & DOMAIN CONSTRAINTS
- **Strictly SMP (Junior High).** Domain is fixed to **Fase D, kelas 7–9**. The former multi-tier SMA/SD scaffolding has been removed: `learning_objectives.phase` ENUM = `['D']`, classroom grade options = `{7,8,9}`, and the SMA "Peminatan / Mata Pelajaran Pilihan" resource and its importer were deleted. Subject `type` remains `mandatory | kokurikuler | elective | extracurricular` (elective is retained as "Muatan Lokal", a valid SMP concept).
- **Longitudinal integrity is the core promise.** Student academic history must survive class promotions and academic-year transitions without loss or corruption. This constraint governs deletion, grade-locking, and cross-period isolation rules (see §5).

## 2. ENVIRONMENT & INFRASTRUCTURE
- **Target:** cPanel / shared web hosting (≈512 MB RAM, low `max_execution_time`).
- **[FORBIDDEN]** Background daemons (Supervisor) and Redis brokers.
- **[MUST]** `QUEUE_CONNECTION=sync`. All heavy tasks run synchronously inside the HTTP cycle.
- **[MUST]** Synchronous chunking for bulk work: importers use `ImportAction->chunkSize(50)`; mass narrative generation, class promotion, and **semester continuation** use **Livewire event-recursion** (one browser round-trip per chunk → fresh PHP timer) to avoid timeouts.
- **[DECISION] Import performance under `sync`.** Switching to `QUEUE_CONNECTION=database` for chunked imports was **REJECTED** — shared hosting has no persistent worker, so enqueued imports would never run (worse than a timeout). Instead, the dominant per-row cost (2× bcrypt for student+guardian accounts) is cut by **capping the import bcrypt cost at 8 rounds** — `min(8, config('hashing.bcrypt.rounds'))` in `StudentImporter`/`TeacherImporter`. The `min()` is required: the `hashed` cast rejects any pre-hash whose cost exceeds the configured rounds (e.g. `BCRYPT_ROUNDS=4` in testing). Manual account creation keeps the default cost.
- **[MUST]** Locale `id`. `lang/id/validation.php` and `lang/id/auth.php` are present so validation/auth messages render in Indonesian.
- **[MUST]** Initial admin credentials come from `env('ADMIN_EMAIL')` / `env('ADMIN_PASSWORD')` (no hardcoded defaults in production).

## 3. ROLE-BASED ACCESS CONTROL (RBAC)
Authorization is enforced via Laravel Policies + Spatie Permission. **Spatie roles are the single source of truth**; the legacy `users.role` ENUM column is kept in sync (all account-linking dropdowns query `whereHas('roles', …)`, and `User::syncLegacyRoleColumn()` derives the column from the highest-priority Spatie role on account create/edit).

### 3.1 Roles
- `super_admin` — absolute access; owns Spatie roles & seeders.
- `admin` — school clerk. Master data (Students, Teachers, Subjects, Classrooms), Smart Importer, academic-year transitions, promotion wizard, extracurricular & P5 grade management.
- `headmaster` (Kepala Sekolah) — read-only monitoring: aggregate dashboards, class averages, teacher performance (grade-input completeness), longitudinal charts, report-card recaps.
- `teacher` — operational access to own teaching assignments: sets KKTP/formula/booster, defines Learning Objectives (TP), inputs scores, approves remedials, generates narratives. As **active homeroom teacher (Wali Kelas)** also inputs P5 & extracurricular grades and locks report cards.
- `guru_bk` — Guidance & Counseling. Counseling records + VAK questionnaire distribution/results. **[FORBIDDEN]** cannot author cognitive questions.
- `student` — read-only personal: timetable, own grades/longitudinal chart, takes VAK when ticketed.
- `guardian` (Wali Siswa) — read-only portal for linked children (grades, attendance, shadow report). One guardian may cover multiple siblings.

### 3.2 Permission Provisioning (SOP)
- **[MUST]** `RolePermissionSeeder` is the single source of truth. It creates **every** permission explicitly (`Permission::firstOrCreate`, ~258 permissions across 21 resources + page/widget permissions) — **no dependency on `shield:generate`**. Permission names match the exact strings checked in `app/Policies/*`.
- **[MUST]** Importers assign ONLY `student`/`guardian` (student import) or `teacher` (teacher import). `headmaster`/`guru_bk`/`admin` are assigned manually — never read from a spreadsheet (prevents privilege escalation).
- **[FORBIDDEN]** Hardcoded/fictitious role slugs in code (`'guru'`, `'Guru'`, `'wali_siswa'`, `'Siswa'`, `'Super Admin'`). All role checks use the canonical slugs above.

## 4. FUNCTIONAL MODULES

### 4.1 Grading Engine (Sumatif + Formatif Booster)
- **Assessment categories:** `sumatif_lingkup_materi` (Sumatif Harian), `sumatif_akhir_semester` (SAS), `formatif_poin` (booster checklist), `formatif_deskripsi`. Each summative assessment links to one/more **TP** via the `assessment_learning_objective` pivot.
- **Final score = min(100, summativeScore + booster).**
  - `summativeScore` per `grading_formula`: `average` | `weighting` (percentage weights, null-safe normalisation) | `percentage` (KKTP mastery %).
  - **Booster** per SK-Mengajar `booster_mode`: `none` | `weight` (Σ formatif×value%) | `point` (count×value). Toggling booster on a teaching assignment recomputes final grades.
- **[MUST] `final_grades` is never written directly by seeders.** It is a computed snapshot derived from raw per-TP `grades`. All writes route through **`FinalGrade::snapshot($studentId,$taId,$semester,$attrs)`** — the single guarded writer that runs in a `DB::transaction()` with `lockForUpdate()` and **aborts if the record is `is_locked` or `is_manual_override`** (respected by the observer, bulk saves, remedial recalcs, and narrative generation alike).
- **Predicate (A–E)** resolved by `GradeRangeResolver` from KKTP bands (default KKTP 75 → A 91–100, B 83–90, C 75–82, D 60–74, E <60). Internal-only; never exposed raw to student/guardian.

### 4.2 Remedial Calibration
- **[FORBIDDEN]** auto-inflation of sub-KKTP scores. **Workflow:** flag `< KKTP` → teacher explicitly approves → `DB::transaction` stores pure `original_score`, updates `score`, increments `remedial_attempts`.

### 4.3 Guidance & Counseling (BK) + VAK
- Counseling records (`bk_counseling_records`) with categories & session types; VAK questionnaire (`bk_questionnaires` + questions/options) is a deterministic, non-AI rule-based instrument distributed as **tickets** (`bk_student_responses`, NOT NULL `academic_period_id`). `guru_bk` evaluates; results hidden from student until evaluated.

### 4.4 Longitudinal Analytics
- `getNilaiLongitudinal()` returns per-period → per-subject final scores, filtered by the exact period **and** classroom of each enrollment (no cross-period bleed). Access-scoped: staff see all; teacher sees taught subjects (or all as homeroom); guardian/student see own.

### 4.5 P5 (Kokurikuler) & Extracurricular — grade input only
- **P5:** `kokurikuler_grades` (student + period, narrative). Multiple projects per semester allowed.
- **Extracurricular:** `student_subject_enrollments` on an `extracurricular`-type teaching assignment (**predicate + narrative only — no raw scores / no numeric processing**, P5-style). Predicate ENUM (UI): `Sangat Baik | Baik | Cukup | Kurang`.
- **External coaches (no account, no NIP).** `teaching_assignments.teacher_id` is **nullable**; a `teaching_assignments.external_instructor_name` string carries the outside coach's name (shown on the rapor). An extracurricular SK is created with **either** an internal `Teacher` **or** an external name (enforced in `TeachingAssignmentResource`: teacher required unless an external name is filled for an ekskul SK). `TeachingAssignment::instructorDisplayName()` resolves teacher → external name → "—".
- **Grading UI.** Two equivalent entry points, both P5-easy: the `ExtracurricularStudentsRelationManager` (under a teaching assignment) **and** a dedicated **`ExtracurricularGradeResource`** menu ("Nilai Ekstrakurikuler") where Admin registers a student into an ekskul and Admin/Homeroom inputs predicate + description directly.
- **[MUST] Scoped ownership.** Grading is delegated to **Admin (register + grade) and the active Homeroom Teacher (grade only)**. `StudentSubjectEnrollmentPolicy` (like `KokurikulerGradePolicy`) derives the class from the **student's own enrollment** at the SK's period — NOT the SK's `classroom_id` — so a teacher may edit only if they are the `ClassHomeroom (is_current)` of **that specific student's active class**. This correctly handles cross-class activities (e.g. Pramuka mixing rombels). A non-homeroom teacher, a past-period homeroom, or a homeroom of a different class is rejected; students/guardians never see the menu (`viewAny` denied).
- **[NOTE]** Extracurricular permissions reuse the `*_student::subject::enrollment` set (there is no separate `*_extracurricular` permission); `teacher` intentionally lacks `create/update/delete` there, so registration is Admin-only while the homeroom grading path is granted by the policy, not a permission.
- **[NOTE]** Extracurricular enrollment has **no CSV importer** — it is a manual workflow.

### 4.6 Smart Importer & Manual Parity
- **CSV only** (League\Csv reader; `.xlsx` is NOT parsed). Delimiter `;`. Importers: Students, Teachers, Teaching Assignments (SK Mengajar), Subject Schedules, Learning Objectives (TP).
- **[MUST] Manual/Import parity:** `CreateStudent` and `CreateTeacher` auto-provision login accounts inside a `DB::transaction()` identically to the importers (student → student+guardian accounts; teacher → teacher account; username/password from NISN/NIP). Re-import uses `updateOrCreate` consistently.
- **[NOTE]** The Subject-Schedule importer uses a wider column set (re-resolves the parent teaching assignment by `tahun_ajaran, semester, guru, mata_pelajaran, kelas`, then adds `hari, jam_mulai, jam_selesai`) — divergent from the other importers.

### 4.7 Academic Transition (Promotion, Graduation & Semester Continuation)
Two DISTINCT enrollment-mutation flows, split by the direction of the transition. Both run in `DB::transaction()`, are chunked via Livewire event-recursion, and fill `promoted_from_enrollment_id` to preserve the longitudinal chain. All guards are enforced **BOTH in the UI and server-side in `PromotionService`**.

- **A. Naik / Tinggal Kelas & Kelulusan** — `StudentPromotionWizard` + `PromotionService::processChunk()`.
  - **[MUST] Temporal guard:** source period must be **Genap (even)**; for `promoted`/`retained` the target must be **Ganjil (odd) of the next academic year** (`target.start_year == source.end_year`). The wizard's target dropdown is filtered to match; `PromotionService::assertYearEndTransition()` re-checks server-side. Promoting from a Ganjil source is rejected with a pointer to "Lanjut Semester".
  - **[MUST] Grade-level gates:** promoted → target grade = source+1; retained → same grade; graduated only at kelas 9. Cross-grade jumps rejected.
  - **[MUST] Lock guard:** a student is blocked from any transition while **any existing `final_grade` in the source period is unlocked** (`PromotionService::assertReportLocked()`). Defensive: subjects with no grade row (e.g. an ungraded elective) do NOT trap the student.
  - Closes the old enrollment (status `promoted`/`retained`/`graduated`).
- **B. Lanjut Semester** — `SemesterContinuationWizard` + `PromotionService::processSemesterChunk()`.
  - **[MUST] Temporal guard:** strictly **Ganjil → Genap of the SAME academic year**. The target Genap period is resolved automatically.
  - Student **stays in the same class & grade**; a new Genap enrollment is created (same `classroom_id`), linked via `promoted_from_enrollment_id`. The Ganjil enrollment is left `active` as that semester's record (queries are period-scoped, so no bleed).
  - The same lock guard as (A) applies before continuation.
- **[NOTE] Architecture:** this is a deliberate, documented divergence from the earlier "wizard is the ONLY mutation path" rule — there are now **two** mutation paths, one per direction, each with its own temporal guard.
- **[MUST]** On graduation, the guardian account is deactivated ONLY if no other active sibling remains.

### 4.8 Report Card (Rapor)
- Recap anchored on `ClassHomeroom`; homeroom teachers see own classes, staff see all.
- **Cross-period isolation:** attendance summary, extracurricular, and P5 are filtered by the specific report period (via `teaching_assignment.academic_period_id`), not merely semester parity. P5 renders ALL projects for the semester.
- **Academic recap excludes non-numeric subjects:** `ViewRapor` compiles the academic progress/recap from `mandatory|elective` teaching assignments only — **both `kokurikuler` and `extracurricular` are excluded** (they have no numeric FinalGrade), mirroring `RaporExportService`. Ekskul/P5 appear via their own sections, not the academic table.
- **Grade lock:** `is_locked` finalises grades; the observer/bulk paths will not overwrite locked or manually-overridden grades.
- **Historical read-only:** report cards of inactive periods remain viewable/printable; mutation actions (lock/unlock, generate narrative) are hidden on inactive periods so history need not be re-activated (which would thaw the freeze).
- Auto-narrative generation is chunked (5 students/batch) via Livewire event-recursion.

### 4.9 Learning Objectives (TP) Cross-Period Copy — "Salin TP"
- **Problem:** `learning_objectives` are bound to `academic_period_id`, so every new semester starts as a blank slate.
- **Workflow:** a header action ("Salin TP Antar-Periode") on the Learning Objectives list page asks **Source Period** and **Target Period** (defaults to the active period). `LearningObjectiveCopyService::copy()` replicates each source TP (subject, grade_level, phase, code, content, attribute, teacher_id) into the target period inside a `DB::transaction()`.
- **[MUST] Idempotency:** a target TP with the same `(subject_id, code)` is **skipped, never duplicated** (fallback key `(subject_id, content)` when `code` is null). Re-running the copy is safe; existing target TPs are not overwritten.
- **[MUST] RBAC (scope guard):** `super_admin`/`admin` may copy **any** subject; a `teacher` may copy **only** subjects they are actively assigned to teach in the **target** period (`teaching_assignments`). Resolved by `allowedSubjectIdsFor()` (null = all; `[]` = none → no-op). This is a **bulk** operation gated by role — deliberately distinct from per-TP authoring, so `admin` can bulk-copy even though the per-record `create_learning::objective` permission remains teacher-only.

## 5. DATA INTEGRITY PROTECTIONS
- **[MUST] Cascade-delete guards.** Delete / bulk-delete actions are hidden/halted when the record already holds longitudinal history: `AcademicPeriod` & `Classroom` (enrollments/homerooms), `Subject` (teaching assignments), `TeachingAssignment` (final grades/assessments), `Student` (enrollments), and both Enrollment relation managers (grades or promotion-chain source). This blocks FK-cascade wipes of research data.
- **[MUST]** Single grade-snapshot writer (`FinalGrade::snapshot`) — see §4.1.
- **[MUST]** ENUM ↔ form parity (enrollment status, semester, phase, subject/assessment types); all `match()` on ENUM state carry a `default` branch.
- **[MUST]** DB-unique columns (email, nisn, nip, subject code) have `->unique(ignoreRecord:true)` in forms (friendly errors, not SQL 500).

## 6. NON-FUNCTIONAL REQUIREMENTS
- **Performance (shared hosting):** no N+1 in list views (correlated `withCount`, eager loads, pre-computed aggregates); no full-table hydration (lazy `getSearchResultsUsing`, no `Model::all()->pluck` in selects); bulk work chunked.
- **Security:** predicate/grade internals hidden from student/guardian payloads; role checks canonical; policies authoritative.
- **Localization:** all UI Indonesian; validation/auth language files present.
- **Testing:** automated feature/unit suite (132 passing) covering grade-lock, sibling-guardian, promotion gates, cross-period rapor isolation, P5 ownership, importer parity, and Kepsek monitoring metrics.

## 7. NAVIGATION / MENU ORDER
Menus follow the admin INPUT dependency, top-to-bottom. Group order: **Manajemen Sistem → Akademik → (teacher views) → Bimbingan Konseling → Web Sekolah → Pengaturan**. Within **Manajemen Sistem**: Tahun Ajaran → Manajemen Akun → Pengaturan Sekolah. Within **Akademik**: Data Siswa → Data Guru → Mata Pelajaran → Ruang Kelas → Kelas Ajar (SK Mengajar) → Tujuan Pembelajaran (TP) → Jurnal KBM → Nilai P5 → Nilai Ekstrakurikuler → Rekap Rapor → Grafik Nilai Siswa → Proses Kenaikan Kelas → Lanjut Semester.

## 8. DATA MODEL (key tables)
`users` (role ENUM incl. super_admin/guru_bk), `teachers`, `students` (user_id, guardian_user_id), `academic_periods`, `classrooms`, `class_homerooms` (is_current), `enrollments` (status ENUM `active|promoted|retained|graduated|dropped`, `promoted_from_enrollment_id`), `subjects`, `teaching_assignments` (**`teacher_id` nullable** + **`external_instructor_name`** for outside ekskul coaches, `nullOnDelete`; grading_formula, kktp, booster_mode/value), `subject_schedules`, `learning_objectives` (phase D), `assessments` (+ `assessment_learning_objective` pivot), `grades` (score, original_score, remedial_attempts), `final_grades` (final_score, grade_label, is_locked, is_manual_override), `attendance` + `attendance_summaries`, `grade_ranges`, `kokurikuler_grades`, `student_subject_enrollments`, BK tables, school-profile/CMS tables.

## 9. CHANGELOG vs v2
- **SMP-only enforcement:** deleted the SMA elective resource/importer; narrowed `phase` ENUM to `D`; grade options 7–9; removed 4 obsolete P5/ekskul tables (`extracurriculars`, `student_extracurriculars`, `projects`, `project_grades`) and their model.
- **RBAC:** eradicated fictitious role slugs; manual permission provisioning replaces `shield:generate`; `users.role` reconciled with Spatie; `guardian` (not "parent") is the canonical wali role.
- **Longitudinal hardening:** cascade-delete guards; single `FinalGrade::snapshot` writer with lock/override enforcement + atomicity; server-side promotion grade gates; sibling-guardian check; cross-period rapor isolation; historical read-only report cards.
- **Grading:** formatif booster (none/weight/point) formalised; final grade strictly derived from per-TP raw grades.
- **Parity & housekeeping:** manual student/teacher creation now auto-provisions accounts like the importer; `chunkSize(50)` on ImportActions (dead `$chunkSize` property removed); dead config `show_score_sd` removed; localization files added; env-based admin credentials.
- **Ownership:** P5 & extracurricular grading scoped to Admin + active Homeroom teacher (of the student's own class).
- **UX:** navigation reordered by input dependency.

## 10. CHANGELOG v3.0.0 → v3.1.0 (2026-07-20)
- **Academic transition split (§4.7):** `StudentPromotionWizard` restricted to Even→Odd (next year) with lock + grade-level guards; new **`SemesterContinuationWizard`** for Odd→Even (same year, carry enrollment). Two mutation paths, one per direction.
- **Import performance (§2):** DB-queue switch rejected (no worker on shared hosting); import bcrypt cost capped at `min(8, config rounds)` to prevent 60s timeouts without breaking the `hashed` cast.
- **External ekskul coaches (§4.5, §8):** `teaching_assignments.teacher_id` made nullable + `external_instructor_name` added (`nullOnDelete`). New **`ExtracurricularGradeResource`** ("Nilai Ekstrakurikuler") for P5-style register + grade. Ekskul grading policy re-scoped to the **student's own enrollment class** (correct for cross-class activities). Chose to EXTEND the existing `student_subject_enrollments` subsystem rather than resurrect the dedicated ekskul tables removed in v3.0.
- **Governance:** living-document rule adopted (schema/workflow/RBAC changes ship with the SRS edit in the same commit).
- **Rapor fixes:** PDF/Word exports via `streamDownload` (Livewire-safe); progress bars reactively bound via Alpine; NISN/NIPD/NIK numeric validation via regex; report lock blocked when narrative empty; semester label localized (Ganjil/Genap).
- **Null `teacher_id` hardening:** all teaching-assignment teacher renders route through `TeachingAssignment::instructorDisplayName()` (ViewRapor, MyGrades, StudentScheduleWidget, TeachingAssignmentResource, SubjectsRelationManager) — external-coach ekskul no longer crashes/blanks; eager-loaded to avoid N+1; external names remain searchable.
- **ViewRapor recap (§4.8):** academic recap now excludes `extracurricular` (previously only `kokurikuler`), mirroring `RaporExportService`.
- **Assessment TP field (bug):** the TP `CheckboxList` filter was over-restrictive — imported TPs with `NULL` grade_level were dropped, leaving an empty (seemingly missing) required field. Filter is now grade_level-null-tolerant, extracted to a testable method, with an always-visible helper hint.
- **Salin TP (§4.9):** new `LearningObjectiveCopyService` + list-page action; idempotent, role-scoped bulk copy of TP across periods.
