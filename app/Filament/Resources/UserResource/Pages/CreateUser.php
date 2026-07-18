<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Selaraskan kolom legacy `users.role` dengan role Spatie terpilih (Audit 1.2)
     * agar akun buatan manual tidak tertinggal di default 'student'.
     */
    protected function afterCreate(): void
    {
        $this->record->syncLegacyRoleColumn();
    }
}
