<?php

namespace App\Filament\Resources\NarrativeTemplateResource\Pages;

use App\Filament\Resources\NarrativeTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNarrativeTemplate extends EditRecord
{
    protected static string $resource = NarrativeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
