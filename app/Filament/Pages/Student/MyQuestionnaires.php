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
        if ($user->hasRole('wali_siswa') && $user->guardianStudents()->exists()) {
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

        if ($user->hasRole('wali_siswa')) {
            return $user->guardianStudents()->get();
        }

        return new Collection();
    }

    /**
     * Ambil daftar kuesioner yang ditargetkan ke kelas siswa saat ini.
     *
     * Eager-loading diterapkan pada: counselor, questions.options, targets.classroom
     * Menambahkan atribut dinamis `has_responded`, `student_response`, `evaluated_at`.
     */
    public function getQuestionnairesForStudent(): Collection
    {
        $students = $this->getAccessibleStudents();

        if ($students->isEmpty()) {
            return new Collection();
        }

        // Ambil tahun ajaran aktif
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (! $activePeriod) {
            return new Collection();
        }

        // Kumpulkan semua classroom_id dari enrollment aktif siswa-siswa
        $studentIds = $students->pluck('id')->toArray();

        $enrollments = Enrollment::whereIn('student_id', $studentIds)
            ->where('academic_period_id', $activePeriod->id)
            ->where('status', 'active')
            ->get();

        if ($enrollments->isEmpty()) {
            return new Collection();
        }

        $classroomIds = $enrollments->pluck('classroom_id')->unique()->toArray();

        // Ambil respon siswa yang sudah ada (untuk menandai status)
        $responses = BkStudentResponse::whereIn('student_id', $studentIds)
            ->get()
            ->keyBy(function ($r) {
                return $r->questionnaire_id . '_' . $r->student_id;
            });

        // Query kuesioner yang published, sesuai periode, ditargetkan ke kelas siswa
        // Eager-load relasi untuk menghindari N+1
        $questionnaires = BkQuestionnaire::with([
                'counselor:id,name',
                'questions.options',
                'targets.classroom',
            ])
            ->where('status', 'published')
            ->where('academic_period_id', $activePeriod->id)
            ->whereHas('targets', function ($query) use ($classroomIds) {
                $query->whereIn('classroom_id', $classroomIds);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Untuk siswa: gunakan student_id pertama sebagai referensi
        // Untuk wali_siswa: bisa saja punya lebih dari 1 anak, ambil anak pertama yang ada enrollment
        $primaryStudentId = $enrollments->first()->student_id;

        // Tandai setiap kuesioner dengan status respons
        $questionnaires->each(function ($q) use ($responses, $primaryStudentId) {
            $key = $q->id . '_' . $primaryStudentId;
            $response = $responses->get($key);

            $q->has_responded   = $response !== null;
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

        // Guard: hanya siswa (bukan wali) yang boleh submit
        if (! $student) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Hanya siswa yang dapat mengisi kuesioner.')
                ->danger()
                ->send();
            return;
        }

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
        return Auth::user()->hasRole('wali_siswa');
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
