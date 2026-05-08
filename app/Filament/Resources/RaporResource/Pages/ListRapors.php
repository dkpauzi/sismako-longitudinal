<?php

namespace App\Filament\Resources\RaporResource\Pages;

use App\Filament\Resources\RaporResource;
use Filament\Resources\Pages\ListRecords;

class ListRapors extends ListRecords
{
    protected static string $resource = RaporResource::class;

    protected function getHeaderActions(): array
    {
        // Resource Rekap Rapor bersifat read-only.
        // Jangan tampilkan aksi Create agar tidak memicu INSERT kosong ke class_homerooms.
        return [];
    }
}
