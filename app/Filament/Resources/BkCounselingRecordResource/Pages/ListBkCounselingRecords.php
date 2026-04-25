<?php

namespace App\Filament\Resources\BkCounselingRecordResource\Pages;

use App\Filament\Resources\BkCounselingRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBkCounselingRecords extends ListRecords
{
    protected static string $resource = BkCounselingRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
