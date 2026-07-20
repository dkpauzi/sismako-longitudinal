<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan PEMBINA EKSTRAKURIKULER EKSTERNAL (tanpa akun & tanpa NIP).
 *
 * Keputusan arsitektur: PERLUAS subsistem ekskul yang sudah ada
 * (Subject type=extracurricular → TeachingAssignment → StudentSubjectEnrollment)
 * alih-alih membangun model paralel — menjaga integrasi rapor, policy, dan
 * permission tetap tunggal. SRS §4.5 diperbarui pada commit yang sama.
 *
 * - teacher_id → NULLABLE (pembina eksternal tidak punya baris Teacher).
 * - FK diubah cascadeOnDelete → nullOnDelete: menghapus Guru tidak lagi ikut
 *   menghapus SK & nilainya (lebih aman utk data longitudinal; SRS §5).
 * - external_instructor_name: nama pembina luar untuk ditampilkan di rapor.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop FK dulu (terpisah) agar perubahan nullability kolom tidak bentrok.
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
        });

        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->change();
            $table->string('external_instructor_name')->nullable()->after('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('teachers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn('external_instructor_name');
        });

        Schema::table('teaching_assignments', function (Blueprint $table) {
            // Kembalikan ke NOT NULL + cascade seperti skema awal.
            $table->foreignId('teacher_id')->nullable(false)->change();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
        });
    }
};
