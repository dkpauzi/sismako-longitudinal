<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KokurikulerGradeResource\Pages;
use App\Models\KokurikulerGrade;
use App\Models\AcademicPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class KokurikulerGradeResource extends Resource
{
    protected static ?string $model = KokurikulerGrade::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Nilai P5 (Kokurikuler)';
    protected static ?string $modelLabel = 'Nilai Kokurikuler';
    protected static ?string $pluralModelLabel = 'Nilai Kokurikuler';

    public static function canViewAny(): bool
    {
        return Auth::user()->hasAnyRole(['guru', 'Guru', 'super_admin', 'Super Admin']);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('academic_period_id')
                    ->label('Tahun Ajaran')
                    ->options(AcademicPeriod::all()->pluck('name', 'id'))
                    ->default(fn () => AcademicPeriod::where('is_active', true)->first()?->id)
                    ->required(),
                    
                Forms\Components\Select::make('student_id')
                    ->label('Siswa')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('project_title')
                    ->label('Judul Proyek (Tema)')
                    ->placeholder('Cth: Gaya Hidup Berkelanjutan: Pengolahan Sampah')
                    ->maxLength(255)
                    ->required(),

                Forms\Components\Textarea::make('narrative_description')
                    ->label('Deskripsi Narasi P5')
                    ->placeholder('Cth: Ananda telah menunjukkan kemampuan yang sangat baik dalam proyek...')
                    ->rows(5)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Tahun Ajaran')
                    ->sortable(),

                Tables\Columns\TextColumn::make('project_title')
                    ->label('Judul Proyek')
                    ->searchable(),

                Tables\Columns\TextColumn::make('narrative_description')
                    ->label('Narasi')
                    ->limit(50),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKokurikulerGrades::route('/'),
            'create' => Pages\CreateKokurikulerGrade::route('/create'),
            'edit' => Pages\EditKokurikulerGrade::route('/{record}/edit'),
        ];
    }
}
