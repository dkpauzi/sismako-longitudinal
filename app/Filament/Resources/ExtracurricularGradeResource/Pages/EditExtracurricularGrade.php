<?php

namespace App\Filament\Resources\ExtracurricularGradeResource\Pages;

use App\Filament\Resources\ExtracurricularGradeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExtracurricularGrade extends EditRecord
{
    protected static string $resource = ExtracurricularGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
