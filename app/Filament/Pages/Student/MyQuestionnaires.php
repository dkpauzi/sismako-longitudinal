<?php

namespace App\Filament\Pages\Student;

use App\Models\AcademicPeriod;
use App\Models\BkAnswer;
use App\Models\BkQuestion;
use App\Models\BkQuestionnaire;
use App\Models\BkStudentResponse;
use App\Models\Enrollment;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Halaman siswa untuk mengisi kuesioner Bimbingan Konseling (BK).
 *
 * Menampilkan daftar kuesioner yang ditargetkan ke kelas siswa pada
 * tahun ajaran aktif, serta menyediakan form modal untuk mengisi jawaban.
 */
class MyQuestionnaires extends Page implements HasForms
{
    use InteractsWithForms;

    // ── NAVIGASI ──────────────────────────────────────────────────
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Kuesioner BK';
    protected static ?string $title           = 'Kuesioner Bimbingan Konseling';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $navigationGroup = 'Bimbingan Konseling';

    protected static string $view = 'filament.pages.student.my-questionnaires';

    /**
     * Hanya siswa yang memiliki profil Student yang bisa akses halaman ini.
     */
    public static function canAccess(): bool
    {
        return Auth::check()
            && Auth::user()->hasRole('student')
            && Auth::user()->student !== null;
    }

    /**
     * Ambil daftar kuesioner yang ditargetkan ke kelas siswa saat ini.
     *
     * Eager-loading diterapkan pada: counselor, questions.options, targets.classroom
     * Menambahkan atribut dinamis `has_responded` pada setiap record.
     */
    public function getQuestionnairesForStudent(): Collection
    {
        $student = Auth::user()->student;

        if (! $student) {
            return new Collection();
        }

        // Ambil tahun ajaran aktif
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return new Collection();
        }

        // Cari enrollment aktif siswa di periode ini
        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('academic_period_id', $activePeriod->id)
            ->where('status', 'active')
            ->first();

        if (! $enrollment) {
            return new Collection();
        }

        $classroomId = $enrollment->classroom_id;

        // Ambil ID respon siswa yang sudah ada (untuk menandai yang sudah dikerjakan)
        $respondedIds = BkStudentResponse::where('student_id', $student->id)
            ->pluck('questionnaire_id')
            ->toArray();

        // Query kuesioner yang published, sesuai periode, dan ditargetkan ke kelas siswa
        // Eager-load relasi untuk menghindari N+1
        $questionnaires = BkQuestionnaire::with([
                'counselor:id,name',
                'questions.options',
                'targets.classroom',
            ])
            ->where('status', 'published')
            ->where('academic_period_id', $activePeriod->id)
            ->whereHas('targets', function ($query) use ($classroomId) {
                $query->where('classroom_id', $classroomId);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Tandai setiap kuesioner apakah siswa sudah merespons
        $questionnaires->each(function ($q) use ($respondedIds) {
            $q->has_responded = in_array($q->id, $respondedIds);
        });

        return $questionnaires;
    }

    /**
     * Tentukan status waktu kuesioner relatif terhadap waktu sekarang.
     *
     * @return string 'not_started' | 'closed' | 'open'
     */
    public function getTimeStatus(BkQuestionnaire $questionnaire): string
    {
        $now = now();

        // Belum dibuka
        if ($questionnaire->starts_at && $now->lt($questionnaire->starts_at)) {
            return 'not_started';
        }

        // Sudah ditutup
        if ($questionnaire->ends_at && $now->gt($questionnaire->ends_at)) {
            return 'closed';
        }

        // Terbuka (dalam jendela waktu, atau tidak ada batasan waktu)
        return 'open';
    }

    /**
     * Submit jawaban kuesioner siswa.
     *
     * Data disimpan dalam satu DB transaction untuk menjamin konsistensi:
     * 1. Buat BkStudentResponse (header)
     * 2. Loop setiap pertanyaan dan buat BkAnswer:
     *    - single_choice / scale: satu baris dengan selected_option_id
     *    - multiple_choice: satu baris per opsi yang dipilih (relasional, bukan JSON)
     *    - text: satu baris dengan text_answer
     *
     * @param int   $questionnaireId  ID kuesioner yang diisi
     * @param array $formData         Data jawaban dari form ['question_{id}' => value]
     */
    public function submitQuestionnaire(int $questionnaireId, array $formData): void
    {
        $student = Auth::user()->student;

        // Guard: cegah duplikat submission
        $existingResponse = BkStudentResponse::where('questionnaire_id', $questionnaireId)
            ->where('student_id', $student->id)
            ->exists();

        if ($existingResponse) {
            Notification::make()
                ->title('Kuesioner Sudah Dikerjakan')
                ->body('Kamu sudah mengisi kuesioner ini sebelumnya.')
                ->warning()
                ->send();
            return;
        }

        // Ambil pertanyaan untuk validasi tipe
        $questions = BkQuestion::where('questionnaire_id', $questionnaireId)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($questionnaireId, $student, $formData, $questions) {
            // 1. Buat header respons
            $response = BkStudentResponse::create([
                'questionnaire_id' => $questionnaireId,
                'student_id'       => $student->id,
                'submitted_at'     => now(),
            ]);

            // 2. Simpan jawaban per pertanyaan
            foreach ($questions as $question) {
                $key   = "question_{$question->id}";
                $value = $formData[$key] ?? null;

                switch ($question->question_type) {
                    case 'single_choice':
                    case 'scale':
                        // Satu baris, satu opsi terpilih
                        if ($value) {
                            BkAnswer::create([
                                'response_id'        => $response->id,
                                'question_id'        => $question->id,
                                'selected_option_id' => (int) $value,
                                'text_answer'        => null,
                            ]);
                        }
                        break;

                    case 'multiple_choice':
                        // Satu baris per opsi yang dipilih (relasional)
                        // $value berupa array ID opsi
                        if (is_array($value)) {
                            foreach ($value as $optionId) {
                                BkAnswer::create([
                                    'response_id'        => $response->id,
                                    'question_id'        => $question->id,
                                    'selected_option_id' => (int) $optionId,
                                    'text_answer'        => null,
                                ]);
                            }
                        }
                        break;

                    case 'text':
                        // Jawaban teks bebas (essay)
                        BkAnswer::create([
                            'response_id'        => $response->id,
                            'question_id'        => $question->id,
                            'selected_option_id' => null,
                            'text_answer'        => $value,
                        ]);
                        break;
                }
            }
        });

        Notification::make()
            ->title('Berhasil Disimpan')
            ->body('Jawaban kuesioner berhasil dikirim. Terima kasih!')
            ->success()
            ->send();
    }

