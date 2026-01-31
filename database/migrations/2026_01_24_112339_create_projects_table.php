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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete(); // Projek per kelas

            $table->string('theme'); // Contoh: "Gaya Hidup Berkelanjutan"
            $table->string('name'); // Contoh: "Sampahku Tanggung Jawabku"
            $table->text('description')->nullable();
            $table->timestamps();
        });
        // 2. Tabel Nilai Projek Siswa (Bukan angka, tapi dimensi & fase)
        Schema::create('project_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Dimensi Profil Pelajar Pancasila (Beriman, Mandiri, Gotong Royong, dll)
            $table->string('dimension');

            // Elemen / Sub-elemen yang dinilai
            $table->string('element');

            // Predikat P5:
            // BB = Belum Berkembang
            // MB = Mulai Berkembang
            // BSH = Berkembang Sesuai Harapan
            // SB = Sangat Berkembang
            $table->enum('score', ['BB', 'MB', 'BSH', 'SB']);

            $table->text('note')->nullable(); // Catatan Proses

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
