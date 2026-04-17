<?php

namespace App\Filament\Resources\RaporResource\Pages;

use App\Filament\Resources\RaporResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRapor extends EditRecord
{
    protected static string $resource = RaporResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
