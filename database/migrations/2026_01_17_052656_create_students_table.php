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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            // Relasi ke User Login (Bisa null jika siswa belum punya akun login)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Relasi ke akun Wali Siswa (Bisa null, 1 wali bisa terhubung ke banyak siswa/saudara)
            $table->foreignId('guardian_user_id')->nullable()->constrained('users')->nullOnDelete();

            // --- 1. IDENTITAS UTAMA ---
            $table->string('name');             // Kolom Excel: Nama
            $table->string('nipd')->nullable(); // Kolom Excel: NIPD (No Induk Sekolah)
            $table->string('nisn')->nullable()->unique(); // Kolom Excel: NISN (Wajib Unik)
            $table->string('nik')->nullable();  // Kolom Excel: NIK

            // --- 2. BIODATA PRIBADI ---
            $table->enum('gender', ['L', 'P']);           // Kolom Excel: JK
            $table->string('place_of_birth')->nullable(); // Kolom Excel: Tempat Lahir
            $table->date('date_of_birth')->nullable();    // Kolom Excel: Tanggal Lahir
            $table->string('religion')->nullable();       // Kolom Excel: Agama

            // --- 3. DATA ORANG TUA ---
            $table->string('father_name')->nullable(); // Kolom Excel: Ayah
            $table->string('mother_name')->nullable(); // Kolom Excel: IBU

            // --- 4. ALAMAT ---
            $table->text('address')->nullable(); // Kolom Excel: Alamat

            // --- 5. SYSTEM TRACKING ---
            $table->enum('status', [
                'active',       // Aktif
                'graduated',    // Lulus
                'moved',        // Pindah Sekolah
                'dropped_out',  // Putus Sekolah
                'deceased'      // Meninggal
            ])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};