<?php

namespace App\Filament\Resources\TeachingAssignmentResource\Pages;

use App\Filament\Resources\TeachingAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTeachingAssignment extends EditRecord
{
    protected static string $resource = TeachingAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
