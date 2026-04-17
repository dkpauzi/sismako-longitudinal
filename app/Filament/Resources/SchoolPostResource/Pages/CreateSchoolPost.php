<?php
namespace App\Filament\Resources\SchoolPostResource\Pages;
use App\Filament\Resources\SchoolPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSchoolPost extends CreateRecord
{
    protected static string $resource = SchoolPostResource::class;

    // Auto-set published_at saat create jika kosong
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        return $data;
    }
}