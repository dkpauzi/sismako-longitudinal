<?php

namespace App\Filament\Resources\StudentSubjectEnrollmentResource\Pages;

use App\Filament\Resources\StudentSubjectEnrollmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStudentSubjectEnrollments extends ListRecords
{
    protected static string $resource = StudentSubjectEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
