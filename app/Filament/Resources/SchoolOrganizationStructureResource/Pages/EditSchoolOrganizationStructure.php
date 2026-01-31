<?php

namespace App\Filament\Resources\SchoolOrganizationStructureResource\Pages;

use App\Filament\Resources\SchoolOrganizationStructureResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSchoolOrganizationStructure extends EditRecord
{
    protected static string $resource = SchoolOrganizationStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
