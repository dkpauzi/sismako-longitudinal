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
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();

            // Status siswa DI KELAS INI (bukan status sekolah)
            // Contoh: 'promoted' (naik kelas), 'retained' (tinggal kelas)
            $table->enum('status', ['active', 'promoted', 'retained', 'transferred'])->default('active');

            $table->timestamps();

            // Mencegah duplikasi: Satu siswa tidak boleh ada di 2 kelas pada periode yang sama
            $table->unique(['academic_period_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
