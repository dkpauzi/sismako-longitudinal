<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolOrganizationStructureResource\Pages;
use App\Filament\Resources\SchoolOrganizationStructureResource\RelationManagers;
use App\Models\SchoolOrganizationStructure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SchoolOrganizationStructureResource extends Resource
{
    protected static ?string $navigationGroup = 'Web Sekolah';
    protected static ?string $navigationLabel = 'Struktur Organisasi'; // Label Menu
    protected static ?string $modelLabel = 'Struktur Organisasi';
    protected static ?string $pluralModelLabel = 'Struktur Organisasi';
    protected static ?int $navigationSort = 2;
    protected static ?string $model = SchoolOrganizationStructure::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Hidden ID: Asumsikan ID profil sekolah selalu 1 (karena cuma satu sekolah)
                Forms\Components\Hidden::make('school_profile_id')->default(1),

                Forms\Components\FileUpload::make('photo_path')
                    ->label('Foto Personil')
                    ->image()
                    ->avatar()
                    ->directory('organization'),

                Forms\Components\TextInput::make('name')
                    ->label('Nama Lengkap')
                    ->required(),

                Forms\Components\TextInput::make('position')
                    ->label('Jabatan')
                    ->required(),

                Forms\Components\Select::make('order')
                    ->label('Urutan Tampil')
                    ->options(function (?Model $record) {
                        // 1. Tentukan batas maksimal urutan (misal: Struktur max 20 orang)
                        $maxOrder = 20;
                        $allOrders = range(1, $maxOrder);

                        // 2. Ambil daftar nomor yang SUDAH DIPAKAI orang lain
                        $takenOrders = SchoolOrganizationStructure::query()
                            ->when($record, function ($query) use ($record) {
                            // PENTING: Jika sedang EDIT, abaikan nomor milik diri sendiri
                            // Supaya nomor user saat ini tetap muncul di list
                            return $query->where('id', '!=', $record->id);
                        })
                            ->pluck('order')
                            ->toArray();

                        // 3. Cari selisihnya (Angka tersedia = Semua Angka - Angka Terpakai)
                        $availableOrders = array_diff($allOrders, $takenOrders);

                        // 4. Ubah format array jadi [1 => 1, 3 => 3] agar bisa dibaca Filament
                        return array_combine($availableOrders, $availableOrders);
                    })
                    ->required()
                    ->searchable() // Biar bisa diketik angkanya
                    ->preload()
                    ->default(function () {
                        // Otomatis cari angka terkecil yang kosong saat Create Baru
                        $taken = SchoolOrganizationStructure::pluck('order')->toArray();
                        for ($i = 1; $i <= 20; $i++) {
                            if (!in_array($i, $taken))
                                return $i;
                        }
                        return null;
                    })
                    ->helperText('Angka yang muncul adalah slot yang masih kosong. 1 = Paling Atas (Kepsek).'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo_path')->circular()->label('Foto'),
                Tables\Columns\TextColumn::make('name')->searchable()->label('Nama'),
                Tables\Columns\TextColumn::make('position')->sortable()->label('Jabatan'),
                Tables\Columns\TextColumn::make('order')->sortable()->label('Urutan'),
            ])
            ->defaultSort('order', 'asc') // Urutkan dari angka kecil (atas) ke besar
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
            'index' => Pages\ListSchoolOrganizationStructures::route('/'),
            'create' => Pages\CreateSchoolOrganizationStructure::route('/create'),
            'edit' => Pages\EditSchoolOrganizationStructure::route('/{record}/edit'),
        ];
    }
}
