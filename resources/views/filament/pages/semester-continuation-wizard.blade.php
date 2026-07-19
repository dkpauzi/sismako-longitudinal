<x-filament-panels::page>

    {{-- Progress bar (pola sama dengan Kenaikan Kelas, Issue #5 fix) --}}
    @if ($isProcessing)
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Memproses lanjut semester...
                    </span>
                </div>
                <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                    {{ $processedCount }} / {{ $totalCount }} siswa
                </span>
            </div>

            <div class="h-4 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" x-data>
                <div
                    class="h-4 rounded-full bg-gradient-to-r from-primary-500 to-primary-400 transition-all duration-500 ease-out"
                    style="width: {{ $this->progressPercentage }}%"
                    x-bind:style="`width: ${$wire.totalCount ? Math.round($wire.processedCount / $wire.totalCount * 100) : 0}%`"
                ></div>
            </div>

            <p class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">
                {{ $this->progressPercentage }}% — Jangan tutup halaman ini.
            </p>
        </div>
    @endif

    @if ($errorMessage)
        <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 dark:border-danger-700 dark:bg-danger-950">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-danger-500" />
                <span class="text-sm font-medium text-danger-700 dark:text-danger-300">{{ $errorMessage }}</span>
            </div>
        </div>
    @endif

    @if (! $isProcessing)
        <x-filament-panels::form wire:submit="submit">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Proses Lanjut Semester
                </x-filament::button>
            </div>
        </x-filament-panels::form>
    @endif

</x-filament-panels::page>
