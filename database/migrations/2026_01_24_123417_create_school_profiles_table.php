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
        Schema::create('school_profiles', function (Blueprint $table) {
            $table->id();
            // 1. Identitas Dasar (Tampil di Header/Footer)
            $table->string('name'); // Nama Sekolah
            $table->string('npsn')->nullable();
            $table->string('accreditation')->nullable(); // A, B, C
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // 2. Branding (Logo & Warna)
            $table->string('logo_path')->nullable(); // Logo Utama
            $table->string('favicon_path')->nullable(); // Ikon di tab browser
            $table->string('banner_image_path')->nullable(); // Foto besar di halaman depan
            $table->string('primary_color')->default('#0000FF'); // Admin bisa ganti warna tema web!

            // 3. Kepala Sekolah (Sambutan)
            $table->string('principal_name')->nullable();
            $table->string('principal_photo_path')->nullable();
            $table->text('welcome_message')->nullable(); // Kata sambutan
            $table->string('welcome_video_url')->nullable(); // Link Youtube Sambutan (opsional)

            // 4. Konten Profil (Sejarah & Visi)
            $table->longText('history')->nullable(); // Sejarah singkat (LongText biar muat banyak)
            $table->text('vision')->nullable(); // Visi biasanya cuma 1 paragraf

            // 5. Embed Sosmed & Peta
            $table->text('google_maps_embed')->nullable(); // Kode iframe Google Maps
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_profiles');
    }
};
