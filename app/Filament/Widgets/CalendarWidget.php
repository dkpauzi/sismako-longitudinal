<?php

namespace App\Filament\Widgets;

use App\Models\SchoolEvent;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model; // <--- Import ini PENTING
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Actions\DeleteAction;

class CalendarWidget extends FullCalendarWidget
{
    // --- PERBAIKAN DISINI ---
    // Tipe datanya harus lengkap: Model|string|null
    public Model|string|null $model = SchoolEvent::class;

    protected static ?int $sort = 2;

    public function fetchEvents(array $fetchInfo): array
    {
        return SchoolEvent::query()
            ->where('start', '>=', $fetchInfo['start'])
            ->where('end', '<=', $fetchInfo['end'])
            ->get()
            ->map(
                fn(SchoolEvent $event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start' => $event->start,
                    'end' => $event->end,
                    'color' => $event->color,
                    'allDay' => $event->is_all_day,
                ]
            )
            ->all();
    }

    public function getFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Nama Kegiatan / Libur')
                ->required(),

            Select::make('color')
                ->label('Jenis Agenda')
                ->options([
                    '#ef4444' => 'Libur Sekolah (Merah)',
                    '#3b82f6' => 'Kegiatan Penting (Biru)',
                    '#22c55e' => 'Rapat Guru (Hijau)',
                    '#eab308' => 'Pengingat (Kuning)',
                ])
                ->required()
                ->default('#3b82f6')
                ->allowHtml(false),

            Grid::make()
                ->schema([
                    DateTimePicker::make('start')
                        ->label('Mulai')
                        ->required(),
                    DateTimePicker::make('end')
                        ->label('Selesai')
                        ->required(),
                ]),

            Toggle::make('is_all_day')
                ->label('Sepanjang Hari (All Day)')
                ->default(true),
        ];
    }

    protected function headerActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Agenda')
                ->mountUsing(
                    function (Form $form, array $arguments) {
                        $form->fill([
                            'start' => $arguments['start'] ?? null,
                            'end' => $arguments['end'] ?? null,
                            'is_all_day' => true
                        ]);
                    }
                )
        ];
    }

    protected function modalActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public function config(): array
    {
        return [
            'locale' => 'id',
            'firstDay' => 1,
            'headerToolbar' => [
                'left' => 'dayGridMonth,timeGridWeek,listWeek',
                'center' => 'title',
                'right' => 'prev,next today',
            ],
        ];
    }
}