<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. PROFIL SEKOLAH — Single Source of Truth untuk identitas sekolah
        // Harus dibuat PERTAMA karena school_settings memiliki FK ke sini.
        Schema::create('school_profiles', function (Blueprint $table) {
            $table->id();
            // Identitas Dasar
            $table->string('name');
            $table->string('npsn')->nullable();
            $table->string('accreditation')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Branding (Warna & Aset)
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('banner_image_path')->nullable();
            $table->string('primary_color')->default('#0000FF');

            // Sambutan Kepsek
            $table->string('principal_name')->nullable();
            $table->string('principal_photo_path')->nullable();
            $table->text('welcome_message')->nullable();
            $table->string('welcome_video_url')->nullable();

            // Sejarah & Visi
            $table->longText('history')->nullable();
            $table->text('vision')->nullable();

            // --- PENGATURAN TOGGLE TAMPILAN LANDING PAGE ---
            $table->boolean('show_history')->default(true);
            $table->boolean('show_vision_mission')->default(true);
            $table->boolean('show_map')->default(true);

            // Sosmed & Embed
            $table->text('google_maps_embed')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->timestamps();
        });

        // 2. PENGATURAN SISTEM SEKOLAH (Konfigurasi operasional, BUKAN identitas)
        // Identitas sekolah diambil dari school_profiles via relasi.
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->nullable()->constrained()->nullOnDelete();

            // Branding & Tanda Tangan (khusus dokumen/rapor)
            $table->string('kop_surat_path')->nullable();
            $table->string('principal_nip')->nullable();

            // Default Akademik
            $table->integer('default_kkm')->default(75);
            $table->timestamps();
        });

        // 3. MISI SEKOLAH (Anak dari Profile)
        Schema::create('school_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->integer('order')->default(1);
            $table->timestamps();
        });

        // 4. FASILITAS SEKOLAH (Anak dari Profile)
        Schema::create('school_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        // 5. STRUKTUR ORGANISASI (Anak dari Profile)
        Schema::create('school_organization_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position');
            $table->string('photo_path')->nullable();
            $table->integer('order')->default(1);
            $table->timestamps();
        });

        // 6. KEGIATAN SEKOLAH (Anak dari Profile)
        Schema::create('school_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->date('date')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // 7. AGENDA / KALENDER EVENT
        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->boolean('is_all_day')->default(true);
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('school_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_profile_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('body');                          // Konten utama (rich text)
            $table->string('cover_image_path')->nullable(); // Foto utama (opsional)

            // Multi-gambar: disimpan sebagai JSON array path
            $table->json('gallery_images')->nullable();

            $table->string('category')->default('Umum');   // Cth: Prestasi, Pengumuman, Kegiatan
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();  // Bisa dijadwalkan

            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Hapus dengan urutan terbalik (Anak dulu, baru Induk)
        Schema::dropIfExists('school_posts');
        Schema::dropIfExists('school_events');
        Schema::dropIfExists('school_activities');
        Schema::dropIfExists('school_organization_structures');
        Schema::dropIfExists('school_facilities');
        Schema::dropIfExists('school_missions');
        Schema::dropIfExists('school_profiles');
        Schema::dropIfExists('school_settings');
    }
};