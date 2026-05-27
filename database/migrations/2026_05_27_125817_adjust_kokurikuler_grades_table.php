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
        Schema::table('kokurikuler_grades', function (Blueprint $table) {
            if (!Schema::hasColumn('kokurikuler_grades', 'academic_period_id')) {
                $table->foreignId('academic_period_id')->nullable()->after('student_id')->constrained('academic_periods')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('kokurikuler_grades', 'project_title')) {
                $table->string('project_title')->nullable()->after('academic_period_id');
            }
            if (Schema::hasColumn('kokurikuler_grades', 'description') && !Schema::hasColumn('kokurikuler_grades', 'narrative_description')) {
                $table->renameColumn('description', 'narrative_description');
            }

            // Create index for foreign key before dropping the unique constraint that it was using
            $table->index('teaching_assignment_id');
            $table->dropUnique('unique_kokurikuler_grade_per_student');
            $table->unsignedBigInteger('teaching_assignment_id')->nullable()->change();
            
            $table->unique(
                ['academic_period_id', 'student_id'],
                'unique_kokurikuler_period_student'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kokurikuler_grades', function (Blueprint $table) {
            $table->dropUnique('unique_kokurikuler_period_student');
            $table->unsignedBigInteger('teaching_assignment_id')->nullable(false)->change();
            $table->unique(
                ['teaching_assignment_id', 'student_id'],
                'unique_kokurikuler_grade_per_student'
            );
            $table->dropIndex(['teaching_assignment_id']);

            if (Schema::hasColumn('kokurikuler_grades', 'narrative_description')) {
                $table->renameColumn('narrative_description', 'description');
            }
            $table->dropForeign(['academic_period_id']);
            $table->dropColumn(['academic_period_id', 'project_title']);
        });
    }
};
