<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ── TABEL TEMPLATE NARASI RAPOR ──────────────────────────
        // Menyimpan template kalimat deskripsi rapor per grade (A-E).
        // Placeholder [TP] akan diganti dengan nama Tujuan Pembelajaran saat generate.
        //
        // Hierarki fallback:
        //   1. Template guru (teaching_assignment_id = x, is_default = false)
        //   2. Template admin default (teaching_assignment_id = NULL, is_default = true)
        Schema::create('narrative_templates', function (Blueprint $table) {
            $table->id();
            $table->enum('grade_letter', ['A', 'B', 'C', 'D', 'E']);
            $table->text('template_text');  // Contoh: "Menunjukkan penguasaan yang sangat baik dalam [TP]"
            $table->boolean('is_default')->default(false);  // true = Admin school-wide default
            $table->foreignId('teaching_assignment_id')
                ->nullable()
                ->constrained('teaching_assignments')
                ->cascadeOnDelete();
            $table->timestamps();

            // Admin hanya boleh punya 1 default per grade
            $table->unique(
                ['grade_letter', 'is_default'],
                'unique_default_per_grade'
            );
        });

        // ── SEED 5 TEMPLATE DEFAULT ADMIN ────────────────────────
        $defaults = [
            'A' => 'Ananda menunjukkan penguasaan yang sangat baik dalam materi [TP].',
            'B' => 'Ananda sudah memahami dan mampu menerapkan materi [TP] dengan baik.',
            'C' => 'Ananda cukup memahami materi [TP], namun masih perlu pendalaman lebih lanjut.',
            'D' => 'Ananda perlu meningkatkan pemahaman pada materi [TP] agar mencapai ketuntasan.',
            'E' => 'Ananda memerlukan bimbingan intensif pada materi [TP] untuk mencapai kompetensi dasar.',
        ];

        $now = now();
        foreach ($defaults as $letter => $text) {
            DB::table('narrative_templates')->insert([
                'grade_letter' => $letter,
                'template_text' => $text,
                'is_default' => true,
                'teaching_assignment_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('narrative_templates');
    }
};
