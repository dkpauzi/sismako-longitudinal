<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Imports\StudentImporter;
use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            // Tombol "Import Siswa" — menggunakan StudentImporter
            // yang sudah dioptimasi untuk shared hosting (chunk 50 baris).
            Actions\ImportAction::make()
                ->importer(StudentImporter::class)
                ->label('Import Siswa')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import Data Siswa dari CSV/Excel')
                ->modalDescription('Upload file CSV/Excel untuk mengimpor data siswa secara massal. Sistem akan otomatis membuat akun login untuk Siswa dan Wali.')
                ->modalSubmitActionLabel('Mulai Import'),
        ];
    }
}
