<x-filament-panels::page>
    {{-- ═══════════════════════════════════════════════════════════════
         Halaman Kuesioner BK — Tampilan Siswa & Wali Siswa
         Menampilkan daftar kuesioner, status pengerjaan, dan hasil evaluasi.
         ═══════════════════════════════════════════════════════════════ --}}

    @if($isGuardianView)
        <div class="bg-blue-50 dark:bg-blue-500/10 rounded-xl p-4 mb-6 flex items-center gap-3 ring-1 ring-blue-200 dark:ring-blue-500/20">
            <x-heroicon-o-eye class="w-6 h-6 text-blue-500 flex-shrink-0" />
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <span class="font-semibold">Mode Wali Siswa:</span> Anda melihat kuesioner BK anak Anda. Pengisian hanya dapat dilakukan oleh siswa yang bersangkutan.
            </p>
        </div>
    @endif

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
                    $timeStatus    = $page->getTimeStatus($q);
                    $hasResponded  = $q->has_responded;
                    $hasEvaluated  = $q->evaluated_at !== null;
                    $isDisabled    = $hasResponded || $timeStatus !== 'open' || $isGuardianView;
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
                            @if($hasResponded && $hasEvaluated)
                                <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400 whitespace-nowrap">
                                    <x-heroicon-m-check-badge class="w-4 h-4" />
                                    Sudah Dievaluasi
                                </span>
                            @elseif($hasResponded)
                                <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 dark:bg-sky-500/10 dark:text-sky-400 whitespace-nowrap">
                                    <x-heroicon-m-clock class="w-4 h-4" />
                                    Menunggu Evaluasi
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
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-question-mark-circle class="w-4 h-4 text-gray-400" />
                                <span>{{ $q->questions->count() }} Pertanyaan</span>
                            </div>

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

                    {{-- Card Footer: Action Buttons --}}
                    <div class="px-6 py-4 bg-gray-50/50 dark:bg-gray-800/20 border-t border-gray-100 dark:border-gray-800 space-y-2">
                        {{-- Tombol Lihat Hasil (jika sudah dievaluasi) --}}
                        @if($hasResponded && $hasEvaluated)
                            <button
                                type="button"
                                wire:click="mountAction('viewResult', { questionnaire_id: {{ $q->id }} })"
                                style="background-color: #059669; color: #ffffff;"
                                class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg text-sm font-medium transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2"
                            >
                                <x-heroicon-m-chart-bar-square class="w-5 h-5" />
                                Lihat Hasil
                            </button>
                        @elseif($hasResponded && !$hasEvaluated)
                            {{-- Sudah dikerjakan, menunggu evaluasi --}}
                            <span
                                style="background-color: #e0f2fe; color: #0369a1;"
                                class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg text-sm font-medium cursor-not-allowed"
                            >
                                <x-heroicon-m-clock class="w-5 h-5" />
                                Menunggu Evaluasi Guru BK
                            </span>
                        @elseif($isGuardianView)
                            {{-- Wali siswa: tidak bisa mengisi --}}
                            <span class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg text-sm font-medium cursor-not-allowed bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                <x-heroicon-m-eye class="w-5 h-5" />
                                Hanya Siswa yang Dapat Mengisi
                            </span>
                        @elseif($timeStatus === 'not_started')
                            <span
                                style="background-color: #fef3c7; color: #b45309;"
                                class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg text-sm font-medium cursor-not-allowed"
                            >
                                <x-heroicon-m-clock class="w-5 h-5" />
                                Belum Dibuka
                            </span>
                        @elseif($timeStatus === 'closed')
                            <span
                                style="background-color: #fee2e2; color: #b91c1c;"
                                class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg text-sm font-medium cursor-not-allowed"
                            >
                                <x-heroicon-m-x-circle class="w-5 h-5" />
                                Sudah Ditutup
                            </span>
                        @else
                            {{-- Tombol Kerjakan --}}
                            <button
                                type="button"
                                wire:click="mountAction('fillQuestionnaire', { questionnaire_id: {{ $q->id }} })"
                                style="background-color: #2563eb; color: #ffffff;"
                                class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-lg text-sm font-medium transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2"
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
