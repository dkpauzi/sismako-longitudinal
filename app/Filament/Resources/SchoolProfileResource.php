<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolProfileResource\Pages;
use App\Filament\Resources\SchoolProfileResource\RelationManagers;
use App\Models\SchoolProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Filament\Forms\Get; // Import Helper Get

class SchoolProfileResource extends Resource
{
    protected static ?string $navigationGroup = 'Web Sekolah';
    protected static ?string $navigationLabel = 'Tentang Kami'; // Label Menu
    protected static ?int $navigationSort = 1;
    protected static ?string $model = SchoolProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Profil Sekolah')
                    ->tabs([
                        // --- TAB 1: IDENTITAS ---
                        Forms\Components\Tabs\Tab::make('Identitas Utama')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Sekolah')
                                    ->required(),
                                Forms\Components\TextInput::make('npsn')
                                    ->label('NPSN'),
                                Forms\Components\Select::make('accreditation')
                                    ->label('Akreditasi')
                                    ->options(['A' => 'A', 'B' => 'B', 'C' => 'C', 'Belum Terakreditasi' => 'Belum']),
                                Forms\Components\TextInput::make('phone')
                                    ->tel(),
                                Forms\Components\TextInput::make('email')
                                    ->email(),
                                Forms\Components\TextInput::make('website')
                                    ->url(),
                                Forms\Components\Textarea::make('address')
                                    ->label('Alamat Lengkap')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                // --- TAMBAHAN BARU: GOOGLE MAPS ---
                                Forms\Components\Textarea::make('google_maps_embed')
                                    ->label('Peta Lokasi (Google Maps Embed)')
                                    ->placeholder('<iframe src="https://www.google.com/maps/embed?..." width="600" height="450" ...></iframe>')
                                    ->rows(5) // Agak tinggi biar kodenya kelihatan
                                    ->columnSpanFull() // Memanjang penuh
                                    ->helperText(new HtmlString('
        <strong>Cara mendapatkan kode:</strong>
        <ol style="list-style-type: decimal; margin-left: 15px; margin-top: 5px;">
            <li>Buka <a href="https://www.google.com/maps" target="_blank" style="color: blue; text-decoration: underline;">Google Maps</a> dan cari lokasi sekolah.</li>
            <li>Klik tombol <strong>"Bagikan" (Share)</strong>.</li>
            <li>Pilih tab <strong>"Sematkan Peta" (Embed a map)</strong>.</li>
            <li>Klik <strong>"Salin HTML"</strong> lalu tempel (paste) di kolom ini.</li>
        </ol>
        <div style="margin-top: 5px;">
            <a href="https://support.google.com/maps/answer/144361?hl=id" target="_blank" style="color: #d97706; font-weight: bold;">
                🔗 Klik di sini untuk panduan visual (Tutorial Resmi)
            </a>
        </div>
    ')),
                                Forms\Components\Placeholder::make('preview_map')
                                    ->label('Preview Tampilan Peta')
                                    ->content(function (Get $get) {
                                        $mapCode = $get('google_maps_embed');

                                        // Jika kosong, jangan tampilkan apa-apa
                                        if (!$mapCode) {
                                            return null;
                                        }

                                        // Render kode iframe HTML
                                        return new HtmlString('<div style="aspect-ratio: 16/9; overflow: hidden; border-radius: 8px;">' . $mapCode . '</div>');
                                    })
                                    ->hidden(fn(Get $get) => !$get('google_maps_embed')) // Sembunyikan jika input kosong
                                    ->columnSpanFull(),

                            ])->columns(2),

                        // --- TAB 2: BRANDING & SOSMED ---
                        Forms\Components\Tabs\Tab::make('Branding & Sosmed')
                            ->icon('heroicon-o-paint-brush')
                            ->schema([
                                Forms\Components\FileUpload::make('logo_path')
                                    ->label('Logo Sekolah')
                                    ->image()
                                    ->directory('school-assets') // Disimpan di folder storage/app/public/school-assets
                                    ->imageEditor(),
                                Forms\Components\FileUpload::make('banner_image_path')
                                    ->label('Foto Banner Utama (Landing Page)')
                                    ->image()
                                    ->directory('school-assets')
                                    ->columnSpanFull(),
                                Forms\Components\ColorPicker::make('primary_color')
                                    ->label('Warna Tema Website'),

                                Forms\Components\Section::make('Sosial Media')
                                    ->schema([
                                        Forms\Components\TextInput::make('facebook_url')->url()->prefix('fb.com/'),
                                        Forms\Components\TextInput::make('instagram_url')->url()->prefix('instagram.com/'),
                                        Forms\Components\TextInput::make('youtube_url')->url()->prefix('youtube.com/'),
                                    ])->columns(3),
                            ]),

                        // --- TAB 3: VISI MISI & SAMBUTAN ---
                        Forms\Components\Tabs\Tab::make('Visi Misi & Sambutan')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Section::make('Kepala Sekolah')
                                    ->schema([
                                        Forms\Components\TextInput::make('principal_name')->label('Nama Kepsek'),
                                        Forms\Components\FileUpload::make('principal_photo_path')->image()->directory('school-assets'),
                                        Forms\Components\Textarea::make('welcome_message')->label('Kata Sambutan')->rows(3),
                                    ])->columns(2),

                                Forms\Components\RichEditor::make('history')
                                    ->label('Sejarah Sekolah')
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('vision')
                                    ->label('Visi Sekolah')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                // REPEATER: Input Misi bisa ditambah berkali-kali
                                Forms\Components\Repeater::make('missions')
                                    ->relationship('missions') // Relasi ke tabel school_missions
                                    ->label('Daftar Misi')
                                    ->schema([
                                        Forms\Components\TextInput::make('content')
                                            ->label('Butir Misi')
                                            ->required(),
                                    ])
                                    ->addActionLabel('Tambah Misi')
                                    ->defaultItems(3),
                            ]),

                        // --- TAB 4: FASILITAS ---
                        Forms\Components\Tabs\Tab::make('Fasilitas')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\Repeater::make('facilities')
                                    ->relationship('facilities') // Relasi ke tabel school_facilities
                                    ->label('Galeri Fasilitas')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_path')
                                            ->label('Foto')
                                            ->image()
                                            ->directory('facilities')
                                            ->required(),
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nama Fasilitas')
                                            ->placeholder('Cth: Lab Komputer')
                                            ->required(),
                                        Forms\Components\Textarea::make('description')
                                            ->label('Deskripsi Singkat')
                                            ->rows(2),
                                    ])
                                    ->grid(2) // Tampil 2 kolom per baris
                                    ->addActionLabel('Tambah Fasilitas'),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public') // <--- PENTING: Paksa baca dari folder public
                    //->visibility('private') // Kadang perlu diset explicit
                    ->defaultImageUrl(url('/images/placeholder.png')) // (Opsional) jika gambar rusak
                    ->circular(), // (Opsional) Biar tampil bulat rapi
                Tables\Columns\TextColumn::make('name')->label('Nama Sekolah')->weight('bold'),
                Tables\Columns\TextColumn::make('address')->label('Alamat')->limit(50),
                Tables\Columns\TextColumn::make('phone')->label('Telepon'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->label('Terakhir Update'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->paginated(false) // Matikan pagination karena cuma 1 data
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
            'index' => Pages\ListSchoolProfiles::route('/'),
            'create' => Pages\CreateSchoolProfile::route('/create'),
            'edit' => Pages\EditSchoolProfile::route('/{record}/edit'),
        ];
    }
}
