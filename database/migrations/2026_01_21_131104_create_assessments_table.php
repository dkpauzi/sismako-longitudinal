<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            // Relasi ke TP (Penting untuk label deskripsi rapor)
            $table->foreignId('learning_objective_id')->nullable()->constrained()->cascadeOnDelete();

            // Kategori Penilaian
            $table->enum('category', ['formatif', 'sumatif_lingkup_materi', 'sumatif_akhir_semester']);

            // Teknik
            $table->enum('technique', [
                'observasi',
                'kinerja',
                'projek',
                'tes_tertulis',
                'tes_lisan',
                'penugasan',
                'portofolio'
            ]);

            $table->string('name');
            $table->date('date');

            // --- BATAS BAWAH (KKTP) ---
            // Ini kuncinya! Setiap ujian bisa punya standar beda-beda.
            // Misal: Materi Aljabar (sulit) batasnya 65, Materi Statistik (mudah) batasnya 75.
            $table->integer('passing_grade')->default(75); // Batas aman (KKTP)

            $table->integer('max_score')->default(100);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
