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
        Schema::create('school_missions', function (Blueprint $table) {
            $table->id();
            // Terhubung ke tabel profil (meskipun profil cuma 1, relasi ini menjaga konsistensi)
            $table->foreignId('school_profile_id')->constrained()->cascadeOnDelete();

            $table->text('content'); // Isi Misi
            $table->integer('order')->default(1); // Untuk mengatur urutan tampil (1, 2, 3)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_missions');
    }
};
