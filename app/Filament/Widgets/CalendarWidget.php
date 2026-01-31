<?php

namespace App\Filament\Widgets;

use App\Models\SchoolEvent;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Actions\DeleteAction;

class CalendarWidget extends FullCalendarWidget
{
    protected static ?int $sort = 2; // <--- Tambahkan ini (Urutan 2)

    // Opsional: Pastikan kalender memanjang penuh
    protected int|string|array $columnSpan = 'full';
    // Mengambil data event dari database untuk ditampilkan di kalender
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
                    'color' => $event->color, // Warna event
                    'allDay' => $event->is_all_day,
                ]
            )
            ->all();
    }

    // Form saat Admin mengklik tanggal (Tambah Event Baru)
    public function getFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Nama Kegiatan / Libur')
                ->required(),
            \Filament\Forms\Components\Select::make('color')
                ->label('Jenis Agenda')
                ->options([
                    '#ef4444' => 'Libur Sekolah (Merah)',  // Merah
                    '#3b82f6' => 'Kegiatan Penting (Biru)', // Biru
                    '#22c55e' => 'Rapat Guru (Hijau)',      // Hijau
                    '#eab308' => 'Pengingat (Kuning)',      // Kuning
                ])
                ->required()
                ->default('#3b82f6') // Default Biru
                ->selectablePlaceholder(false), // Paksa pilih salah satu

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

    // Tombol "Create" di pojok kanan atas kalender
    protected function headerActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kegiatan')
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

    // Aksi saat event diklik (Edit & Hapus)
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
            'locale' => 'id', // Set Bahasa Indonesia
            'firstDay' => 1,  // Mulai hari Senin
            'headerToolbar' => [
                'left' => 'dayGridMonth,timeGridWeek,listWeek',
                'center' => 'title',
                'right' => 'prev,next today',
            ],
        ];
    }

}