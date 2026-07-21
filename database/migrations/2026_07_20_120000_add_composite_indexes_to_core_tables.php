<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks komposit untuk pola filter high-traffic (RelationManager, rapor, policy).
 *
 * Semua kolom FK sudah punya indeks tunggal (dibuat foreignId()->constrained()),
 * tetapi query nyata memfilter beberapa kolom sekaligus. Indeks komposit di bawah
 * mencegah full/partial scan pada pola-pola berikut.
 *
 * Aturan LEFT-MOST PREFIX (MySQL): indeks (a,b,c) sudah melayani (a) & (a,b),
 * jadi indeks (classroom_id, academic_period_id) TIDAK dibuat untuk enrollments —
 * sudah tercakup oleh (classroom_id, academic_period_id, status) [analis: IDX-2 vs IDX-6].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            // ViewRapor / RaporExportService / AssessmentsRM / ExtracurricularGrade:
            $table->index(['classroom_id', 'academic_period_id'], 'ta_classroom_period_idx');
            // allowedSubjectIdsFor() & daftar SK guru per periode:
            $table->index(['teacher_id', 'academic_period_id'], 'ta_teacher_period_idx');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            // Roster & withCount siswa aktif per kelas/periode (IDX-6). Ini juga
            // menutupi (classroom_id, academic_period_id) via left-most prefix.
            $table->index(['classroom_id', 'academic_period_id', 'status'], 'enr_class_period_status_idx');
        });

        Schema::table('class_homerooms', function (Blueprint $table) {
            // Policy wali kelas aktif (StudentSubjectEnrollment/Kokurikuler):
            $table->index(['classroom_id', 'academic_period_id', 'is_current'], 'ch_class_period_current_idx');
            // "Kelas yang saya ampu sebagai wali aktif" (Teacher):
            $table->index(['teacher_id', 'is_current'], 'ch_teacher_current_idx');
        });

        Schema::table('final_grades', function (Blueprint $table) {
            // Rekap per-SK (unique yang ada berawalan student_id, tak melayani ini):
            $table->index(['teaching_assignment_id', 'semester'], 'fg_ta_semester_idx');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table) {
            $table->dropIndex('ta_classroom_period_idx');
            $table->dropIndex('ta_teacher_period_idx');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enr_class_period_status_idx');
        });

        Schema::table('class_homerooms', function (Blueprint $table) {
            $table->dropIndex('ch_class_period_current_idx');
            $table->dropIndex('ch_teacher_current_idx');
        });

        Schema::table('final_grades', function (Blueprint $table) {
            $table->dropIndex('fg_ta_semester_idx');
        });
    }
};
