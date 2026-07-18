<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Selaraskan kolom legacy `users.role` bila role Spatie diubah (Audit 1.2).
     */
    protected function afterSave(): void
    {
        $this->record->syncLegacyRoleColumn();
    }
}
