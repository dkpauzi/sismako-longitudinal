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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();

            // --- 1. RELASI AKUN ---
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // --- 2. IDENTITAS UTAMA ---
            $table->string('nip')->unique()->nullable(); // Dari kolom: NIP
            $table->string('name');                      // Dari kolom: NAMA
            $table->enum('gender', ['L', 'P'])->nullable(); // Dari kolom: L/P
            $table->string('place_of_birth')->nullable();   // Standard data diri
            $table->date('date_of_birth')->nullable();      // Dari kolom: TANGGAL LAHIR

            // --- 3. KONTAK (Standard) ---
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            // --- 4. PENDIDIKAN (Dari kolom PENDIDIKAN) ---
            $table->string('degree')->nullable();        // Dari: IJAZAH (S.Pd, S.Sos)
            $table->string('major')->nullable();         // Dari: JURUSAN (Pend. Biologi)
            $table->string('university')->nullable();    // Dari: Asal Sekolah/Kampus (STKIP, UNP)
            $table->year('graduation_year')->nullable(); // Dari: TAHUN LULUS

            // --- 5. DATA KEPEGAWAIAN (Dari kolom KEPEGAWAIAN) ---
            $table->string('employment_status')->nullable(); // Dari: KET (PNS, PPPK, Honorer)
            $table->string('position')->nullable();          // Dari: JABATAN (Kepala Sekolah, Guru Mapel)

            $table->string('grade')->nullable();             // Dari: GOL (IV/c, III/a)
            $table->string('rank')->nullable();              // Dari: PANGKAT (Pembina Utama Muda)

            $table->date('assignment_date')->nullable();     // Dari: TMT PANGKAT / MULAI DINAS

            // --- 6. STATUS SISTEM ---
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};