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
        Schema::create('school_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->constrained()->cascadeOnDelete();

            $table->string('title'); // Judul Kegiatan (Misal: "Pameran Karya P5")
            $table->text('description')->nullable(); // Deskripsi singkat
            $table->string('image_path'); // Foto dokumentasi
            $table->date('date')->nullable(); // Tanggal kegiatan dilaksanakan

            $table->boolean('is_published')->default(true); // Opsi sembunyikan jika perlu
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_activities');
    }
};