    /**
     * Buat Filament Action untuk mengisi kuesioner via modal.
     * Dipanggil dari Blade view via `$this->mountAction('fillQuestionnaire', ['questionnaire_id' => $q->id])`.
     */
    public function fillQuestionnaireAction(): Action
    {
        return Action::make('fillQuestionnaire')
            ->label('Kerjakan Kuesioner')
            ->icon('heroicon-o-pencil-square')
            ->modalWidth('3xl')
            ->modalHeading(fn (array $arguments) => $this->getQuestionnaireTitle($arguments))
            ->modalDescription(fn (array $arguments) => $this->getQuestionnaireInstructions($arguments))
            ->modalSubmitActionLabel('Kirim Jawaban')
            ->modalCancelActionLabel('Batal')
            ->form(fn (array $arguments) => $this->buildQuestionnaireForm($arguments))
            ->action(function (array $data, array $arguments) {
                $this->submitQuestionnaire((int) $arguments['questionnaire_id'], $data);
            });
    }

    /**
     * Ambil judul kuesioner untuk header modal.
     */
    private function getQuestionnaireTitle(array $arguments): string
    {
        $q = BkQuestionnaire::find($arguments['questionnaire_id'] ?? 0);
        return $q?->title ?? 'Kuesioner';
    }

    /**
     * Ambil instruksi kuesioner untuk deskripsi modal.
     */
    private function getQuestionnaireInstructions(array $arguments): ?string
    {
        $q = BkQuestionnaire::find($arguments['questionnaire_id'] ?? 0);
        return $q?->instructions;
    }

    /**
     * Bangun form fields secara dinamis berdasarkan pertanyaan kuesioner.
     *
     * Tipe mapping:
     * - single_choice → Radio
     * - scale → Radio
     * - multiple_choice → CheckboxList
     * - text → Textarea
     *
     * @return array<Forms\Components\Component>
     */
    private function buildQuestionnaireForm(array $arguments): array
    {
        $questionnaireId = $arguments['questionnaire_id'] ?? 0;

        // Eager-load pertanyaan dan opsinya
        $questions = BkQuestion::with('options')
            ->where('questionnaire_id', $questionnaireId)
            ->orderBy('order')
            ->get();

        $fields = [];

        foreach ($questions as $index => $question) {
            $fieldName = "question_{$question->id}";
            $label     = ($index + 1) . '. ' . $question->question_text;

            switch ($question->question_type) {
                case 'single_choice':
                case 'scale':
                    $options = $question->options->pluck('option_text', 'id')->toArray();
                    $fields[] = Forms\Components\Radio::make($fieldName)
                        ->label($label)
                        ->options($options)
                        ->required()
                        ->columnSpanFull();
                    break;

                case 'multiple_choice':
                    $options = $question->options->pluck('option_text', 'id')->toArray();
                    $fields[] = Forms\Components\CheckboxList::make($fieldName)
                        ->label($label)
                        ->options($options)
                        ->required()
                        ->columnSpanFull();
                    break;

                case 'text':
                    $fields[] = Forms\Components\Textarea::make($fieldName)
                        ->label($label)
                        ->required()
                        ->rows(3)
                        ->columnSpanFull();
                    break;
            }
        }

        return $fields;
    }

    /**
     * Sediakan data ke Blade view.
     * Semua kuesioner yang relevan dimuat dengan eager loading.
     */
    protected function getViewData(): array
    {
        $questionnaires = $this->getQuestionnairesForStudent();

        return [
            'questionnaires' => $questionnaires,
            'page'           => $this,
        ];
    }
}
