<x-filament-panels::page>
    {{-- ═══════════════════════════════════════════════════════════════
         Halaman Hasil Asesmen BK — Tampilan Guru / Wali Kelas
         Menampilkan riwayat evaluasi kuesioner BK siswa per kelas.
         ═══════════════════════════════════════════════════════════════ --}}

    <div class="mb-6">
        {{ $this->form }}
    </div>

    @if(!$hasClassroom)
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-8 text-center">
            <x-heroicon-o-building-library class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <h2 class="text-xl font-medium text-gray-900 dark:text-gray-100">Pilih Kelas</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Pilih kelas di atas untuk melihat riwayat asesmen BK siswa.</p>
        </div>
    @elseif($responses->isEmpty())
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-8 text-center">
            <x-heroicon-o-clipboard-document-list class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <h2 class="text-xl font-medium text-gray-900 dark:text-gray-100">Belum Ada Data Evaluasi</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Belum ada evaluasi kuesioner BK yang selesai untuk kelas ini.</p>
        </div>
    @else
        {{-- Tabel Hasil Evaluasi --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/20">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-chart-bar-square class="w-5 h-5 text-primary-500" />
                    Riwayat Asesmen Kognitif BK
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Menampilkan {{ $responses->count() }} evaluasi yang telah selesai.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/30 dark:bg-gray-800/10">
                            <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Siswa</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Kuesioner</th>
                            <th class="px-6 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Skor</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Umpan Balik</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Rekomendasi</th>
                            <th class="px-6 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Tanggal Evaluasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($responses as $response)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ $response->student?->user?->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                    {{ $response->questionnaire?->title ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($response->score !== null)
                                        @php
                                            $scoreColor = $response->score >= 75 ? 'text-emerald-600 dark:text-emerald-400' :
                                                         ($response->score >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400');
                                        @endphp
                                        <span class="font-bold text-lg {{ $scoreColor }}">{{ number_format($response->score, 0) }}</span>
                                        <span class="text-xs text-gray-400">/100</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300 max-w-xs">
                                    <p class="line-clamp-2" title="{{ $response->feedback }}">{{ $response->feedback ?? '—' }}</p>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300 max-w-xs">
                                    <p class="line-clamp-2" title="{{ $response->recommendation }}">{{ $response->recommendation ?? '—' }}</p>
                                </td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $response->evaluated_at?->format('d M Y H:i') ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
