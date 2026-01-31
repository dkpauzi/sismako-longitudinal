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
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name');
            $table->string('npsn')->nullable(); // Nomor Pokok Sekolah Nasional
            $table->text('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Branding
            $table->string('logo_path')->nullable();
            $table->string('kop_surat_path')->nullable();

            // Penanda Tangan Rapor
            $table->string('principal_name')->nullable();
            $table->string('principal_nip')->nullable();

            // Setting Akademik Default
            $table->integer('default_kkm')->default(75);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};
