<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            
            // Input Wali Kelas
            $table->text('homeroom_notes')->nullable(); // Catatan Wali Kelas
            
            // Override/Cache Absensi Rapor
            $table->integer('sick_days')->default(0);
            $table->integer('excused_days')->default(0);
            $table->integer('unexcused_days')->default(0);
            
            // Status Dokumen
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            // Constraint: 1 Rapor per siswa per semester
            $table->unique(['student_id', 'academic_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_reports');
    }
};
