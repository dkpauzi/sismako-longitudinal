<x-filament-panels::page>
    @if(!$hasData)
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 p-8 text-center">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-gray-400 mb-4" />
            <h2 class="text-xl font-medium text-gray-900 dark:text-gray-100">Belum ada data nilai</h2>
            <p class="text-gray-500 mt-2">Anda tidak terdaftar di periode akademik yang aktif saat ini, atau data belum disiapkan oleh admin.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                <p class="text-sm text-gray-500">Siswa</p>
                <p class="font-bold text-lg text-gray-900 dark:text-white">{{ $student->name }}</p>
                <p class="text-xs text-gray-400">{{ $student->nisn }}</p>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6">
                <p class="text-sm text-gray-500">Kelas & Semester</p>
                <p class="font-bold text-lg text-gray-900 dark:text-white">{{ $classroom }}</p>
                <p class="text-xs text-gray-400">{{ $period->name }}</p>
            </div>
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 p-6 flex flex-col justify-center items-center text-center">
                <p class="text-sm text-gray-500">Total Ketidakhadiran</p>
                @php
                    $totSakit = $akademikData->sum(fn($d) => $d['attendance']?->sick ?? 0);
                    $totIzin = $akademikData->sum(fn($d) => $d['attendance']?->permit ?? 0);
                    $totAlpha = $akademikData->sum(fn($d) => $d['attendance']?->alpha ?? 0);
                @endphp
                <div class="flex gap-4 mt-2">
                    <div class="text-center">
                        <span class="block text-xl font-bold text-amber-500">{{ $totSakit }}</span>
                        <span class="text-[10px] uppercase tracking-wider text-gray-400">Sakit</span>
                    </div>
                    <div class="text-center">
                        <span class="block text-xl font-bold text-amber-500">{{ $totIzin }}</span>
                        <span class="text-[10px] uppercase tracking-wider text-gray-400">Izin</span>
                    </div>
                    <div class="text-center">
                        <span class="block text-xl font-bold text-red-500">{{ $totAlpha }}</span>
                        <span class="text-[10px] uppercase tracking-wider text-gray-400">Tanpa Keterangan</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            @foreach($akademikData as $data)
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 overflow-hidden">
                    {{-- Header Mapel --}}
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/20 flex flex-wrap gap-4 items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $data['subject'] }}</h3>
                            <p class="text-xs text-gray-500">Guru: {{ $data['teacher'] }} &nbsp;&bull;&nbsp; KKTP: {{ $data['kktp'] }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            @if($data['final_grade'] && $data['final_grade']->is_locked)
                                <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400">
                                    <x-heroicon-m-check-circle class="w-4 h-4" />
                                    Nilai Dikunci
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-500/10 dark:text-blue-400">
                                    <x-heroicon-m-clock class="w-4 h-4" />
                                    Dalam Proses
                                </span>
                            @endif

                            <div class="text-right">
                                <p class="text-xs text-gray-400">Nilai Akhir Rapor</p>
                                @if($data['final_grade'] && $data['final_grade']->final_score !== null)
                                    <p class="text-2xl font-black {{ $data['final_grade']->final_score < ($data['kktp'] ?? 75) ? 'text-red-600' : 'text-primary-600' }}">
                                        {{ number_format($data['final_grade']->final_score, 0) }}
                                        <span class="text-sm font-medium text-gray-400 ml-1">({{ $data['final_grade']->grade_label }})</span>
                                    </p>
                                @else
                                    <p class="text-2xl font-black text-gray-300">-</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100 dark:divide-gray-800">
                        {{-- Sumatif --}}
                        <div class="p-6">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-primary-500" />
                                Penilaian Sumatif
                            </h4>
                            @if(count($data['summative_scores']) > 0)
                                <div class="space-y-3">
                                    @foreach($data['summative_scores'] as $score)
                                        <div class="flex justify-between items-center text-sm">
                                            <span class="text-gray-600 dark:text-gray-400">{{ $score->assessment->title }}</span>
                                            <span class="font-medium {{ $score->score < ($data['kktp'] ?? 75) ? 'text-red-600' : 'text-gray-900 dark:text-gray-100' }}">
                                                {{ $score->score !== null ? $score->score : '-' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-400 italic">Belum ada nilai sumatif yang di-input.</p>
                            @endif
                        </div>

                        {{-- Formatif --}}
                        <div class="p-6">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <x-heroicon-o-document-text class="w-5 h-5 text-amber-500" />
                                Penilaian Formatif
                            </h4>
                            @if(count($data['formative_scores']) > 0)
                                <ul class="space-y-3">
                                    @foreach($data['formative_scores'] as $score)
                                        <li class="flex justify-between items-center text-sm">
                                            <span class="text-gray-600 dark:text-gray-400">{{ $score->assessment->title }}</span>
                                            <span class="font-medium {{ $score->score < ($data['kktp'] ?? 75) ? 'text-red-600' : 'text-gray-900 dark:text-gray-100' }}">
                                                {{ $score->score !== null ? $score->score : '-' }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-xs text-gray-400 italic">Belum ada nilai formatif yang di-input.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Deskripsi Rapor --}}
                    @if($data['final_grade'] && $data['final_grade']->is_locked && $data['final_grade']->description)
                        <div class="px-6 py-4 bg-primary-50/50 dark:bg-primary-900/10 border-t border-gray-100 dark:border-gray-800">
                            <h4 class="text-xs font-semibold text-primary-900 dark:text-primary-300 mb-1">Deskripsi Rapor</h4>
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $data['final_grade']->description }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
