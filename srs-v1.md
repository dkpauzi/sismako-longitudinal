# Software Requirements Specification (SRS)
## Integrated Academic Information System (SIAKAD)
**Case Study:** SMPN 45 Sijunjung
**Version:** 1.0.0 (Initial AI-Ready Specification)

---

## 1. Introduction

### 1.1 Purpose
This Software Requirements Specification (SRS) defines the software requirements for the Integrated Academic Information System (SIAKAD) at SMPN 45 Sijunjung. The system is designed to digitize core academic processes, ranging from master data management (students, teachers, classrooms) and Guidance Counseling (BK) management, to a Kurikulum Merdeka-based grading system (including automated narrative descriptions), and large-scale academic transition management.

### 1.2 Scope
The system is built using the **TALL Stack** (TailwindCSS, Alpine.js, Laravel 11, Livewire) and Filament Panel v3. The application scope includes:
1. Academic data infrastructure management (Academic Years, Classrooms, Subjects).
2. Intelligent grading system featuring a weighting engine and automated narrative generator (*Narrative Rule Engine*).
3. Guidance and Counseling (BK) module integrated with cognitive test instruments.
4. Smart Importer for integrated student data migration and synchronization.
5. Student circulation management (Class Promotion and Graduation) based on enrollment history.

---

## 2. System Actors & Role-Based Access Control (RBAC)
The system implements a granular, role-based authorization architecture with 7 (seven) main actors:

1. **Super Admin:** Has absolute prerogative rights over all modules, global system configuration, architecture modification (feature toggles), and access control management (via Spatie Shield).
2. **Admin (School Clerk):** Responsible for managing master data (Students, Teachers, Schedules), executing mass data imports via Excel, and executing academic transition operations such as class promotion and graduation.
3. **Headmaster (Kepala Sekolah):** Holds high-level read-only access focused on comprehensive analytical dashboards, school-wide aggregate grade recaps, and holistic teacher performance evaluations.
4. **Subject Teacher:** Authorized to record daily attendance, input summative and formative grades, formulate Learning Objectives (Tujuan Pembelajaran / TP), and customize specific narrative report card templates for their subjects.
5. **Guidance Counselor (Guru BK):** Manages Computer-Based Test (CBT) cognitive assessment instruments, documents confidential student counseling records, and monitors student vulnerability matrices with exclusive access rights unavailable to regular teachers.
6. **Student:** Has the right to access personal class schedules, participate in BK assessment questionnaires, and review personal grade transcripts/report cards in real-time.
7. **Parent (Wali Siswa):** Acts as an academic supervisor who can monitor their child's grade progression, attendance rates, and disciplinary reviews (special notes) provided by the relevant Teacher or BK Counselor.

---

## 3. Core Functional Requirements

### 3.1 Integrated Grading Module & Narrative Rule Engine
* **Hidden Grade System:** The system is required to convert absolute numeric scores (0-100) into internal alphabetical predicates (A-E) dynamically tied to the specific Learning Objective Achievement Criteria (KKTP) of each class. This internal predicate serves as a parameter for the rule engine and must be hidden from the student interface.
* **Dynamic Narrative Generator:** The system must feature an algorithmic engine that processes the highest and lowest Learning Objective (TP) achievements of each student, then generates an automated report card description using appropriate language conjunctions (e.g., "excellent in [X], but requires guidance in [Y]").
* **Fallback Hierarchy:** The system must apply a narrative template layout resolution with the following priority hierarchy: `Teacher Custom Template` > `Admin Default Template` > `Hardcoded System Template`.

### 3.2 Guidance and Counseling (BK) Module
* **CBT-Based Questionnaire Instrument:** This feature allows the BK Teacher to digitally distribute structured psychological/cognitive assessments to targeted students.
* **Counseling Track Record (Shadow Columns):** The system must provide encrypted/shadow note entities specifically designed to store sensitive student counseling narratives. This column is prepared for future Artificial Intelligence (AI) extraction and analysis, with strict authorization restrictions limiting access solely to the BK Teacher.

### 3.3 Smart Importer Module
* **Background Execution (Job Batching):** The operation of uploading hundreds of student entity data rows via Excel spreadsheets must be executed asynchronously using a centralized queue system (**Redis/Database Queue**). This is intended to prevent the user interface thread from experiencing timeout conditions.
* **Auto-Account Generation:** Each validated student data row will trigger an atomic transactional procedure that creates:
  1. Student Profile Entity.
  2. Student User Credentials (username: registered NISN).
  3. Parent User Credentials (username: standard format `WALI_{NISN}`).
  4. Class registration relation mapping (Enrollment Pivot).

### 3.4 Academic Transition Module (Promotion System)
* **Mass Promotion Wizard:** The system must provide a sequential step-by-step wizard interface for the Admin to process the annual academic tier transition of an entire classroom cohort intact.
* **Historical Track Record Chain (Enrollment Chain Tracking):** The system must maintain referential relations from academic year to academic year using the foreign key `promoted_from_enrollment_id`. This is essential to preserve the integrity of historical grade data from previous academic periods.
* **Automated Graduation Termination:** Students confirmed to have reached graduation status must automatically have their status reclassified and their educational record terminated (soft-deactivated), which also includes freezing the authentication of the student's account and the related parent's account.

---

## 4. Non-Functional Requirements

### 4.1 Security & Operational Data Integrity
* **Database Transactions:** All mutations manipulating cross-table entities (e.g., Smart Importer execution, Class Promotion transitions) must be wrapped in pure Database Transactions (`DB::transaction`). This rule is absolute to guarantee ACID (Atomicity, Consistency, Isolation, Durability) properties and eliminate fragmented data in the event of a disconnected system failure.
* **Credential Encryption:** Automatically generated user passwords must be instantly one-way encrypted using the adaptive Bcrypt hashing algorithm (via the `Hash::make` facade), complying with modern authentication security standards.

### 4.2 Performance Optimization & Database Querying
* **Mandatory Eager Loading:** All query constructions on tables rooted in complex relations (such as the Report Card module containing the hierarchy `Student -> Grade Records -> Assessment Metadata`) are standardized to use Eager Loading (`with()`). This is crucial to proactively reduce the escalation of the N+1 Query Problem on the main dashboard.

### 4.3 Open Architecture Scalability
* **Feature Toggle Architecture (Design Preparation):** The database schema for upper-tier specific operational features (such as Elective Subjects specifically for SMA Phase F) is engineered modularly. This is intended so that the application ecosystem can be configured with a centralized switch (toggle), enabling complex features to be neatly hidden/disabled when the system is deployed purely for the SMP (Junior High School) infrastructure.