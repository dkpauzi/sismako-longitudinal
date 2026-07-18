<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('academic_period_id')
                    ->label('Tahun Ajaran')
                    ->options(\App\Models\AcademicPeriod::where('is_active', true)->get()->mapWithKeys(fn($p) => [$p->id => $p->name])) // Hanya tampilkan periode aktif
                    ->required()
                    // Validasi aplikasi mengikuti constraint DB:
                    // UNIQUE(student_id, academic_period_id) — 1 siswa hanya boleh
                    // terdaftar di 1 kelas per periode. Siswa di sini adalah owner record.
                    ->unique(
                        table: 'enrollments',
                        column: 'academic_period_id',
                        modifyRuleUsing: fn($rule) => $rule
                            ->where('student_id', $this->getOwnerRecord()->id),
                        ignoreRecord: true
                    )
                    ->validationMessages([
                        'unique' => 'Siswa ini sudah terdaftar di kelas lain pada periode tersebut.',
                    ]),

                Forms\Components\Select::make('classroom_id')
                    ->label('Kelas')
                    ->options(\App\Models\Classroom::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('status')
                    // Opsi WAJIB sama dengan ENUM di migrasi enrollments:
                    // active, promoted, retained, graduated, dropped.
                    ->options([
                        'active' => 'Aktif',
                        'promoted' => 'Naik Kelas',
                        'retained' => 'Tinggal Kelas',
                        'graduated' => 'Lulus',
                        'dropped' => 'Keluar',
                    ])
                    ->default('active')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('status')
            ->columns([
                Tables\Columns\TextColumn::make('academicPeriod.name')
                    ->label('Tahun Ajaran')
                    ->sortable(),

                Tables\Columns\TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    // Nilai mengikuti ENUM migrasi enrollments; default 'gray'
                    // mencegah UnhandledMatchError untuk data lama yang tak dikenal.
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'promoted' => 'info',
                        'retained' => 'warning',
                        'graduated' => 'primary',
                        'dropped' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'promoted' => 'Naik Kelas',
                        'retained' => 'Tinggal Kelas',
                        'graduated' => 'Lulus',
                        'dropped' => 'Keluar',
                        default => $state,
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Masukkan ke Kelas'), // Ganti label tombol,
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // PROTEKSI LONGITUDINAL (Audit HIGH-1): sembunyikan Hapus jika
                // enrollment ini asal promosi atau sudah punya nilai.
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (\App\Models\Enrollment $record): bool => $record->hasLongitudinalHistory()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            $blocked = $records->filter(fn (\App\Models\Enrollment $r) => $r->hasLongitudinalHistory());

                            if ($blocked->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Penghapusan dibatalkan')
                                    ->body($blocked->count() . ' pendaftaran memiliki jejak longitudinal (nilai/rantai promosi) dan tidak dapat dihapus.')
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }
                        }),
                ]),
            ]);
    }
}
