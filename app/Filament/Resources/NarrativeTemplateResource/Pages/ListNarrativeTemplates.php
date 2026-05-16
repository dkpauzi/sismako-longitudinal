<?php

namespace App\Filament\Resources\NarrativeTemplateResource\Pages;

use App\Filament\Resources\NarrativeTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListNarrativeTemplates extends ListRecords
{
    protected static string $resource = NarrativeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
