<?php

namespace App\Filament\Resources\BkQuestionnaireResource\RelationManagers;

use App\Models\BkStudentResponse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * RelationManager: Menampilkan daftar respons siswa pada halaman Edit kuesioner.
 * Guru BK dapat meninjau jawaban dan mengevaluasi setiap respons.
 */
class BkStudentResponseRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';
    protected static ?string $title = 'Respons Siswa';
    protected static ?string $modelLabel = 'Respons';

    public function table(Table $table): Table
    {
        return $table
            // Eager-load untuk menghindari N+1
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'student.user',
                'student.currentClassroom',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.currentClassroom.name')
                    ->label('Kelas')
                    ->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Waktu Submit')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->label('Skor Kognitif')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('evaluated_at')
                    ->label('Status Evaluasi')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Sudah Dievaluasi' : 'Belum Dievaluasi')
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('evaluated_at')
                    ->label('Status Evaluasi')
                    ->nullable()
                    ->trueLabel('Sudah Dievaluasi')
                    ->falseLabel('Belum Dievaluasi')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('evaluated_at'),
                        false: fn (Builder $query) => $query->whereNull('evaluated_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('evaluate')
                    ->label('Evaluasi')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('3xl')
                    ->modalHeading(fn (BkStudentResponse $record) => 'Evaluasi: ' . ($record->student?->user?->name ?? 'Siswa'))
                    ->modalSubmitActionLabel('Simpan Evaluasi')
                    ->modalCancelActionLabel('Batal')
                    ->fillForm(fn (BkStudentResponse $record) => [
                        'score'          => $record->score,
                        'feedback'       => $record->feedback,
                        'recommendation' => $record->recommendation,
                    ])
                    ->form(fn (BkStudentResponse $record) => [
                        // ── SECTION 1: Jawaban Siswa (Read-Only) ──────────
                        Forms\Components\Section::make('Jawaban Siswa')
                            ->description('Berikut adalah jawaban yang diberikan oleh siswa.')
                            ->icon('heroicon-o-document-text')
                            ->collapsible()
                            ->schema(
                                $this->buildStudentAnswerViews($record)
                            ),

                        // ── SECTION 2: Form Evaluasi Guru BK ─────────────
                        Forms\Components\Section::make('Evaluasi Guru BK')
                            ->description('Berikan penilaian dan rekomendasi berdasarkan jawaban siswa.')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Forms\Components\TextInput::make('score')
                                    ->label('Skor Kognitif')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->suffix('/ 100')
                                    ->required()
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('feedback')
                                    ->label('Umpan Balik')
                                    ->rows(4)
                                    ->required()
                                    ->columnSpanFull()
                                    ->hintAction(
                                        Forms\Components\Actions\Action::make('ai_feedback')
                                            ->label('Saran AI (Segera Hadir)')
                                            ->icon('heroicon-m-sparkles')
                                            ->color('gray')
                                            ->disabled()
                                            ->tooltip('Fitur integrasi AI/Expert System akan tersedia di versi mendatang.')
                                    ),

                                Forms\Components\Textarea::make('recommendation')
                                    ->label('Saran Metode Belajar')
                                    ->rows(4)
                                    ->required()
                                    ->columnSpanFull()
                                    ->hintAction(
                                        Forms\Components\Actions\Action::make('ai_recommendation')
                                            ->label('Saran AI (Segera Hadir)')
                                            ->icon('heroicon-m-sparkles')
                                            ->color('gray')
                                            ->disabled()
                                            ->tooltip('Fitur integrasi AI/Expert System akan tersedia di versi mendatang.')
                                    ),
                            ]),
                    ])
                    ->action(function (BkStudentResponse $record, array $data) {
                        $record->update([
                            'score'          => $data['score'],
                            'feedback'       => $data['feedback'],
                            'recommendation' => $data['recommendation'],
                            'evaluated_at'   => now(),
                        ]);

                        Notification::make()
                            ->title('Evaluasi Berhasil Disimpan')
                            ->body('Skor, umpan balik, dan rekomendasi telah disimpan untuk siswa ini.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * Bangun tampilan jawaban siswa secara read-only sebagai Placeholder fields.
     * Eager-load answers dengan relasi question dan selectedOption.
     *
     * @return array<Forms\Components\Component>
     */
    private function buildStudentAnswerViews(BkStudentResponse $record): array
    {
        // Eager-load jawaban + relasi untuk menghindari N+1
        $record->load(['answers.question', 'answers.selectedOption']);

        $fields = [];
        $questionNumber = 1;

        // Group answers by question untuk menangani multiple_choice
        $grouped = $record->answers->groupBy('question_id');

        foreach ($grouped as $questionId => $answers) {
            $firstAnswer = $answers->first();
            $question    = $firstAnswer->question;

            if (! $question) {
                continue;
            }

            $label = "{$questionNumber}. {$question->question_text}";

            // Tentukan teks jawaban berdasarkan tipe pertanyaan
            if ($question->question_type === 'text') {
                $answerText = $firstAnswer->text_answer ?? '—';
            } elseif ($question->question_type === 'multiple_choice') {
                // Multiple choice: gabungkan semua opsi terpilih
                $answerText = $answers
                    ->map(fn ($a) => $a->selectedOption?->option_text ?? '—')
                    ->implode(', ');
            } else {
                // single_choice / scale
                $answerText = $firstAnswer->selectedOption?->option_text ?? '—';
            }

            $fields[] = Forms\Components\Placeholder::make("answer_{$questionId}")
                ->label($label)
                ->content($answerText)
                ->columnSpanFull();

            $questionNumber++;
        }

        if (empty($fields)) {
            $fields[] = Forms\Components\Placeholder::make('no_answers')
                ->content('Belum ada jawaban yang tersedia.')
                ->columnSpanFull();
        }

        return $fields;
    }
}
