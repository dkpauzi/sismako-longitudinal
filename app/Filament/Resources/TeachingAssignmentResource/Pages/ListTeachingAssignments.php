<?php

namespace App\Filament\Resources\TeachingAssignmentResource\Pages;

use App\Filament\Resources\TeachingAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeachingAssignments extends ListRecords
{
    protected static string $resource = TeachingAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
