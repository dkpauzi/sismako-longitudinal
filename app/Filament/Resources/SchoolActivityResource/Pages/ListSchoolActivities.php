<?php

namespace App\Filament\Resources\SchoolActivityResource\Pages;

use App\Filament\Resources\SchoolActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSchoolActivities extends ListRecords
{
    protected static string $resource = SchoolActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
