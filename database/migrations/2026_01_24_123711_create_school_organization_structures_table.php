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
        Schema::create('school_organization_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // Nama Pejabat (Misal: Drs. Budi)
            $table->string('position'); // Jabatan (Misal: "Waka Kurikulum")
            $table->string('photo_path')->nullable(); // Foto orangnya
            $table->integer('order')->default(1); // Untuk mengatur urutan (Kepsek paling atas)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_organization_structures');
    }
};
