<?php

namespace App\Filament\Resources\KokurikulerGradeResource\Pages;

use App\Filament\Resources\KokurikulerGradeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKokurikulerGrades extends ListRecords
{
    protected static string $resource = KokurikulerGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
