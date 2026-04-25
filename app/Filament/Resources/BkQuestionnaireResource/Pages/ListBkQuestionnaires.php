<?php

namespace App\Filament\Resources\BkQuestionnaireResource\Pages;

use App\Filament\Resources\BkQuestionnaireResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBkQuestionnaires extends ListRecords
{
    protected static string $resource = BkQuestionnaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
