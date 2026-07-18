<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubjectResource\Pages;
use App\Models\Subject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static ?string $navigationGroup = 'Akademik';
    protected static ?string $navigationLabel = 'Mata Pelajaran';
    protected static ?string $modelLabel = 'Mata Pelajaran';
    protected static ?string $pluralModelLabel = 'Mata Pelajaran';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Mata Pelajaran')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Mata Pelajaran')
                                    ->placeholder('Contoh: Matematika, Bahasa Indonesia')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('code')
                                    ->label('Kode Mapel')
                                    ->placeholder('Contoh: MTK, IND, IPA')
                                    ->required()
                                    // PERBAIKAN 1: Pastikan ignoreRecord ditulis seperti ini agar aman
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(10)
                                    // PERBAIKAN 2: Ganti upperCase() (yang bikin error) dengan ini:
                                    // Mengubah input jadi huruf besar saat disimpan ke database
                                    ->dehydrateStateUsing(fn(string $state): string => strtoupper($state))
                                    // Opsional: Biar kelihatan huruf besar saat ngetik (Visual CSS saja)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                Forms\Components\Select::make('type')
                                    ->label('Tipe Mata Pelajaran')
                                    ->options([
                                        'mandatory' => 'Wajib (Mandatory) — Otomatis semua siswa',
                                        'kokurikuler' => 'Kokurikuler / P5 — Otomatis semua siswa',
                                        'elective' => 'Pilihan (Elective) — Siswa didaftarkan manual',
                                        'extracurricular' => 'Ekstrakurikuler — Siswa didaftarkan manual',
                                    ])
                                    ->default('mandatory')
                                    ->required()
                                    ->native(false)
                                    ->helperText(
                                        'Wajib & Kokurikuler: otomatis masuk rapor semua siswa. ' .
                                        'Pilihan & Ekskul: hanya muncul di rapor siswa yang terdaftar.'
                                    )
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi (Opsional)')
                            ->placeholder('Keterangan tambahan mengenai mata pelajaran ini.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Mata Pelajaran')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'mandatory' => 'primary',
                        'kokurikuler' => 'success',
                        'elective' => 'warning',
                        'extracurricular' => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'mandatory' => 'Wajib',
                        'kokurikuler' => 'Kokurikuler',
                        'elective' => 'Pilihan',
                        'extracurricular' => 'Ekskul',
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // PROTEKSI LONGITUDINAL (Audit 3.3): sembunyikan Hapus jika mapel ini
                // sudah dipakai di SK Mengajar (menyimpan nilai/rapor).
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Subject $record): bool => self::hasHistory($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $blocked = $records->filter(fn (Subject $r) => self::hasHistory($r));

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->title('Penghapusan dibatalkan')
                                    ->body($blocked->count() . ' mata pelajaran sudah dipakai di SK Mengajar dan tidak dapat dihapus untuk menjaga data longitudinal.')
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }

    /**
     * Apakah mapel ini sudah dipakai di SK Mengajar (punya nilai/rapor turunan)?
     */
    protected static function hasHistory(Subject $record): bool
    {
        return $record->teachingAssignments()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubjects::route('/'),
            'create' => Pages\CreateSubject::route('/create'),
            'edit' => Pages\EditSubject::route('/{record}/edit'),
        ];
    }
}