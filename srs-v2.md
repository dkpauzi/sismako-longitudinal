# SYSTEM_SPECIFICATION: SIAKAD-PPA (SMPN 45 Sijunjung)
**Version:** 2.1.0
**Architecture:** TALL Stack (TailwindCSS, Alpine.js, Laravel 11, Livewire) + Filament Panel Builder v3.
**Type:** Companion System (Pre-Compilation & Real-Time Monitoring). Not a replacement for the national e-Rapor.

---

## 1. ENVIRONMENT & INFRASTRUCTURE CONSTRAINTS
- **Target:** Hostinger Shared Web Hosting (Business Plan).
- **[FORBIDDEN] Background Daemons:** `Supervisor` and dedicated Redis message brokers are strictly prohibited due to shared hosting limits.
- **[MUST] Queue Configuration:** `QUEUE_CONNECTION=sync` MUST be enforced. All async tasks (e.g., chunked Excel imports) MUST execute synchronously within the standard HTTP request-response cycle.
- **[MUST] Scalability:** Multi-Tier architecture required. SMA (Phase F) features must exist in the database schema but be cleanly toggleable/hidden for SMP deployments.

---

## 2. CORE SYSTEM DEFINITIONS & TERMINOLOGY
- **Internal Progress Report:** Digital dashboard. Validation uses internal Auth Logs, not external digital signatures.
- **Remedial Calibration:** A manual, transactional workflow to adjust summative grades to meet KKTP. Replaces auto-boosting scripts.
- **Synchronous Chunking:** Data arrays are processed in chunks of MAX 50 rows per atomic HTTP request.
- **Hidden Grade System:** Maps numeric scores (0-100) to predicates (A-E) via KKTP rules on the server side.
- **VAK System:** Deterministic, non-AI rule-based scoring module mapping answers to Visual, Auditory, or Kinesthetic styles.

---

## 3. ROLE-BASED ACCESS CONTROL (RBAC)
*Authorization enforced via Laravel Policies and Spatie Shield.*

### 3.1 Role Definitions
- `super_admin`: Absolute access. Manages toggles, Spatie roles, and executes DB seeders (including VAK).
- `admin`: School Clerk. Manages master data (Students, Teachers, Classrooms, Subjects), executes Smart Importer, manages academic year transitions. Handles manual role escalations.
- `headmaster`: Read-only executive access. Views aggregate dashboards, school-wide stats, and longitudinal charts.
- `teacher`: Operational access to assigned classes. Sets KKTP/weights, defines Learning Objectives (TP), inputs scores, approves remedials, inputs P5/Extracurricular text grades.
- `guru_bk`: Guidance Counselor. Exclusive access to behavioral tracking and VAK module. Distributes VAK, views results, logs encrypted sessions. [FORBIDDEN] Cannot create cognitive questions.
- `student`: Read-only personal access. Views timetable, takes active VAK questionnaires, views longitudinal charts.
- `parent`: Read-only portal tracking their child's grades, attendance, and progress reports.

### 3.2 Account Provisioning Workflow (SOP)
- **Rule 1 (Default State):** Excel mass imports MUST assign ONLY the `teacher` role to all imported educator accounts.
- **Rule 2 (Escalation):** `headmaster` and `guru_bk` roles MUST NOT be auto-assigned. Admin assigns them manually post-import via the Spatie Role management UI.
- **Rule 3 (Zero-State UI Mitigation):** Dashboards for `headmaster` and `guru_bk` MUST use null-safe operators (`?->`) or empty checks to prevent crashes if the user lacks active `teaching_assignments`.

---

## 4. FUNCTIONAL MODULES

### 4.1 Grading & Narrative Engine
- **Logic:** Convert 0-100 to A-E based on KKTP threshold.
- **Security:** Use `$hidden = ['grade_label']` on Eloquent models sent to frontend (Student/Parent views) to prevent data leakage.
- **Generator:** Auto-compiles narratives analyzing highest/lowest TP scores (e.g., "Excellent in [TP_HIGH], needs guidance in [TP_LOW]").

### 4.2 Remedial Calibration Module
- **[FORBIDDEN]:** Automated scripts inflating grades below KKTP without explicit teacher interaction.
- **Workflow:** 1. System flags < KKTP scores.
  2. Teacher explicitly clicks "Approve".
  3. `DB::transaction` updates final score to minimum KKTP.
  4. Write to Security Audit Log.
- **Empirical Tracking Data Model:** Upon approval, store the pure original score in `original_score`, update `final_score`, and increment `remedial_attempts` in the `grades` table.

### 4.3 Guidance & Counseling (VAK)
- **Data Source:** Hardcoded 14-question Kemdikbud 2018 instrument via DB Seeders. Teachers CANNOT mutate questions.
- **Logic:** Each option maps to `option_code` ('VISUAL', 'AUDITORI', 'KINESTETIK'). Absolute summation determines the `dominant_style`.
- **State Machine:** `pending` -> `completed` | `revoked`. Access locks immediately on `completed` or `revoked`.
- **Auto-Save Architecture:** Every radio button click MUST asynchronously save to `bk_student_responses` to prevent data loss due to unstable network connections.

### 4.4 Longitudinal Analytics
- **Data Model:** Track multi-year journey via recursive relation `promoted_from_enrollment_id`.
- **Rendering Constraint:** [MUST] Render graphs entirely on the client-side (Chart.js/ApexCharts) using raw JSON payloads from a single optimized backend query. PHP-side chart rendering is prohibited.

### 4.5 P5 & Extracurricular
- **P5 (Kokurikuler):** - **[FORBIDDEN]:** Processing raw rubric parameters (BB, MB, BSH, SB).
  - **Data Input:** Direct input of `project_title` and `narrative_description` into the `kokurikuler_grades` table.
  - **Cardinality Constraint:** 1-to-Many. Students can have 2-3 projects per semester. [FORBIDDEN] Do not apply a unique constraint on `student_id` + `academic_period_id`.
- **Extracurricular:** - Pivot table `student_subject_enrollments` linked to `teaching_assignment`.
  - Uses a flexible string `predicate` (e.g., Sangat Baik, Baik) and text `description`. [FORBIDDEN] Do not use strict database-level enums to prevent nomenclature lock-in.

### 4.6 Smart Importer & Utilities
- **Chunking execution:** Process mass arrays synchronously in max 50-row chunks bypassing `max_execution_time`.
- **Graduation Wizard:** Final-year graduation MUST trigger a soft-deactivation database flag, freezing login authentication capabilities for both the student and associated parent user accounts.

---

## 5. DATABASE OPTIMIZATION & SECURITY
- **Audit Trail:** Any direct mutation on `grades` or `final_grades` tables MUST trigger an Eloquent Observer (`GradeObserver`) logging `old_score`, `new_score`, `user_id`, `ip_address`, and `user_agent`.
- **Transactions:** Mass modifications (Smart Importer, Remedial Calibration, Graduation Wizard, BK Diagnostic tallying) MUST be wrapped completely inside an explicit `DB::transaction()` closure.
- **Eager Loading:** [MUST] Use explicit `with()` arrays to eliminate the N+1 Query Problem on complex pages.
- **[FORBIDDEN] Loop Queries:** Querying the database inside a `foreach` loop is strictly banned for classroom-wide reporting. The controller MUST fetch all metadata via a single query into a Laravel PHP Collection and handle all aggregations/filtering in-memory.