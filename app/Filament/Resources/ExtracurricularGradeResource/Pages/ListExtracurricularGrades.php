<?php

namespace App\Filament\Resources\ExtracurricularGradeResource\Pages;

use App\Filament\Resources\ExtracurricularGradeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExtracurricularGrades extends ListRecords
{
    protected static string $resource = ExtracurricularGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Daftarkan Siswa'),
        ];
    }
}
