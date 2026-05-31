<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolActivityResource\Pages;
use App\Filament\Resources\SchoolActivityResource\RelationManagers;
use App\Models\SchoolActivity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SchoolActivityResource extends Resource
{
    protected static ?string $navigationGroup = 'Web Sekolah';
    protected static ?string $navigationLabel = 'Galeri Kegiatan'; // Label Menu
    protected static ?string $modelLabel = 'Kegiatan';
    protected static ?string $pluralModelLabel = 'Galeri Kegiatan';
    protected static ?int $navigationSort = 3;
    protected static ?string $model = SchoolActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('school_profile_id')->default(1),

                Forms\Components\FileUpload::make('image_path')
                    ->label('Foto Kegiatan')
                    ->image()
                    ->directory('activities')
                    ->columnSpanFull()
                    ->required(),

                Forms\Components\TextInput::make('title')
                    ->label('Judul Kegiatan')
                    ->required(),

                Forms\Components\DatePicker::make('date')
                    ->label('Tanggal')
                    ->default(now()),

                Forms\Components\Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_published')
                    ->label('Tampilkan di Web')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('Foto'),
                Tables\Columns\TextColumn::make('title')->searchable()->label('Judul'),
                Tables\Columns\TextColumn::make('date')->date()->label('Tanggal'),
                Tables\Columns\IconColumn::make('is_published')->boolean()->label('Tayang'),
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
            'index' => Pages\ListSchoolActivities::route('/'),
            'create' => Pages\CreateSchoolActivity::route('/create'),
            'edit' => Pages\EditSchoolActivity::route('/{record}/edit'),
        ];
    }
}
