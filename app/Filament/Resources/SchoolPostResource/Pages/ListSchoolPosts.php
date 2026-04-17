<?php

namespace App\Filament\Resources\SchoolPostResource\Pages;

use App\Filament\Resources\SchoolPostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSchoolPosts extends ListRecords
{
    protected static string $resource = SchoolPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tulis Postingan Baru')
        ];
    }
}
