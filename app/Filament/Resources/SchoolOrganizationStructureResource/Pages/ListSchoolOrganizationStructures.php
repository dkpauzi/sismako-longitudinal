<?php

namespace App\Filament\Resources\SchoolOrganizationStructureResource\Pages;

use App\Filament\Resources\SchoolOrganizationStructureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSchoolOrganizationStructures extends ListRecords
{
    protected static string $resource = SchoolOrganizationStructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
