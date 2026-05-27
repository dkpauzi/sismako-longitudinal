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
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->string('predicate')->nullable()->after('note');
            $table->text('description')->nullable()->after('predicate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            $table->dropColumn(['predicate', 'description']);
        });
    }
};
