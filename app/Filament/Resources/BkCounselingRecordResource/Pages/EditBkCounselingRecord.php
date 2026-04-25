<?php

namespace App\Filament\Resources\BkCounselingRecordResource\Pages;

use App\Filament\Resources\BkCounselingRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBkCounselingRecord extends EditRecord
{
    protected static string $resource = BkCounselingRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
