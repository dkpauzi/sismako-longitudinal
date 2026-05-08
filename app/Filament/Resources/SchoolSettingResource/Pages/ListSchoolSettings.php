<?php

namespace App\Filament\Resources\SchoolSettingResource\Pages;

use App\Filament\Resources\SchoolSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListSchoolSettings extends ListRecords
{
    protected static string $resource = SchoolSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
