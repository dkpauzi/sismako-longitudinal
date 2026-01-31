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
        Schema::create('extracurriculars', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Pramuka, Basket, Coding
            $table->string('coach_name')->nullable(); // Nama Pelatih Luar
            $table->string('description')->nullable();
            $table->timestamps();
        });
        // Tabel Pivot (Siswa ikut Ekskul)
        Schema::create('student_extracurriculars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extracurricular_id')->constrained()->cascadeOnDelete();

            // Nilai di Rapor
            $table->string('score_grade')->nullable(); // A, B, C
            $table->text('description')->nullable(); // "Sangat aktif dalam kegiatan..."

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extracurriculars');
    }
};
