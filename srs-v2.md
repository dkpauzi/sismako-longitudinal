# Software Requirements Specification (SRS)
## Student Academic Progress Monitoring Information System (SIAKAD-PPA)
**Case Study:** SMPN 45 Sijunjung
**Document Version:** 2.0.0 (Implementation Baseline)

---

## 1. Introduction

### 1.1 Purpose
This Software Requirements Specification (SRS) serves as an unambiguous architectural blueprint and precise functional specification for the development of the Student Academic Progress Monitoring Information System (SIAKAD-PPA) at SMPN 45 Sijunjung. This application is strictly positioned as a **Companion System (Pre-Compilation & Real-Time Monitoring Platform)**. It does not replace the official government e-Rapor (Dapodik) system. 

Instead, it acts as an internal academic workbench for teachers to autonomously process continuous grades, track longitudinal competencies across semesters, and provide real-time transparency to students and parents prior to final manual data entry into the national e-Rapor platform.

### 1.2 Scope & Infrastructure Constraints
The application must be developed using the **TALL Stack** (TailwindCSS, Alpine.js, Laravel 11, Livewire) and **Filament Panel Builder v3**. 
* **Target Environment:** Hostinger Shared Web Hosting (Business Plan).
* **Critical Technical Boundary:** Due to shared hosting limitations, persistent background daemons (e.g., `Supervisor` keeping `php artisan queue:work` running continuously) and dedicated Redis message brokers are **strictly forbidden**. All operations must execute synchronously within standard HTTP request-response cycles.
* **Scalability Target:** The database design must use a Multi-Tier architecture, allowing features designed for Upper Secondary School (SMA - Phase F) to be present but toggled off cleanly when deployed for Lower Secondary School (SMP) scope.

### 1.3 Technical Definitions & Abbreviations
* **Internal Progress Report:** An un-official, real-time verifiable digital dashboard and document showing current academic standing. Validation relies entirely on system-internal Authentication and Authorization Logs instead of external digital signatures.
* **Remedial Calibration:** A granular, teacher-approved transactional workflow to adjust a student's summative grade upward to meet the minimum competency threshold (KKTP), strictly replacing automated grade-boosting scripts.
* **Synchronous Chunking:** A data processing technique that slices large data arrays (e.g., Excel imports) into small sequential fragments (max 50 rows per iteration) executed within single, atomic HTTP requests to bypass PHP memory limits.
* **Hidden Grade System:** A server-side mechanism mapping absolute numeric scores (0-100) to internal alphabetical predicates (A-E) based on KKTP rules. These drive the narrative text engine but are strictly hidden from frontend views.
* **Rule-Based Scoring System (VAK):** A deterministic calculation module within the Guidance & Counseling feature that maps specific multiple-choice answers directly to Visual, Auditory, or Kinesthetic (VAK) learning styles without requiring probabilistic AI inference.

---

## 2. System Actors & Role-Based Access Control (RBAC)

Authorization must be enforced at the Model, Query, and UI level using Laravel Policies and Spatie Shield.

1. **Super Admin:** Absolute system access. Manages feature toggles, global application states, Spatie roles/permissions, and executes database seeding (including the VAK question bank).
2. **Admin (School Clerk):** Manages master data structures (Students, Teachers, Classrooms, Subjects). Executes mass imports via the Smart Importer and drives the academic year transition wizard.
3. **Headmaster (Kepala Sekolah):** High-level *read-only* access. Focused on aggregate analytical dashboards, school-wide performance statistics, and longitudinal student progress charts.
4. **Subject Teacher / Coach:** Full operational access to assigned classes. Configures custom summative weightings, defines Learning Objectives (TP), inputs scores, executes remedial calibrations, and inputs P5/Extracurricular qualitative grades.
5. **Guidance Counselor (Guru BK):** Exclusive access to behavioral tracking and the Non-Cognitive Diagnostic Assessment module. They **do not** have the authority to create cognitive questions. They can only distribute the pre-seeded VAK questionnaire to classrooms, view the auto-calculated learning style results, and record encrypted counseling session logs.
6. **Student:** Personal read-only access to individual timetables, active BK questionnaires (which auto-save to prevent data loss on disconnects), and real-time longitudinal grade tracking graphs.
7. **Parent (Wali Siswa):** Read-only portal tracking their specific child's continuous grades, attendance matrix, and draft internal progress report downloads.

---

## 3. Functional Requirements

### 3.1 Grading Engine & Narrative Rule Engine
* **Hidden Grade Conversion Logic:**
  - Converts numeric scores (0-100) into alphabetical predicates (A-E) based on classroom KKTP.
  - **Data Leakage Security Rule:** The alphabetical predicate string must be stripped from data objects passed to the student/parent views using Eloquent's `makeHidden(['grade_label'])`.
