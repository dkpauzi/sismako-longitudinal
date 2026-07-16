<?php

namespace App\Filament\Pages\Student;

use App\Models\AcademicPeriod;
use App\Models\BkAnswer;
use App\Models\BkQuestion;
use App\Models\BkQuestionnaire;
use App\Models\BkStudentResponse;
use App\Models\Enrollment;
use App\Models\Student;
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
 * Halaman siswa/wali siswa untuk mengisi kuesioner BK dan melihat hasil evaluasi.
 *
 * Menampilkan daftar kuesioner yang ditargetkan ke kelas siswa pada
 * tahun ajaran aktif, serta menyediakan form modal untuk mengisi jawaban
 * dan modal read-only untuk melihat hasil evaluasi Guru BK.
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
     * Siswa yang memiliki profil Student ATAU wali siswa yang memiliki anak
     * bisa mengakses halaman ini.
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Siswa: harus punya profil Student
        if ($user->hasRole('student') && $user->student !== null) {
            return true;
        }

        // Wali Siswa: harus punya minimal 1 anak yang terdaftar
        if ($user->hasRole('guardian') && $user->guardianStudents()->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Ambil daftar Student yang bisa dilihat oleh user saat ini.
     * - Siswa: hanya dirinya sendiri
     * - Wali Siswa: semua anak yang ditautkan
     *
     * @return \Illuminate\Database\Eloquent\Collection<Student>
     */
    private function getAccessibleStudents(): Collection
    {
        $user = Auth::user();

        if ($user->hasRole('student') && $user->student) {
            return new Collection([$user->student]);
        }

        if ($user->hasRole('guardian')) {
            return $user->guardianStudents()->get();
        }

        return new Collection();
    }

    /**
     * Ambil daftar kuesioner yang memiliki tiket pending untuk siswa ini.
     *
     * Siswa HANYA melihat kuesioner yang Guru BK telah membuka aksesnya
     * melalui aksi 'Buka Akses Asesmen' (ticketing system).
     */
    public function getQuestionnairesForStudent(): Collection
    {
        $students = $this->getAccessibleStudents();

        if ($students->isEmpty()) {
            return new Collection();
        }

        $studentIds = $students->pluck('id')->toArray();

        // Ambil semua respons siswa (pending dan completed, BUKAN revoked)
        $responses = BkStudentResponse::whereIn('student_id', $studentIds)
            ->where('status', '!=', 'revoked')
            ->get()
            ->keyBy(function ($r) {
                return $r->questionnaire_id . '_' . $r->student_id;
            });

        // Ambil questionnaire_ids yang memiliki tiket untuk siswa ini
        $questionnaireIds = $responses->pluck('questionnaire_id')->unique()->toArray();

        if (empty($questionnaireIds)) {
            return new Collection();
        }

        // Query kuesioner yang published DAN memiliki tiket untuk siswa
        $questionnaires = BkQuestionnaire::with([
                'counselor:id,name',
                'questions.options',
                'targets.classroom',
            ])
            ->where('status', 'published')
            ->whereIn('id', $questionnaireIds)
            ->orderBy('created_at', 'desc')
            ->get();

        // Gunakan student_id pertama sebagai referensi utama
        $primaryStudentId = $students->first()->id;

        // Tandai setiap kuesioner dengan status respons
        $questionnaires->each(function ($q) use ($responses, $primaryStudentId) {
            $key = $q->id . '_' . $primaryStudentId;
            $response = $responses->get($key);

            // Siswa sudah mengerjakan jika status = completed
            $q->has_responded   = $response?->status === 'completed';
            $q->student_response = $response;
            $q->evaluated_at    = $response?->evaluated_at;
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
     * TICKETING SYSTEM: Siswa TIDAK membuat BkStudentResponse baru.
     * Sistem mencari tiket 'pending' yang sudah dibuat oleh Guru BK,
     * menyimpan jawaban ke dalamnya, lalu mengubah status ke 'completed'.
     *
     * @param int   $questionnaireId  ID kuesioner yang diisi
     * @param array $formData         Data jawaban dari form ['question_{id}' => value]
     */
    public function submitQuestionnaire(int $questionnaireId, array $formData): void
    {
        $student = Auth::user()->student;

        // Guard: hanya siswa (bukan wali) yang boleh submit
        if (! $student) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Hanya siswa yang dapat mengisi kuesioner.')
                ->danger()
                ->send();
            return;
        }

        // Guard: cari tiket pending — siswa HARUS memiliki tiket dari Guru BK
        $response = BkStudentResponse::where('questionnaire_id', $questionnaireId)
            ->where('student_id', $student->id)
            ->where('status', 'pending')
            ->first();

        if (! $response) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Kamu belum diberikan akses untuk mengerjakan kuesioner ini oleh Guru BK.')
                ->danger()
                ->send();
            return;
        }

        // Ambil pertanyaan untuk validasi tipe
        $questions = BkQuestion::where('questionnaire_id', $questionnaireId)
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($response, $formData, $questions) {
            // Update header respons: set submitted_at dan status
            $response->update([
                'submitted_at' => now(),
                'status' => 'completed',
            ]);

            // Simpan jawaban per pertanyaan
            foreach ($questions as $question) {
                $key   = "question_{$question->id}";
                $value = $formData[$key] ?? null;

                switch ($question->question_type) {
                    case 'single_choice':
                    case 'scale':
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

        // --- VAK SCORING INTEGRATION (Out of Transaction) ---
        $questionnaire = BkQuestionnaire::find($questionnaireId);
        if ($questionnaire && str_contains(strtolower($questionnaire->title), 'vak')) {
            $response->refresh(); // Reload setelah transaction commit

            $vakService = new \App\Services\VakScoringService();
            $result = $vakService->score($response);

            $response->update([
                'score' => $result['dominant_percentage'],
                'score_distribution' => $result['score_distribution'],
                'feedback' => "Gaya belajar dominan: {$result['dominant_style']}",
                'recommendation' => $result['recommendation'],
                'evaluated_at' => now(),
            ]);
        }

        Notification::make()
            ->title('Berhasil Disimpan')
            ->body('Jawaban kuesioner berhasil dikirim. Terima kasih!')
            ->success()
            ->send();
    }

    /**
     * Filament Action: mengisi kuesioner via modal.
     * Hanya ditampilkan untuk role 'student'.
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
     * Filament Action: melihat hasil evaluasi Guru BK via modal (read-only).
     * Hanya bisa dimount jika evaluated_at != null.
     */
    public function viewResultAction(): Action
    {
        return Action::make('viewResult')
            ->label('Lihat Hasil')
            ->icon('heroicon-o-chart-bar-square')
            ->color('success')
            ->modalWidth('2xl')
            ->modalHeading(fn (array $arguments) => 'Hasil Asesmen — ' . $this->getQuestionnaireTitle($arguments))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->form(fn (array $arguments) => $this->buildResultView($arguments));
    }

    /**
     * Bangun tampilan hasil evaluasi secara read-only.
     *
     * @return array<Forms\Components\Component>
     */
    private function buildResultView(array $arguments): array
    {
        $questionnaireId = $arguments['questionnaire_id'] ?? 0;

        $students = $this->getAccessibleStudents();
        $studentId = $students->first()?->id;

        $response = BkStudentResponse::where('questionnaire_id', $questionnaireId)
            ->where('student_id', $studentId)
            ->whereNotNull('evaluated_at')
            ->first();

        if (! $response) {
            return [
                Forms\Components\Placeholder::make('no_result')
                    ->content('Hasil evaluasi belum tersedia.')
                    ->columnSpanFull(),
            ];
        }

        return [
            Forms\Components\Section::make('Hasil Asesmen')
                ->icon('heroicon-o-chart-bar-square')
                ->schema([
                    Forms\Components\Placeholder::make('result_score')
                        ->label('Skor Kognitif')
                        ->content($response->score . ' / 100'),

                    Forms\Components\Placeholder::make('result_feedback')
                        ->label('Umpan Balik')
                        ->content($response->feedback ?? '—'),

                    Forms\Components\Placeholder::make('result_recommendation')
                        ->label('Saran Metode Belajar')
                        ->content($response->recommendation ?? '—'),

                    Forms\Components\Placeholder::make('result_date')
                        ->label('Tanggal Evaluasi')
                        ->content($response->evaluated_at->format('d M Y H:i')),
                ]),
        ];
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
     * Cek apakah user saat ini adalah wali siswa (bukan siswa sendiri).
     */
    public function isGuardianView(): bool
    {
        return Auth::user()->hasRole('guardian');
    }

    /**
     * Sediakan data ke Blade view.
     */
    protected function getViewData(): array
    {
        $questionnaires = $this->getQuestionnairesForStudent();

        return [
            'questionnaires'  => $questionnaires,
            'page'            => $this,
            'isGuardianView'  => $this->isGuardianView(),
        ];
    }
}
