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

        // Catatan Arsitektur (Batasan Skripsi — SMP / Kurikulum Merdeka):
        // Tabel extracurriculars, student_extracurriculars, projects, dan project_grades
        // sengaja dihapus dari skema. Fungsinya sudah digantikan sepenuhnya oleh:
        //   - Nilai P5 / Kokurikuler  -> tabel kokurikuler_grades
        //   - Nilai Ekstrakurikuler   -> tabel student_subject_enrollments (kolom predicate & description)
        // Keduanya didefinisikan di migrasi create_kbm_and_assessment_tables.
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('class_homerooms');
    }
};