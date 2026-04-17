<?php
// app/Filament/Widgets/KinerjaGuruWidget.php

namespace App\Filament\Widgets;

use App\Services\NilaiVisualisasiService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class KinerjaGuruWidget extends BaseWidget
{
    protected static ?string $heading = 'Rekap Kinerja Guru — Periode Aktif';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(['headmaster', 'super_admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        $service = app(NilaiVisualisasiService::class);
        $data    = collect($service->getKinerjaGuru());

        return $table
            ->query(
                // Menggunakan query builder dari data yang sudah ada
                \App\Models\Teacher::whereIn('id', $data->pluck('teacher_id'))
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Guru')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('jumlah_kelas')
                    ->label('Jml Kelas')
                    ->alignCenter()
                    ->getStateUsing(fn($record) =>
                        $data->firstWhere('teacher_id', $record->id)['jumlah_kelas'] ?? 0
                    ),

                Tables\Columns\TextColumn::make('persen_nilai')
                    ->label('Kelengkapan Nilai')
                    ->alignCenter()
                    ->getStateUsing(fn($record) =>
                        ($data->firstWhere('teacher_id', $record->id)['persen_nilai'] ?? 0) . '%'
                    )
                    ->color(fn($record) =>
                        match($data->firstWhere('teacher_id', $record->id)['status'] ?? '') {
                            'Lengkap'  => 'success',
                            'Sebagian' => 'warning',
                            default    => 'danger',
                        }
                    ),

                Tables\Columns\TextColumn::make('jurnal_masuk')
                    ->label('Jurnal KBM')
                    ->alignCenter()
                    ->getStateUsing(fn($record) =>
                        $data->firstWhere('teacher_id', $record->id)['jurnal_masuk'] ?? 0
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge() 
                    ->getStateUsing(fn($record) =>
                        $data->firstWhere('teacher_id', $record->id)['status'] ?? 'Belum'
                    )
                    ->color(fn(string $state): string => match ($state) {
                        'Lengkap' => 'success',
                        'Sebagian' => 'warning',
                        'Belum'  => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated(false);
    }
}