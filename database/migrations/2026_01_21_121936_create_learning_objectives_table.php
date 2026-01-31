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
        Schema::create('learning_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();

            // Contoh: "Peserta didik mampu mengidentifikasi unsur-unsur lingkaran"
            $table->text('content');

            // Ringkasan untuk Rapor (Opsional, agar deskripsi tidak terlalu panjang)
            // Contoh: "mengidentifikasi unsur lingkaran"
            $table->string('attribute')->nullable();

            // Fase (A/B/C/D/E/F) - Sesuai panduan KM
            $table->enum('phase', ['A', 'B', 'C', 'D', 'E', 'F'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_objectives');
    }
};
