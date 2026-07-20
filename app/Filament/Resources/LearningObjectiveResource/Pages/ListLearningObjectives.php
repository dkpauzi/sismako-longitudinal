<?php

namespace App\Filament\Resources\LearningObjectiveResource\Pages;

use App\Filament\Resources\LearningObjectiveResource;
use App\Models\AcademicPeriod;
use App\Services\LearningObjectiveCopyService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLearningObjectives extends ListRecords
{
    protected static string $resource = LearningObjectiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->copyObjectivesAction(),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * SALIN TP ANTAR-PERIODE (Mandate 3).
     *
     * Operasi BULK (berbeda dari otoring TP per-baris): tersedia untuk
     * super_admin/admin (semua mapel) & teacher (hanya mapel yang ia ampu di
     * periode TUJUAN). Idempoten & RBAC ditegakkan di LearningObjectiveCopyService.
     */
    protected function copyObjectivesAction(): Actions\Action
    {
        return Actions\Action::make('copy_objectives')
            ->label('Salin TP Antar-Periode')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->visible(fn() => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'teacher']) ?? false)
            ->modalHeading('Salin Tujuan Pembelajaran Antar-Periode')
            ->modalDescription('TP dari periode sumber akan disalin ke periode tujuan. TP dengan kode & mapel yang sama di periode tujuan otomatis dilewati (tidak diduplikasi).')
            ->form([
                Forms\Components\Select::make('source_academic_period_id')
                    ->label('Periode Sumber')
                    ->options(AcademicPeriod::getSelectOptions())
                    ->required()
                    ->live(),

                Forms\Components\Select::make('target_academic_period_id')
                    ->label('Periode Tujuan')
                    ->options(AcademicPeriod::getSelectOptions())
                    ->default(fn() => AcademicPeriod::where('is_active', true)->first()?->id)
                    ->required()
                    ->different('source_academic_period_id')
                    ->helperText('Default: tahun ajaran aktif.'),
            ])
            ->action(function (array $data) {
                $service = new LearningObjectiveCopyService();
                $user = auth()->user();

                $allowed = $service->allowedSubjectIdsFor($user, (int) $data['target_academic_period_id']);

                // Backend catch: selain guard frontend ->different(), tangkap
                // InvalidArgumentException (mis. periode sumber == tujuan) agar
                // muncul notifikasi anggun, bukan halaman 500.
                try {
                    $result = $service->copy(
                        (int) $data['source_academic_period_id'],
                        (int) $data['target_academic_period_id'],
                        $allowed,
                    );
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->danger()
                        ->title($e->getMessage())
                        ->send();

                    return;
                }

                if ($result['copied'] === 0 && $result['skipped'] === 0) {
                    Notification::make()
                        ->title('Tidak ada TP yang disalin')
                        ->body('Tidak ada TP pada periode sumber untuk cakupan mapel Anda.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Salin TP selesai')
                    ->body("{$result['copied']} TP disalin, {$result['skipped']} dilewati (sudah ada).")
                    ->success()
                    ->send();
            });
    }
}
