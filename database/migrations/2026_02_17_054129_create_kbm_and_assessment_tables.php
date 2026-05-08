<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. SK MENGAJAR (Teaching Assignments)
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();

            // Config Penilaian Dasar
            $table->string('grading_formula')->default('average');
            $table->integer('kktp')->default(75)->nullable();

            // --- FITUR BARU: PENDONGKRAK NILAI FORMATIF ---
            // Apakah nilai formatif akan diikutsertakan ke perhitungan akhir sumatif?
            $table->boolean('use_formative_boost')->default(false);
            // Jika iya, berapa persen bobot formatif yang diambil? (Misal: 20%)
            $table->integer('formative_boost_percentage')->default(0)->nullable();
            // Override type dari subjects. NULL = ikuti type di subjects.
            $table->enum('subject_type', ['mandatory', 'kokurikuler', 'elective', 'extracurricular'])
                ->nullable();
            $table->timestamps();
        });

        // 2. JADWAL PELAJARAN (Schedules)
        Schema::create('subject_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->enum('day', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // 3. JURNAL KBM — [TAMBAH] Dibuat sebelum attendances karena attendances FK ke sini
        Schema::create('lesson_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')
                ->constrained('teaching_assignments')
                ->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('meeting_number'); // Pertemuan ke-1, ke-2, dst.
            $table->string('topic');                       // Materi hari ini
            $table->text('notes')->nullable();             // Catatan/refleksi guru
            $table->enum('status', ['draft', 'done', 'locked'])->default('draft');
            $table->timestamps();

            $table->unique(
                ['teaching_assignment_id', 'date'],
                'unique_journal_per_date'
            );
        });



        // 4. JURNAL ABSENSI — [EDIT] Tambah kolom lesson_journal_id
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')
                ->constrained('teaching_assignments')
                ->cascadeOnDelete();

            // [TAMBAH] Link opsional ke jurnal KBM. Nullable agar absensi tetap valid
            // jika guru mengisi absensi tanpa membuat jurnal terlebih dahulu.
            $table->foreignId('lesson_journal_id')
                ->nullable()
                ->constrained('lesson_journals')
                ->nullOnDelete();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'permit', 'sick', 'alpha', 'holiday'])
                ->default('present');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(
                ['teaching_assignment_id', 'student_id', 'date'],
                'unique_attendance_per_student_per_date'
            );
        });


        // 5. TUJUAN PEMBELAJARAN (TP)
        Schema::create('learning_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->integer('grade_level')->nullable();
            $table->enum('phase', ['A', 'B', 'C', 'D', 'E', 'F'])->default('D');
            $table->text('content');
            $table->string('code')->nullable();
            $table->string('attribute');
            $table->timestamps();
        });

        // 6. HEADER UJIAN (Assessments)
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('technique');
            $table->date('date');
            // Bobot per Asesmen (Sangat berguna untuk Latihan/PR yang bobotnya diatur guru)
            $table->integer('weight')->default(1);
            $table->timestamps();
        });

        // 7. PIVOT: ASESMEN <-> TP
        Schema::create('assessment_learning_objective', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('learning_objective_id')->constrained('learning_objectives')->cascadeOnDelete();
        });

        // 8. NILAI SISWA (Grades)
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->integer('score')->nullable();
            $table->text('feedback')->nullable(); // Sangat berguna untuk deskripsi opsional Formatif
            $table->timestamps();

            $table->unique(
                ['assessment_id', 'student_id'],
                'unique_grade_per_assessment_student'
            );
        });

        // 9. NILAI KOKURIKULER (P5)
        Schema::create('kokurikuler_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(
                ['teaching_assignment_id', 'student_id'],
                'unique_kokurikuler_grade_per_student'
            );
        });

        // 10. REKAP ABSENSI — [TAMBAH] Snapshot H/I/S/A per semester, diperbarui oleh Observer
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')
                ->constrained('teaching_assignments')
                ->cascadeOnDelete();
            $table->enum('semester', ['odd', 'even']);
            $table->unsignedSmallInteger('present')->default(0);
            $table->unsignedSmallInteger('permit')->default(0);
            $table->unsignedSmallInteger('sick')->default(0);
            $table->unsignedSmallInteger('alpha')->default(0);
            $table->decimal('attendance_percentage', 5, 2)->default(0.00);
            $table->timestamps();

            $table->unique(
                ['student_id', 'teaching_assignment_id', 'semester'],
                'unique_attendance_summary'
            );
        });

        // 11. NILAI AKHIR RAPOR — [TAMBAH] Snapshot calculateFinalGrade() untuk rapor
        Schema::create('final_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')
                ->constrained('teaching_assignments')
                ->cascadeOnDelete();
            $table->enum('semester', ['odd', 'even']);
            $table->decimal('final_score', 5, 2)->nullable();
            $table->enum('grade_label', ['A', 'B', 'C', 'D'])->nullable();
            $table->text('narrative_description')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'teaching_assignment_id', 'semester'],
                'unique_final_grade'
            );
        });
        // MAPEL PILIHAN & EKSKUL — Pivot siswa ↔ teaching_assignment
        // Hanya diisi untuk type 'elective' dan 'extracurricular'
        Schema::create('student_subject_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('teaching_assignment_id')
                ->constrained('teaching_assignments')
                ->cascadeOnDelete();

            $table->string('note')->nullable(); // Cth: "Peminatan IPA", "Lintas Minat"

            $table->timestamps();

            $table->unique(
                ['student_id', 'teaching_assignment_id'],
                'unique_student_subject_enrollment'
            );
        });


    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_enrollments');
        Schema::dropIfExists('final_grades');
        Schema::dropIfExists('attendance_summaries');
        Schema::dropIfExists('kokurikuler_grades');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('assessment_learning_objective');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('learning_objectives');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('lesson_journals');
        Schema::dropIfExists('subject_schedules');
        Schema::dropIfExists('teaching_assignments');
    }
};