* **Automated Narrative Text Generator:**
  - Analyzes a student's row data for the active semester, isolating the highest and lowest scored Learning Objectives (TP).
  - Auto-compiles a fluent string utilizing structured conjunctions (e.g., "Shows excellent competency in [Highest TP], however, requires further guidance in [Lowest TP].").

### 3.2 Remedial Calibration Module
* **Strict Anti-Auto-Booster Constraint:** Automated backend scripts that inflate grades below the KKTP threshold without explicit teacher interaction are **strictly banned**.
* **Granular Manual Approval Workflow:**
  - System flags grades below the KKTP threshold. The teacher must manually review and explicitly click `Approve Remedial Calibration`.
  - Executes an atomic transaction updating the final score to the minimum passing threshold and writes a permanent record into the security audit log.

### 3.3 Guidance & Counseling (BK) Diagnostic Module
* **Immutable Knowledge Base (VAK Instrument):**
  - The system utilizes a standardized, hardcoded 14-question instrument adopted from the 2018 Ministry of Education (Kemdikbud) guidelines for Visual, Auditory, and Kinesthetic (VAK) learning styles.
  - **Constraint:** Teachers cannot create, edit, or delete questions. The question bank is injected strictly via Laravel Database Seeders.
* **Rule-Based Calculation Engine:**
  - The module maps each multiple-choice option to a specific `option_code` ('VISUAL', 'AUDITORI', 'KINESTETIK').
  - Upon submission, the controller tallies the scores using absolute summation and outputs the dominant learning style as a definitive diagnostic result.
* **Anti-State-Loss Architecture (Incremental Auto-Save):**
  - To prevent data loss due to poor network connectivity (typical for mobile users in remote areas), the questionnaire must be processed asynchronously per question. Every selected radio button instantly triggers a background save to the `bk_answers` table. Time limits must be enforced on the server (`ends_at`), not just via client-side javascript timers.

### 3.4 Longitudinal Analytics Dashboard
* **Enrollment Chain Tracking:**
  - The database tracks a student's multi-year academic journey using a recursive relation on the enrollment tracking table via the foreign key `promoted_from_enrollment_id`.
* **Client-Side Graphical Rendering Performance Rule:**
  - Drawing graphs **must not** happen on the PHP server side to prevent CPU overload.
  - The backend server executes a single optimized query returning a raw JSON array of historical scores. The client's web browser handles all rendering using a client-side JavaScript engine (Chart.js or ApexCharts).

### 3.5 Co-Curricular (P5) & Extracurricular Modules
* **Subject Type Isolation:**
  - The `subjects` table utilizes an `enum('mandatory', 'kokurikuler', 'elective')` column. P5 is registered as `kokurikuler`.
  - When a teacher accesses a `kokurikuler` assessment, numeric inputs are locked. They must use the standard national qualitative ordinal scale (BB, MB, BSH, SB).
* **Flat-Pivot Extracurricular Architecture:**
  - Extracurriculars are detached from the master `subjects` table and use an independent flat-pivot table (`extracurricular_student`) to manage cross-classroom enrollments, utilizing qualitative text grades (Sangat Baik, Baik, Cukup, Kurang).

### 3.6 Smart Importer & Transition Wizard
* **Synchronous Chunking Execution Rule:**
  - Mass spreadsheet processing splits the data payload into chunks of maximum **50 rows per batch**. Each batch executes synchronously within a single HTTP request cycle, bypassing `max_execution_time`.
* **Automated Termination:**
  - Executing a final-year graduation immediately triggers a soft-deactivation database flag, freezing login authentication capabilities for both the student and associated parent user accounts.

### 3.7 Audit Trail & Security Logging Module
* **Granular Grade Modification Capturing:**
  - Any direct mutation on the `grades` or `final_grades` tables instantly triggers an Eloquent Observer (`GradeObserver`) capturing the original state (`old_score`) and modified state (`new_score`) along with forensic context (User ID, IP Address, User Agent).

---

## 4. Non-Functional Requirements & Database Optimizations

### 4.1 Security & Transactional Integrity
* **Mandatory Database Transactions:** All mass modifications (Smart Importer, Remedial Calibration, Graduation Wizard, BK Diagnostic tallying) must be wrapped completely inside an explicit database transaction closure (`DB::transaction`).

### 4.2 High-Performance Database Querying Constraints
* **Mandatory Eager Loading (`with()`):** To eliminate the N+1 Query Problem, all database queries for complex pages (like the Internal Progress Report) must declare an explicit eager load profile.
* **Memory-Optimized Collection Processing:** The system is **strictly prohibited** from running database queries inside a `foreach` loop when compiling classroom-wide reports. The controller must fetch all metadata via a single query into a Laravel PHP Collection and handle filtering in-memory.
