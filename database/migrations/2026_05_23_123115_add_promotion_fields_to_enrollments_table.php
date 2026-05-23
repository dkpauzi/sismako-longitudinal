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
        Schema::table('enrollments', function (Blueprint $table) {
            // Expand the status enum for promotion handling
            // Note: Since 'status' was originally created as a string, changing it in SQLite
            // using string() can be tricky without doctrine/dbal. Since it's just a string,
            // we don't actually need to change the column definition itself. We just add the new FK.
            
            // Add the recursive foreign key to track promotion chains
            $table->unsignedBigInteger('promoted_from_enrollment_id')->nullable()->after('status');
            $table->foreign('promoted_from_enrollment_id')
                  ->references('id')
                  ->on('enrollments')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['promoted_from_enrollment_id']);
            $table->dropColumn('promoted_from_enrollment_id');
        });
    }
};
