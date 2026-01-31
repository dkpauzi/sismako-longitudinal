<?php

namespace App\Filament\Resources\SchoolActivityResource\Pages;

use App\Filament\Resources\SchoolActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSchoolActivity extends EditRecord
{
    protected static string $resource = SchoolActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
