<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Models\TeachingAssignment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FinalGradesRelationManager extends RelationManager
{
    protected static string $relationship = 'finalGrades';
    protected static ?string $title = 'Override Nilai Akhir (Admin)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->hasAnyRole(['super_admin', 'admin']);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('teaching_assignment_id')
                    ->label('Mata Pelajaran')
                    ->options(function () {
                        // Ambil semua teaching assignments yang tersedia
                        return TeachingAssignment::with('subject', 'academicPeriod')
                            ->get()
                            ->mapWithKeys(fn ($ta) => [
                                $ta->id => $ta->subject->name . ' - ' . $ta->academicPeriod->name
                            ]);
                    })
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('semester')
                    ->options(['odd' => 'Ganjil', 'even' => 'Genap'])
                    ->required(),

                Forms\Components\TextInput::make('final_score')
                    ->label('Nilai Akhir')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required(),

                Forms\Components\Select::make('grade_label')
                    ->label('Grade')
                    ->options(['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E']),

                Forms\Components\Textarea::make('narrative_description')
                    ->label('Deskripsi Naratif')
                    ->rows(3)
                    ->columnSpanFull(),

                // OTOMATIS: is_manual_override = true
                Forms\Components\Hidden::make('is_manual_override')
                    ->default(true)
                    ->dehydrated(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('teachingAssignment.subject.name')->label('Mapel'),
                Tables\Columns\TextColumn::make('semester')
                    ->formatStateUsing(fn(string $state): string => $state === 'odd' ? 'Ganjil' : 'Genap')
                    ->badge(),
                Tables\Columns\TextColumn::make('final_score')->label('Nilai'),
                Tables\Columns\TextColumn::make('grade_label')->label('Grade')->badge(),
                Tables\Columns\IconColumn::make('is_manual_override')
                    ->label('Override')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Input Nilai Manual'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
