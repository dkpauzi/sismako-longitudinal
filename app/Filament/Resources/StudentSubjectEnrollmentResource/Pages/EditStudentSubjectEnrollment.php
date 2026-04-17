<?php

namespace App\Filament\Resources\StudentSubjectEnrollmentResource\Pages;

use App\Filament\Resources\StudentSubjectEnrollmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStudentSubjectEnrollment extends EditRecord
{
    protected static string $resource = StudentSubjectEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
