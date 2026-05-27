<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. WALI KELAS (Homerooms)
        Schema::create('class_homerooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        // 2. ANGGOTA KELAS (Enrollments)
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'promoted', 'retained', 'graduated', 'dropped'])->default('active');
            $table->foreignId('promoted_from_enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->timestamps();

            // Aturan: 1 Siswa hanya boleh ada di 1 kelas pada tahun ajaran yang sama
            $table->unique(['student_id', 'academic_period_id']);
        });

        // 3. EKSTRAKURIKULER (Master)
        Schema::create('extracurriculars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('coach_name')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 4. ANGGOTA EKSKUL (Pivot)
        Schema::create('student_extracurriculars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extracurricular_id')->constrained()->cascadeOnDelete();

            // Nilai Ekskul di Rapor
            $table->string('score_grade')->nullable(); // A, B, C
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 5. PROJEK P5 (Master Projek per Kelas)
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete(); // Projek ini untuk kelas mana

            $table->string('theme'); // Gaya Hidup Berkelanjutan
            $table->string('name'); // Sampahku Tanggung Jawabku
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 6. NILAI PROJEK P5 (Rapor P5)
        Schema::create('project_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            $table->string('dimension'); // Beriman, Mandiri...
            $table->string('element');   // Sub-elemen
            $table->enum('score', ['BB', 'MB', 'BSH', 'SB']); // Predikat
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_grades');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('student_extracurriculars');
        Schema::dropIfExists('extracurriculars');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('class_homerooms');
    }
};