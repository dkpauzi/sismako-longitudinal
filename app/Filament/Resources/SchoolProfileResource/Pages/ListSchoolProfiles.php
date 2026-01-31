<?php

namespace App\Filament\Resources\SchoolProfileResource\Pages;

use App\Filament\Resources\SchoolProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSchoolProfiles extends ListRecords
{
    protected static string $resource = SchoolProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
