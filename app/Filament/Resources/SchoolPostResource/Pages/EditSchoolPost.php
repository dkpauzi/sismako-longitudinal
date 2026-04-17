<?php
namespace App\Filament\Resources\SchoolPostResource\Pages;
use App\Filament\Resources\SchoolPostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSchoolPost extends EditRecord
{
    protected static string $resource = SchoolPostResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}