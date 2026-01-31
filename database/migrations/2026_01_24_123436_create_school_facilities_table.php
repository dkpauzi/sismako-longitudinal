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
        Schema::create('school_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // Contoh: "Laboratorium Komputer"
            $table->text('description')->nullable();
            $table->string('image_path')->nullable(); // Foto fasilitas
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_facilities');
    }
};
