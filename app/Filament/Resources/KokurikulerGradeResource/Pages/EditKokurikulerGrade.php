<?php

namespace App\Filament\Resources\KokurikulerGradeResource\Pages;

use App\Filament\Resources\KokurikulerGradeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKokurikulerGrade extends EditRecord
{
    protected static string $resource = KokurikulerGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
