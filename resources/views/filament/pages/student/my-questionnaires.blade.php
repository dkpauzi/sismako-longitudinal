<x-filament-panels::page>
    {{-- ═══════════════════════════════════════════════════════════════
         Halaman Kuesioner BK — Tampilan Siswa
         Menampilkan daftar kuesioner yang ditargetkan ke kelas siswa.
         ═══════════════════════════════════════════════════════════════ --}}

    @if($questionnaires->isEmpty())
        {{-- Empty State --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-10 text-center">
            <x-heroicon-o-clipboard-document-list class="w-14 h-14 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                Belum Ada Kuesioner
            </h2>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                Saat ini belum ada kuesioner BK yang ditargetkan ke kelasmu. Silakan periksa kembali nanti.
            </p>
        </div>
    @else
        {{-- Questionnaire Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($questionnaires as $q)
                @php
                    $timeStatus   = $page->getTimeStatus($q);
                    $hasResponded = $q->has_responded;
                    $isDisabled   = $hasResponded || $timeStatus !== 'open';

                    // Label dan warna tombol
                    if ($hasResponded) {
                        $buttonLabel = 'Sudah Dikerjakan';
                        $buttonColor = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400';
                        $buttonIcon  = 'heroicon-m-check-circle';
                    } elseif ($timeStatus === 'not_started') {
                        $buttonLabel = 'Belum Dibuka';
                        $buttonColor = 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400';
                        $buttonIcon  = 'heroicon-m-clock';
                    } elseif ($timeStatus === 'closed') {
                        $buttonLabel = 'Sudah Ditutup';
                        $buttonColor = 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400';
                        $buttonIcon  = 'heroicon-m-x-circle';
                    } else {
                        $buttonLabel = 'Kerjakan';
                        $buttonColor = '';
                        $buttonIcon  = 'heroicon-m-pencil-square';
                    }
                @endphp

                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden flex flex-col transition-all hover:shadow-md">
                    {{-- Card Header --}}
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white truncate" title="{{ $q->title }}">
                                    {{ $q->title }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Oleh: {{ $q->counselor?->name ?? '-' }}
                                </p>
                            </div>

                            {{-- Status Badge --}}
                            @if($hasResponded)
                                <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400 whitespace-nowrap">
                                    <x-heroicon-m-check-circle class="w-4 h-4" />
                                    Sudah Dikerjakan
                                </span>
                            @elseif($timeStatus === 'not_started')
                                <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-400 whitespace-nowrap">
                                    <x-heroicon-m-clock class="w-4 h-4" />
                                    Belum Dibuka
                                </span>
                            @elseif($timeStatus === 'closed')
                                <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-500/10 dark:text-red-400 whitespace-nowrap">
                                    <x-heroicon-m-x-circle class="w-4 h-4" />
                                    Sudah Ditutup
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-500/10 dark:text-blue-400 whitespace-nowrap">
                                    <x-heroicon-m-pencil-square class="w-4 h-4" />
                                    Belum Dikerjakan
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Card Body --}}
                    <div class="px-6 py-4 flex-1">
                        @if($q->description)
                            <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-3 mb-4">
                                {{ $q->description }}
                            </p>
                        @endif

                        {{-- Meta Information --}}
                        <div class="space-y-2 text-sm text-gray-500 dark:text-gray-400">
                            {{-- Jumlah Pertanyaan --}}
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-question-mark-circle class="w-4 h-4 text-gray-400" />
                                <span>{{ $q->questions->count() }} Pertanyaan</span>
                            </div>

                            {{-- Target Kelas --}}
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-building-library class="w-4 h-4 text-gray-400" />
                                <span>
                                    @foreach($q->targets as $target)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                            {{ $target->classroom?->name }}
                                        </span>
                                    @endforeach
                                </span>
                            </div>

                            {{-- Waktu --}}
                            @if($q->starts_at || $q->ends_at)
                                <div class="flex items-center gap-2">
                                    <x-heroicon-o-calendar-days class="w-4 h-4 text-gray-400" />
                                    <span>
                                        @if($q->starts_at)
                                            {{ $q->starts_at->format('d M Y H:i') }}
                                        @else
                                            —
                                        @endif
                                        <span class="mx-1 text-gray-300 dark:text-gray-600">→</span>
                                        @if($q->ends_at)
                                            {{ $q->ends_at->format('d M Y H:i') }}
                                        @else
                                            Tanpa batas
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Card Footer: Action Button --}}
                    <div class="px-6 py-4 bg-gray-50/50 dark:bg-gray-800/20 border-t border-gray-100 dark:border-gray-800">
                        @if($isDisabled)
                            {{-- Disabled State: tombol statis dengan label sesuai alasan --}}
                            <span class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg text-sm font-medium cursor-not-allowed {{ $buttonColor }}">
                                <x-dynamic-component :component="$buttonIcon" class="w-5 h-5" />
                                {{ $buttonLabel }}
                            </span>
                        @else
                            {{-- Active State: tombol Kerjakan yang membuka modal Filament Action --}}
                            <button
                                type="button"
                                wire:click="mountAction('fillQuestionnaire', { questionnaire_id: {{ $q->id }} })"
                                class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition-colors"
                            >
                                <x-heroicon-m-pencil-square class="w-5 h-5" />
                                Kerjakan
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
