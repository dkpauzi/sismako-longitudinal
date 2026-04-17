<?php

namespace App\Filament\Resources\LessonJournalResource\Pages;

use App\Filament\Resources\LessonJournalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLessonJournal extends EditRecord
{
    protected static string $resource = LessonJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
