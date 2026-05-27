<?php

namespace App\Filament\Widgets;

use App\Models\AcademicPeriod;
use App\Models\StudentSubjectEnrollment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class MyExtracurricularsWidget extends BaseWidget
{
    protected static ?string $heading = 'Ekstrakurikuler Saya';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        // Only students can view this widget
        return Auth::check() && Auth::user()->hasRole('student');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StudentSubjectEnrollment::query()
                    ->where('student_id', Auth::user()->student?->id)
                    ->whereHas('teachingAssignment', function ($query) {
                        $activePeriod = AcademicPeriod::where('is_active', true)->first();
                        $query->where('academic_period_id', $activePeriod?->id)
                              ->whereHas('subject', function ($q) {
                                  $q->where('type', 'extracurricular');
                              });
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('teachingAssignment.subject.name')
                    ->label('Ekstrakurikuler')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('predicate')
                    ->label('Predikat')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Sangat Baik' => 'success',
                        'Baik' => 'info',
                        'Cukup' => 'warning',
                        'Kurang' => 'danger',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->wrap(),
            ])
            ->paginated(false);
    }
}
