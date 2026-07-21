<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BkCounselingRecordResource\Pages;
use App\Models\BkCounselingRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BkCounselingRecordResource extends Resource
{
    protected static ?string $model = BkCounselingRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Bimbingan Konseling';
    protected static ?string $modelLabel = 'Rekam Bimbingan';
    protected static ?string $pluralModelLabel = 'Rekam Bimbingan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('counselor_id')
                    ->default(Auth::id()),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Data Sesi')
                        ->schema([
                            Forms\Components\Select::make('student_id')
                                ->label('Siswa')
                                // titleAttribute = kolom 'name' MILIK Student sendiri (selalu terisi).
                                ->relationship('student', 'name')
                                // Nama diambil dari Student->name, BUKAN user->name — akun user
                                // bisa null/belum tertaut sehingga nama tak muncul di dropdown (bug).
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nisn} - {$record->name}")
                                // Cari berdasarkan NISN MAUPUN NAMA (sebelumnya hanya NISN).
                                ->searchable(['nisn', 'name'])
                                ->required(),
                            Forms\Components\DatePicker::make('session_date')
                                ->label('Tanggal Sesi')
                                ->default(now())
                                ->required(),
                            Forms\Components\Select::make('session_type')
                                ->label('Tipe Sesi')
                                ->options([
                                    'individual' => 'Individu',
                                    'group' => 'Kelompok',
                                    'home_visit' => 'Kunjungan Rumah (Home Visit)',
                                    'online' => 'Daring (Online)',
                                    'other' => 'Lainnya',
                                ])
                                ->required(),
                            Forms\Components\Select::make('category')
                                ->label('Kategori')
                                ->options([
                                    'pribadi' => 'Pribadi',
                                    'sosial' => 'Sosial',
                                    'belajar' => 'Belajar',
                                    'karir' => 'Karir',
                                    'lainnya' => 'Lainnya',
                                ])
                                ->default('pribadi')
                                ->required(),
                        ])->columns(2),

                    Forms\Components\Section::make('Detail Bimbingan')
                        ->schema([
                            Forms\Components\TextInput::make('topic')
                                ->label('Topik Bimbingan')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Textarea::make('description')
                                ->label('Deskripsi / Hasil')
                                ->required()
                                ->rows(4),
                            Forms\Components\Textarea::make('action_taken')
                                ->label('Tindakan yang Diambil')
                                ->rows(3),
                        ]),

                    Forms\Components\Section::make('Tindak Lanjut')
                        ->schema([
                            Forms\Components\DatePicker::make('follow_up_date')
                                ->label('Tanggal Tindak Lanjut'),
                            Forms\Components\Textarea::make('follow_up_note')
                                ->label('Catatan Tindak Lanjut')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])->columns(2),
                ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Pengaturan Visibilitas')
                        ->description('Atur siapa saja yang dapat melihat catatan ini.')
                        ->schema([
                            Forms\Components\Toggle::make('is_visible_to_student')
                                ->label('Terlihat oleh Siswa')
                                ->default(false),
                            Forms\Components\Toggle::make('is_visible_to_guardian')
                                ->label('Terlihat oleh Wali Siswa')
                                ->default(false),
                            Forms\Components\Toggle::make('is_visible_to_homeroom')
                                ->label('Terlihat oleh Wali Kelas')
                                ->default(false),
                            Forms\Components\Toggle::make('is_visible_to_principal')
                                ->label('Terlihat oleh Kepsek')
                                ->default(true),
                        ]),

                    Forms\Components\Section::make('Lampiran Dokumen')
                        ->schema([
                            Forms\Components\Repeater::make('attachments')
                                ->label('File Lampiran')
                                ->relationship('attachments')
                                ->schema([
                                    Forms\Components\FileUpload::make('file_path')
                                        ->label('Pilih File')
                                        ->disk('local') // DISK PRIVATE
                                        ->directory('bk_attachments')
                                        ->visibility('private') // VISIBILITAS PRIVATE
                                        ->preserveFilenames()
                                        ->downloadable() // Natively handles private file downloads via Filament
                                        ->openable()
                                        ->required()
                                        ->storeFileNamesIn('file_type'), // Kita manfaatkan kolom file_type untuk nama asli
                                ])
                                ->addActionLabel('Tambah Lampiran')
                                ->defaultItems(0),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => clone $query->with([
                'student.user',
                'counselor'
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('session_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Siswa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pribadi' => 'warning',
                        'sosial' => 'info',
                        'belajar' => 'success',
                        'karir' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('session_type')
                    ->label('Tipe Sesi')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'individual' => 'Individu',
                        'group' => 'Kelompok',
                        'home_visit' => 'Kunjungan Rumah',
                        'online' => 'Daring',
                        default => 'Lainnya',
                    }),
                Tables\Columns\TextColumn::make('topic')
                    ->label('Topik')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('counselor.name')
                    ->label('Konselor')
                    ->visible(fn () => auth()->user()->hasAnyRole(['super_admin', 'admin', 'headmaster']))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'pribadi' => 'Pribadi',
                        'sosial' => 'Sosial',
                        'belajar' => 'Belajar',
                        'karir' => 'Karir',
                        'lainnya' => 'Lainnya',
                    ]),
                Tables\Filters\SelectFilter::make('session_type')
                    ->label('Tipe Sesi')
                    ->options([
                        'individual' => 'Individu',
                        'group' => 'Kelompok',
                        'home_visit' => 'Kunjungan Rumah',
                        'online' => 'Daring',
                        'other' => 'Lainnya',
                    ]),
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        $user = auth()->user();
        // Jika bukan admin/kepsek, hanya lihat rekam yang dibuat sendiri
        if ($user && !$user->hasAnyRole(['super_admin', 'admin', 'headmaster'])) {
            $query->where('counselor_id', $user->id);
        }

        return $query;
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
            'index' => Pages\ListBkCounselingRecords::route('/'),
            'create' => Pages\CreateBkCounselingRecord::route('/create'),
            'edit' => Pages\EditBkCounselingRecord::route('/{record}/edit'),
        ];
    }
}
