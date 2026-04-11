<?php

namespace App\Filament\Resources\LearningObjectiveResource\Pages;

use App\Filament\Resources\LearningObjectiveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLearningObjective extends EditRecord
{
    protected static string $resource = LearningObjectiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
