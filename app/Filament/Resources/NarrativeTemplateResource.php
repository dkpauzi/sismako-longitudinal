<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NarrativeTemplateResource\Pages;
use App\Models\NarrativeTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resource untuk Admin mengelola 5 template default narasi rapor (A-E).
 *
 * Template menggunakan placeholder [TP] yang akan diganti dengan nama
 * Tujuan Pembelajaran saat narasi di-generate oleh DescriptionGeneratorService.
 *
 * PENTING: Resource ini HANYA menampilkan template default admin (is_default = true).
 * Template override guru dikelola via NarrativeTemplatesRelationManager di TeachingAssignmentResource.
 */
class NarrativeTemplateResource extends Resource
{
    protected static ?string $model = NarrativeTemplate::class;

    protected static ?string $navigationLabel = 'Template Narasi Rapor';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Template Narasi';
    protected static ?string $pluralModelLabel = 'Template Narasi Rapor';

    /**
     * Hanya tampilkan template admin default (is_default = true).
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_default', true)
            ->whereNull('teaching_assignment_id');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Edit Template Narasi')
                    ->description('Gunakan placeholder [TP] untuk menandai posisi Tujuan Pembelajaran (TP) yang akan diisi otomatis.')
                    ->schema([
                        Forms\Components\Select::make('grade_letter')
                            ->label('Grade Internal')
                            ->options([
                                'A' => 'A — Sangat Memuaskan',
                                'B' => 'B — Memuaskan',
                                'C' => 'C — Cukup (mulai dari KKTP)',
                                'D' => 'D — Kurang (di bawah KKTP)',
                                'E' => 'E — Sangat Kurang',
                            ])
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\Textarea::make('template_text')
                            ->label('Template Kalimat')
                            ->helperText('Gunakan [TP] sebagai placeholder. Contoh: "Ananda menunjukkan penguasaan yang sangat baik dalam materi [TP]."')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('preview')
                            ->label('Pratinjau')
                            ->content(function (Forms\Get $get): string {
                                $template = $get('template_text') ?? '';
                                $preview = str_replace('[TP]', 'aljabar dan geometri', $template);
                                return $preview ?: 'Belum ada template...';
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('grade_letter')
                    ->label('Grade')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'A' => 'success',
                        'B' => 'info',
                        'C' => 'warning',
                        'D' => 'danger',
                        'E' => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('template_text')
                    ->label('Template Kalimat')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
            ])
            ->defaultSort('grade_letter')
            ->paginated(false)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit Template'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNarrativeTemplates::route('/'),
            'edit' => Pages\EditNarrativeTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * Nonaktifkan tombol "Buat Template" karena 5 default sudah di-seed otomatis.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
