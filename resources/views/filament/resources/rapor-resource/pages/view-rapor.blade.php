{{-- resources/views/filament/resources/rapor-resource/pages/view-rapor.blade.php --}}

<x-filament-panels::page>

    {{-- ══════════════════════════════════════════════════════════════
        PROGRESS GENERATE NARASI: tampil saat proses chunking berjalan.
        Tiap batch memicu round-trip Livewire (timer PHP fresh) sampai selesai.
    ══════════════════════════════════════════════════════════════ --}}
    @if ($isGeneratingNarasi)
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 mb-6">
            <div class="mb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Membuat deskripsi rapor...
                    </span>
                </div>
                <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                    {{ $narasiProcessed }} / {{ $narasiTotal }} siswa
                </span>
            </div>
            <div class="h-4 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    class="h-4 rounded-full bg-gradient-to-r from-primary-500 to-primary-400 transition-all duration-500 ease-out"
                    style="width: {{ $this->narasiProgressPercentage }}%"
                ></div>
            </div>
            <p class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">
                {{ $this->narasiProgressPercentage }}% — Jangan tutup halaman ini.
            </p>
        </div>
    @endif

    {{-- Header Info Kelas --}}
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-6 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs mb-1">Kelas</p>
                <p class="font-bold text-gray-900 dark:text-white text-lg">
                    {{ $homeroom->classroom->name }}
                </p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs mb-1">Tahun Ajaran</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ $homeroom->academicPeriod->name }}
                </p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs mb-1">Wali Kelas</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ $homeroom->teacher->name }}
                </p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs mb-1">Jumlah Siswa</p>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ $enrollments->count() }} siswa
                </p>
            </div>
        </div>
    </div>

    {{-- Tampilan Progress Guru Mapel --}}
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Status Input Nilai Guru Mata Pelajaran
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Pantau progres pengisian nilai oleh guru mata pelajaran di kelas ini.
            </p>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($progressGuruMapel as $progress)
                <div class="space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-sm text-gray-900 dark:text-white lowercase capitalize">
                                {{ $progress['subject'] }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $progress['teacher'] }}
                            </p>
                        </div>
                        <span class="inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold
                            {{ $progress['percentage'] >= 100 ? 'bg-success-100 text-success-800 dark:bg-success-800/20 dark:text-success-400' : ($progress['percentage'] >= 50 ? 'bg-warning-100 text-warning-800 dark:bg-warning-800/20 dark:text-warning-400' : 'bg-danger-100 text-danger-800 dark:bg-danger-800/20 dark:text-danger-400') }}">
                            {{ $progress['percentage'] }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-800 overflow-hidden">
                        <div class="h-2 rounded-full {{ $progress['percentage'] >= 100 ? 'bg-success-500' : ($progress['percentage'] >= 50 ? 'bg-warning-500' : 'bg-danger-500') }}" 
                             style="width: {{ $progress['percentage'] }}%">
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-400 text-right">
                        {{ $progress['graded_count'] }} / {{ $progress['total_students'] }} Siswa Dinilai
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Tabel Rekap Nilai --}}
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden mb-6">

        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Rekap Nilai Akhir
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Nilai otomatis dihitung dari asesmen yang telah diinput guru.
                Kunci nilai sebelum mencetak rapor.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">

                {{-- Header Tabel --}}
                <thead class="text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-800">
                    <tr>
                        {{-- Kolom Tetap --}}
                        <th class="px-4 py-3 border-r dark:border-gray-700 sticky left-0 bg-gray-50 dark:bg-gray-800 z-10 w-8">
                            No
                        </th>
                        <th class="px-4 py-3 border-r dark:border-gray-700 sticky left-8 bg-gray-50 dark:bg-gray-800 z-10 min-w-[180px]">
                            Nama Siswa
                        </th>

                        {{-- Kolom Per Mapel Akademik --}}
                        @foreach($akademikAssignments as $ta)
                            <th class="px-3 py-3 border-r dark:border-gray-700 text-center min-w-[80px]">
                                <span class="text-blue-600 dark:text-blue-400 block">
                                    {{ $ta->subject->code }}
                                </span>
                                <span class="text-[10px] text-gray-400 font-normal normal-case block">
                                    {{ $ta->kktp ?? 75 }}
                                </span>
                            </th>
                        @endforeach

                        {{-- Kolom Absensi --}}
                        <th class="px-3 py-3 border-r dark:border-gray-700 text-center min-w-[60px] bg-amber-50 dark:bg-amber-900/20">
                            <span class="text-amber-600 dark:text-amber-400 block">S</span>
                            <span class="text-[10px] text-gray-400 font-normal normal-case">Sakit</span>
                        </th>
                        <th class="px-3 py-3 border-r dark:border-gray-700 text-center min-w-[60px] bg-amber-50 dark:bg-amber-900/20">
                            <span class="text-amber-600 dark:text-amber-400 block">I</span>
                            <span class="text-[10px] text-gray-400 font-normal normal-case">Izin</span>
                        </th>
                        <th class="px-3 py-3 border-r dark:border-gray-700 text-center min-w-[60px] bg-amber-50 dark:bg-amber-900/20">
                            <span class="text-red-600 dark:text-red-400 block">A</span>
                            <span class="text-[10px] text-gray-400 font-normal normal-case">Alpha</span>
                        </th>
                        
                        {{-- Kolom Catatan Wali Kelas --}}
                        <th class="px-4 py-3 border-r dark:border-gray-700 text-left min-w-[200px]">
                            Catatan Wali Kelas
                        </th>
                    </tr>
                </thead>

                {{-- Body Tabel --}}
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($enrollments as $enrollment)
                        @php
                            $student       = $enrollment->student;
                            $studentGrades = $finalGrades[$student->id] ?? collect();
                            $studentReport = $studentReports[$student->id] ?? null;

                            // Gunakan manual override jika ada, jika tidak, hitung otomatis
                            if ($studentReport) {
                                $totalSakit = $studentReport->sick_days;
                                $totalIzin  = $studentReport->excused_days;
                                $totalAlpha = $studentReport->unexcused_days;
                            } else {
                                $studentAbsen  = $attendanceSummaries[$student->id] ?? collect();
                                $totalSakit = $studentAbsen->sum('sick');
                                $totalIzin  = $studentAbsen->sum('permit');
                                $totalAlpha = $studentAbsen->sum('alpha');
                            }
                        @endphp

                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">

                            {{-- Kolom Tetap --}}
                            <td class="px-4 py-3 border-r dark:border-gray-700 sticky left-0 bg-white dark:bg-gray-900 z-10 text-gray-400 text-xs">
                                {{ $loop->iteration }}
                            </td>
                            <td class="px-4 py-3 border-r dark:border-gray-700 sticky left-8 bg-white dark:bg-gray-900 z-10">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $student->name }}
                                </p>
                                <p class="text-xs text-gray-400">{{ $student->nisn }}</p>
                            </td>

                            {{-- Nilai Per Mapel --}}
                            @foreach($akademikAssignments as $ta)
                                @php
                                    $grade = $studentGrades
                                        ->where('teaching_assignment_id', $ta->id)
                                        ->first();

                                    $score     = $grade?->final_score;
                                    $label     = $grade?->grade_label;
                                    $isLocked  = $grade?->is_locked ?? false;
                                    $kktp      = $ta->kktp ?? 75;
                                    $isBelowKktp = $score !== null && $score < $kktp;
                                @endphp

                                <td class="px-3 py-3 border-r dark:border-gray-700 text-center">
                                    @if($score !== null)
                                        <span class="font-semibold text-sm {{ $isBelowKktp ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">
                                            {{ number_format($score, 0) }}
                                        </span>
                                        <span class="block text-[10px] mt-0.5 {{ $isBelowKktp ? 'text-red-400' : 'text-gray-400' }}">
                                            {{ $label }}
                                            @if($isLocked)
                                                🔒
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600 text-xs">—</span>
                                    @endif
                                </td>
                            @endforeach

                            {{-- Kolom Absensi --}}
                            <td class="px-3 py-3 border-r dark:border-gray-700 text-center bg-amber-50/50 dark:bg-amber-900/10">
                                <span class="{{ $totalSakit > 0 ? 'text-amber-600 font-semibold' : 'text-gray-300' }}">
                                    {{ $totalSakit ?: '—' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 border-r dark:border-gray-700 text-center bg-amber-50/50 dark:bg-amber-900/10">
                                <span class="{{ $totalIzin > 0 ? 'text-amber-600 font-semibold' : 'text-gray-300' }}">
                                    {{ $totalIzin ?: '—' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 border-r dark:border-gray-700 text-center bg-amber-50/50 dark:bg-amber-900/10">
                                <span class="{{ $totalAlpha > 0 ? 'text-red-600 font-bold' : 'text-gray-300' }}">
                                    {{ $totalAlpha ?: '—' }}
                                </span>
                            </td>

                            {{-- Catatan Wali Kelas --}}
                            <td class="px-4 py-3 border-r dark:border-gray-700 text-xs text-gray-600 dark:text-gray-300">
                                {{ $studentReport?->homeroom_notes ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="100%" class="px-4 py-12 text-center text-gray-400">
                                Belum ada siswa yang terdaftar di kelas ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        {{-- Keterangan Warna --}}
        <div class="px-6 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-6 text-xs text-gray-500">
            <span class="flex items-center gap-1.5">
                <span class="inline-block w-3 h-3 rounded-sm bg-red-100"></span>
                Nilai di bawah KKTP
            </span>
            <span class="flex items-center gap-1.5">
                🔒 Nilai sudah dikunci
            </span>
            <span class="flex items-center gap-1.5">
                <span class="text-gray-300">—</span>
                Belum ada nilai
            </span>
        </div>

    </div>

</x-filament-panels::page>