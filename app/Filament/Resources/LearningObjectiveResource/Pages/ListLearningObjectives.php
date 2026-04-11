<?php

namespace App\Filament\Resources\LearningObjectiveResource\Pages;

use App\Filament\Resources\LearningObjectiveResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLearningObjectives extends ListRecords
{
    protected static string $resource = LearningObjectiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
