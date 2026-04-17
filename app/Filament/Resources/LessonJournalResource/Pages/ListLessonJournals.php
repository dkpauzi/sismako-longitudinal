<?php

namespace App\Filament\Resources\LessonJournalResource\Pages;

use App\Filament\Resources\LessonJournalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLessonJournals extends ListRecords
{
    protected static string $resource = LessonJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
