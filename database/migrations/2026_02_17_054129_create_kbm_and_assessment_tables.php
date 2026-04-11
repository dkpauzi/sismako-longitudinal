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

        // 3. JURNAL ABSENSI (Attendances)
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'permit', 'sick', 'alpha', 'holiday'])->default('present');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // 4. TUJUAN PEMBELAJARAN (TP)
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

        // 5. HEADER UJIAN (Assessments)
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

        // 6. PIVOT: ASESMEN <-> TP
        Schema::create('assessment_learning_objective', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('learning_objective_id')->constrained('learning_objectives')->cascadeOnDelete();
        });

        // 7. NILAI SISWA (Grades)
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->integer('score')->nullable();
            $table->text('feedback')->nullable(); // Sangat berguna untuk deskripsi opsional Formatif
            $table->timestamps();
        });

        // 8. NILAI KOKURIKULER (P5)
        Schema::create('kokurikuler_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 9. PENUGASAN WALI KELAS (Homeroom Assignments)
        Schema::create('homeroom_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['academic_period_id', 'classroom_id'], 'homeroom_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_assignments');
        Schema::dropIfExists('kokurikuler_grades');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('assessment_learning_objective');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('learning_objectives');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('subject_schedules');
        Schema::dropIfExists('teaching_assignments');
    }
};