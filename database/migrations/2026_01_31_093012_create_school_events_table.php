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
        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Nama Kegiatan/Libur
            $table->dateTime('start'); // Mulai
            $table->dateTime('end'); // Selesai
            $table->boolean('is_all_day')->default(true); // Seharian penuh?
            $table->string('color')->nullable(); // Warna label (Merah/Biru/Hijau)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_events');
    }
};
