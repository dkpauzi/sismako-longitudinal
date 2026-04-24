<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolPostResource\Pages;
use App\Models\SchoolPost;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SchoolPostResource extends Resource
{
    protected static ?string $model = SchoolPost::class;
    protected static ?string $navigationGroup = 'Web Sekolah';
    protected static ?string $navigationLabel = 'Postingan & Pengumuman';
    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('school_profile_id')->default(1),

                // ── Kolom Kiri (2/3) ──────────────────────────────
                Forms\Components\Group::make([

                    Forms\Components\TextInput::make('title')
                        ->label('Judul Postingan')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\RichEditor::make('body')
                        ->label('Isi Konten')
                        ->required()
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'bulletList',
                            'orderedList',
                            'h2',
                            'h3',
                            'link',
                            'blockquote',
                            'undo',
                            'redo',
                        ])
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('gallery_images')
                        ->label('Galeri Foto (Opsional)')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->maxFiles(8)
                        ->directory('posts/gallery')
                        ->columnSpanFull()
                        ->helperText('Upload hingga 8 foto. Foto pertama akan jadi cover jika tidak ada foto utama.'),

                ])->columnSpan(2),

                // ── Kolom Kanan (1/3) — Sidebar Setting ──────────
                Forms\Components\Group::make([

                    Forms\Components\Section::make('Pengaturan Publikasi')
                        ->schema([
                            Forms\Components\Select::make('category')
                                ->label('Kategori')
                                ->options([
                                    'Umum' => 'Umum',
                                    'Pengumuman' => 'Pengumuman',
                                    'Prestasi' => 'Prestasi',
                                    'Kegiatan' => 'Kegiatan',
                                    'Berita' => 'Berita',
                                ])
                                ->default('Umum')
                                ->required(),

                            Forms\Components\Toggle::make('is_published')
                                ->label('Langsung Tayang')
                                ->default(true)
                                ->reactive(),

                            Forms\Components\DateTimePicker::make('published_at')
                                ->label('Jadwalkan Tayang')
                                ->nullable()
                                ->helperText('Kosongkan agar langsung tayang sekarang.')
                                ->hidden(fn(Forms\Get $get) => !$get('is_published')),
                        ]),

                    Forms\Components\Section::make('Foto Utama (Cover)')
                        ->schema([
                            Forms\Components\FileUpload::make('cover_image_path')
                                ->label('')
                                ->image()
                                ->imageEditor()
                                ->directory('posts/covers')
                                ->helperText('Rasio ideal 16:9. Jika kosong, foto galeri pertama dipakai.'),
                        ]),

                ])->columnSpan(1),

            ])->columns(3); // Layout 3 kolom: konten 2 + sidebar 1
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image_path')
                    ->label('Cover')
                    ->defaultImageUrl(asset('images/placeholder.png'))
                    ->square()
                    ->size(56),

                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn(SchoolPost $record) => $record->excerpt),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->sortable()
                    ->color(fn(string $state): string => match ($state) {
                        'Prestasi' => 'success',
                        'Pengumuman' => 'warning',
                        'Kegiatan' => 'info',
                        'Berita' => 'gray',
                        default => 'primary',
                    }),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Tayang')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Tgl. Tayang')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Sekarang')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'Umum' => 'Umum',
                        'Pengumuman' => 'Pengumuman',
                        'Prestasi' => 'Prestasi',
                        'Kegiatan' => 'Kegiatan',
                        'Berita' => 'Berita',
                    ])
                    ->placeholder('Semua Kategori'),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Status Tayang'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchoolPosts::route('/'),
            'create' => Pages\CreateSchoolPost::route('/create'),
            'edit' => Pages\EditSchoolPost::route('/{record}/edit'),
        ];
    }
}