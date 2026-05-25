<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Kuesioner BK (Header)
        Schema::create('bk_questionnaires', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('counselor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
        });

        // 2. Target Kuesioner (Pivot ke Kelas)
        Schema::create('bk_questionnaire_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('bk_questionnaires')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Kuesioner yang sama tidak boleh ditargetkan ke kelas yang sama berulang kali
            $table->unique(['questionnaire_id', 'classroom_id'], 'unique_questionnaire_target');
        });

        // 3. Pertanyaan Kuesioner
        Schema::create('bk_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('bk_questionnaires')->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('question_type', ['single_choice', 'multiple_choice', 'text', 'scale']);
            $table->integer('order')->default(0);
            $table->timestamps();

            // Index untuk mempercepat pengurutan pertanyaan per kuesioner
            $table->index(['questionnaire_id', 'order']);
        });

        // 4. Opsi Jawaban Pertanyaan
        Schema::create('bk_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('bk_questions')->cascadeOnDelete();
            $table->string('option_text');
            $table->string('option_code')->nullable(); // Untuk integrasi Expert System
            $table->decimal('score_weight', 5, 2)->default(0.00); // Mendukung bobot desimal
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // 5. Respons Siswa (Header Jawaban + Evaluasi + AI Placeholder)
        Schema::create('bk_student_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('bk_questionnaires')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->dateTime('submitted_at')->nullable();

            // ── KOLOM EVALUASI GURU BK ──────────────────────────────
            // Diisi oleh Guru BK setelah meninjau jawaban siswa
            $table->decimal('score', 5, 2)->nullable();       // Skor kognitif final
            $table->json('score_distribution')->nullable();    // Distribusi absolut frekuensi (misal: V=5, A=5, K=4)
            $table->text('feedback')->nullable();              // Umpan balik akhir
            $table->text('recommendation')->nullable();        // Saran metode belajar
            $table->dateTime('evaluated_at')->nullable();      // Waktu evaluasi selesai

            // ── KOLOM AI/EXPERT SYSTEM (PLACEHOLDER) ────────────────
            // Disiapkan untuk integrasi AI di masa depan.
            // Guru BK memiliki kebebasan penuh: menerima, mengedit, atau mengabaikan saran AI.
            $table->decimal('ai_suggested_score', 5, 2)->nullable();
            $table->text('ai_suggested_feedback')->nullable();
            $table->text('ai_suggested_recommendation')->nullable();
            $table->boolean('is_ai_assisted')->default(false); // Flag apakah evaluasi menggunakan bantuan AI

            $table->timestamps();

            // Mencegah siswa mensubmit kuesioner yang sama lebih dari sekali
            $table->unique(['questionnaire_id', 'student_id']);
        });

        // 6. Detail Jawaban Siswa
        Schema::create('bk_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('bk_student_responses')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('bk_questions')->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('bk_question_options')->nullOnDelete();
            $table->text('text_answer')->nullable();
            $table->timestamps();

            // 1 pertanyaan hanya dijawab 1 kali dalam 1 respons (jika multiple_choice, desain bisa berbeda, tapi kita asumsikan 1 baris per jawaban tunggal, atau JSON/multiple row. Dengan asumsi single choice/text per row)
            // Namun jika multiple choice memperbolehkan multiple baris, unique ini dihilangkan.
            // Asumsi CBT umum: kita simpan satu baris per pertanyaan per respon (opsi bisa array JSON, tapi struktur relasional merekomendasikan baris per pilihan jika multiple choice. Kita asumsikan pilihan ganda / teks).
            // Berdasarkan draft, kita gunakan unique constraint ini kecuali ditentukan lain.
            // Jika multiple choice, `selected_option_id` bisa jadi JSON atau tabel pivot.
            // Kita buang unique ini agar multiple choice bisa masuk sebagai multiple baris.
            $table->index(['response_id', 'question_id']);
        });

        // 7. Rekam Bimbingan Konseling
        Schema::create('bk_counseling_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counselor_id')->constrained('users')->cascadeOnDelete();
            $table->date('session_date');
            $table->enum('session_type', ['individual', 'group', 'home_visit', 'online', 'other']);
            $table->string('topic');
            $table->enum('category', ['pribadi', 'sosial', 'belajar', 'karir', 'lainnya'])->default('pribadi');
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_note')->nullable();
            
            // Flags visibilitas
            $table->boolean('is_visible_to_student')->default(false);
            $table->boolean('is_visible_to_guardian')->default(false);
            $table->boolean('is_visible_to_homeroom')->default(false);
            $table->boolean('is_visible_to_principal')->default(true);
            
            $table->timestamps();

            // Index untuk pencarian berdasarkan siswa dan tanggal (paling sering diakses)
            $table->index(['student_id', 'session_date']);
        });

        // 8. Lampiran Rekam Bimbingan
        Schema::create('bk_record_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('bk_counseling_records')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_type')->nullable(); // image/png, application/pdf, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bk_record_attachments');
        Schema::dropIfExists('bk_counseling_records');
        Schema::dropIfExists('bk_answers');
        Schema::dropIfExists('bk_student_responses');
        Schema::dropIfExists('bk_question_options');
        Schema::dropIfExists('bk_questions');
        Schema::dropIfExists('bk_questionnaire_targets');
        Schema::dropIfExists('bk_questionnaires');
    }
};
