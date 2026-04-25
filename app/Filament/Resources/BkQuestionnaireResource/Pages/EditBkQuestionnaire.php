<?php

namespace App\Filament\Resources\BkQuestionnaireResource\Pages;

use App\Filament\Resources\BkQuestionnaireResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBkQuestionnaire extends EditRecord
{
    protected static string $resource = BkQuestionnaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
