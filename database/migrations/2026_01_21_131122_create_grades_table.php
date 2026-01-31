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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Nilai Angka (0-100)
            // Panduan 2025 mengizinkan penggunaan skala atau interval, 
            // tapi di database sebaiknya disimpan angka mentah (0-100) agar mudah dirata-rata.
            $table->decimal('score', 5, 2);

            // Feedback Kualitatif (Wajib untuk Formatif)
            $table->text('feedback')->nullable();

            $table->timestamps();
            $table->unique(['assessment_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
