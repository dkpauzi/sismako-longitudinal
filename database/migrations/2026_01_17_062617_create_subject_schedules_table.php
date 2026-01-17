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
        Schema::create('subject_schedules', function (Blueprint $table) {
            $table->id();
            // Relasi Utama (Siapa, Dimana, Kapan Periode-nya)
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            // Detail Waktu (Penting untuk validasi bentrok jadwal nanti)
            $table->enum('day', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']);
            $table->time('start_time'); // Jam Mulai (misal: 07:00)
            $table->time('end_time');   // Jam Selesai (misal: 08:30)

            // Detail Tambahan (Opsional tapi sering terpakai)
            $table->string('room')->nullable(); // Misal: "Lab Komputer 1" (jika beda dengan kelas homeroom)
            $table->string('note')->nullable(); // Catatan: "Bawa baju olahraga"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_schedules');
    }
};
