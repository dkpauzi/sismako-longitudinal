<?php

namespace App\Filament\Resources\TeachingAssignmentResource\RelationManagers;

use App\Models\NarrativeTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

/**
 * RelationManager untuk Guru Mapel mengelola override template narasi rapor.
 *
 * Muncul sebagai tab "Template Narasi" di halaman Edit SK Mengajar.
 * Guru bisa mengisi template kustom per grade (A-E) untuk kelasnya.
 * Jika tidak diisi, sistem akan fallback ke template default Admin.
 *
 * Template menggunakan placeholder [TP] yang diganti dengan nama
 * Tujuan Pembelajaran saat narasi rapor di-generate.
 */
class NarrativeTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'narrativeTemplates';
    protected static ?string $title = 'Template Narasi Rapor';
    protected static ?string $icon = 'heroicon-o-document-text';

    /**
     * Sembunyikan tab ini untuk mapel Kokurikuler (P5).
     */
    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return !$ownerRecord->isKokurikuler();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('grade_letter')
                    ->label('Grade')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'A' => 'success',
                        'B' => 'info',
                        'C' => 'warning',
                        'D' => 'danger',
                        'E' => 'gray',
                    }),

                Tables\Columns\TextColumn::make('template_text')
                    ->label('Template Kustom')
                    ->limit(80)
                    ->wrap(),
            ])
            ->emptyStateHeading('Belum ada template kustom')
            ->emptyStateDescription('Gunakan template default Admin. Klik "Buat Template Kustom" untuk membuat override khusus kelas ini.')
            ->headerActions([
                Tables\Actions\Action::make('seed_custom')
                    ->label('Buat Template Kustom')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Buat Template Kustom?')
                    ->modalDescription(
                        'Sistem akan menyalin 5 template default Admin ke kelas ini. ' .
                        'Anda bisa mengedit setiap template setelahnya. ' .
                        'Template yang sudah ada tidak akan ditimpa.'
                    )
                    ->action(function () {
                        $assignmentId = $this->getOwnerRecord()->id;

                        // Cek apakah sudah ada template kustom
                        $existingCount = NarrativeTemplate::where('teaching_assignment_id', $assignmentId)
                            ->where('is_default', false)
                            ->count();

                        if ($existingCount >= 5) {
                            Notification::make()
                                ->title('Template sudah lengkap')
                                ->body('5 template kustom sudah tersedia untuk kelas ini.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Salin template default admin untuk grade yang belum ada
                        foreach (['A', 'B', 'C', 'D', 'E'] as $letter) {
                            $exists = NarrativeTemplate::where('teaching_assignment_id', $assignmentId)
                                ->where('grade_letter', $letter)
                                ->where('is_default', false)
                                ->exists();

                            if (!$exists) {
                                $adminTemplate = NarrativeTemplate::getTemplate($letter);
                                NarrativeTemplate::create([
                                    'grade_letter' => $letter,
                                    'template_text' => $adminTemplate,
                                    'is_default' => false,
                                    'teaching_assignment_id' => $assignmentId,
                                ]);
                            }
                        }

                        Notification::make()
                            ->title('Template kustom berhasil dibuat')
                            ->body('5 template sudah disalin dari default Admin. Silakan edit sesuai kebutuhan.')
                            ->success()
                            ->send();
                    })
                    ->visible(function () {
                        $count = NarrativeTemplate::where('teaching_assignment_id', $this->getOwnerRecord()->id)
                            ->where('is_default', false)
                            ->count();
                        return $count < 5;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->form([
                        Forms\Components\Select::make('grade_letter')
                            ->label('Grade')
                            ->options([
                                'A' => 'A — Sangat Memuaskan',
                                'B' => 'B — Memuaskan',
                                'C' => 'C — Cukup',
                                'D' => 'D — Kurang',
                                'E' => 'E — Sangat Kurang',
                            ])
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\Textarea::make('template_text')
                            ->label('Template Kalimat')
                            ->helperText('Gunakan [TP] sebagai placeholder Tujuan Pembelajaran.')
                            ->required()
                            ->rows(3),

                        Forms\Components\Placeholder::make('preview')
                            ->label('Pratinjau')
                            ->content(function (Forms\Get $get): string {
                                $template = $get('template_text') ?? '';
                                return str_replace('[TP]', 'aljabar dan geometri', $template) ?: 'Belum ada template...';
                            }),
                    ]),

                Tables\Actions\DeleteAction::make()
                    ->label('Hapus Override')
                    ->modalHeading('Hapus Template Kustom?')
                    ->modalDescription('Template akan dihapus dan sistem akan kembali menggunakan template default Admin untuk grade ini.'),
            ]);
    }
}
