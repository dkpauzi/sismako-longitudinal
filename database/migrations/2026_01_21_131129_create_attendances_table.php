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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();

            // Fleksibel: Bisa absen harian (null) atau absen per mapel (diisi)
            $table->foreignId('subject_id')->nullable()->constrained()->cascadeOnDelete();

            $table->date('date');
            $table->time('time_in')->nullable(); // Jam masuk (untuk hitung telat)

            // Status Lengkap
            $table->enum('status', ['present', 'sick', 'permission', 'alpha', 'late', 'dispensation'])->default('present');

            $table->string('note')->nullable(); // Keterangan: "Sakit Tifus"

            // Fitur Digital (Opsional masa depan)
            $table->string('proof_file')->nullable(); // Upload surat dokter
            $table->boolean('is_validated')->default(true); // Validasi guru piket
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
