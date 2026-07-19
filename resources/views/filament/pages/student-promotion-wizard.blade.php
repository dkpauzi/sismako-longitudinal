<x-filament-panels::page>

    {{-- ══════════════════════════════════════════════════════════════
        PROGRESS BAR: Ditampilkan saat proses chunking berjalan.
        Menggunakan Alpine.js untuk animasi smooth pada perubahan width.
    ══════════════════════════════════════════════════════════════ --}}
    @if ($isProcessing)
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Memproses kenaikan kelas...
                    </span>
                </div>
                <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                    {{ $processedCount }} / {{ $totalCount }} siswa
                </span>
            </div>

            {{-- Progress bar container.
                 Lebar bar di-BIND reaktif ke properti Livewire lewat Alpine (x-bind:style):
                 tanpa ini, DOM-morph Livewire tidak selalu menulis ulang atribut `style`
                 antar round-trip chunk, sehingga angka % berubah tapi bar diam (Issue #5).
                 `style=""` server-render dipertahankan untuk paint awal sebelum Alpine aktif. --}}
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

    {{-- ══════════════════════════════════════════════════════════════
        ERROR MESSAGE: Ditampilkan jika ada chunk yang gagal.
    ══════════════════════════════════════════════════════════════ --}}
    @if ($errorMessage)
        <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 dark:border-danger-700 dark:bg-danger-950">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-danger-500" />
                <span class="text-sm font-medium text-danger-700 dark:text-danger-300">
                    {{ $errorMessage }}
                </span>
            </div>
            @if ($processedCount > 0)
                <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">
                    {{ $processedCount }} dari {{ $totalCount }} siswa sudah diproses sebelum error terjadi.
                </p>
            @endif
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
        WIZARD FORM: Disembunyikan saat proses sedang berjalan
        untuk mencegah user mengubah data di tengah proses.
    ══════════════════════════════════════════════════════════════ --}}
    @if (! $isProcessing)
        <x-filament-panels::form wire:submit="submit">
            {{ $this->form }}
        </x-filament-panels::form>
    @endif

</x-filament-panels::page>
