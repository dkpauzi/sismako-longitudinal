<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BkQuestionnaireResource\Pages;
use App\Models\AcademicPeriod;
use App\Models\BkQuestionnaire;
use App\Models\Classroom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BkQuestionnaireResource extends Resource
{
    protected static ?string $model = BkQuestionnaire::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Bimbingan Konseling';
    protected static ?string $modelLabel = 'Kuesioner BK';
    protected static ?string $pluralModelLabel = 'Kuesioner BK';

    public static function form(Form $form): Form
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        return $form
            ->schema([
                Forms\Components\Hidden::make('counselor_id')
                    ->default(Auth::id()),
                Forms\Components\Hidden::make('academic_period_id')
                    ->default($activePeriod?->id),

                Forms\Components\Section::make('Informasi Kuesioner')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Judul Kuesioner')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('instructions')
                            ->label('Instruksi Pengerjaan')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Terbit',
                                'closed' => 'Ditutup',
                            ])
                            ->default('draft')
                            ->required(),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Waktu Mulai')
                            ->native(false),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Waktu Selesai')
                            ->native(false),
                    ])->columns(3),

                Forms\Components\Section::make('Target Kelas')
                    ->description('Pilih kelas mana saja yang harus mengerjakan kuesioner ini pada periode aktif.')
                    ->schema([
                        Forms\Components\Repeater::make('targets')
                            ->label('Target Kelas')
                            ->relationship('targets')
                            ->schema([
                                Forms\Components\Hidden::make('academic_period_id')
                                    ->default($activePeriod?->id),
                                Forms\Components\Select::make('classroom_id')
                                    ->label('Kelas')
                                    ->options(Classroom::pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            ])
                            ->addActionLabel('Tambah Kelas Target')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Daftar Pertanyaan')
                    ->schema([
                        Forms\Components\Repeater::make('questions')
                            ->label('Pertanyaan')
                            ->relationship('questions')
                            ->orderColumn('order')
                            ->schema([
                                Forms\Components\Textarea::make('question_text')
                                    ->label('Teks Pertanyaan')
                                    ->required()
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('question_type')
                                    ->label('Tipe Jawaban')
                                    ->options([
                                        'single_choice' => 'Pilihan Tunggal (Radio)',
                                        'multiple_choice' => 'Pilihan Ganda (Checkbox)',
                                        'text' => 'Teks Bebas (Essay)',
                                        'scale' => 'Skala (Likert)',
                                    ])
                                    ->required()
                                    ->live(), // Live untuk mengatur visibilitas opsi
                                
                                Forms\Components\Repeater::make('options')
                                    ->label('Opsi Jawaban')
                                    ->relationship('options')
                                    ->orderColumn('order')
                                    ->schema([
                                        Forms\Components\TextInput::make('option_text')
                                            ->label('Teks Opsi')
                                            ->required(),
                                        Forms\Components\TextInput::make('option_code')
                                            ->label('Kode Opsi (Opsional)'),
                                        Forms\Components\TextInput::make('score_weight')
                                            ->label('Bobot Nilai')
                                            ->numeric()
                                            ->default(0.00)
                                            ->required(),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull()
                                    // Sembunyikan opsi jawaban jika tipe pertanyaan adalah teks bebas
                                    ->visible(fn (Forms\Get $get) => $get('question_type') !== 'text')
                                    ->addActionLabel('Tambah Opsi'),
                            ])
                            ->addActionLabel('Tambah Pertanyaan')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['question_text'] ?? null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'academicPeriod', 
                'targets.classroom', 
                'questions'
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'closed' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Periode')
                    ->sortable(),
                Tables\Columns\TextColumn::make('targets_count')
                    ->label('Target Kelas')
                    ->counts('targets')
                    ->badge(),
                Tables\Columns\TextColumn::make('questions_count')
                    ->label('Jml Pertanyaan')
                    ->counts('questions'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Terbit',
                        'closed' => 'Ditutup',
                    ]),
                Tables\Filters\SelectFilter::make('academic_period_id')
                    ->label('Periode')
                    ->relationship('academicPeriod', 'id')
                    ->getOptionLabelFromRecordUsing(fn (AcademicPeriod $record) => "{$record->start_year}/{$record->end_year} " . ($record->semester === 'odd' ? 'Ganjil' : 'Genap')),
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
        // Jika bukan admin/kepsek, hanya lihat data yang dibuat sendiri
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
            'index' => Pages\ListBkQuestionnaires::route('/'),
            'create' => Pages\CreateBkQuestionnaire::route('/create'),
            'edit' => Pages\EditBkQuestionnaire::route('/{record}/edit'),
        ];
    }
}
