<x-filament-panels::page>
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th scope="col" class="px-4 py-3 border-r dark:border-gray-700 whitespace-nowrap sticky left-0 bg-gray-50 dark:bg-gray-800 z-10 w-10">No</th>
                        <th scope="col" class="px-4 py-3 border-r dark:border-gray-700 whitespace-nowrap sticky left-10 bg-gray-50 dark:bg-gray-800 z-10 min-w-[200px]">Nama Siswa</th>
                        
                        {{-- Header Sumatif --}}
                        @foreach ($sumatif as $col)
                            <th scope="col" class="px-4 py-3 border-r dark:border-gray-700 text-center whitespace-nowrap" title="{{ $col->name }}">
                                <span class="text-blue-600 dark:text-blue-400">{{ $col->name }}</span>
                                <div class="text-[10px] text-gray-400 font-normal">
                                    {{ $record->grading_formula === 'weighting' ? $col->weight . '%' : 'Sumatif' }}
                                </div>
                            </th>
                        @endforeach

                        {{-- Header Formatif Poin (Booster) --}}
                        @if($record->use_formative_boost)
                            @foreach ($formatifPoin as $col)
                                <th scope="col" class="px-4 py-3 border-r dark:border-gray-700 text-center whitespace-nowrap" title="{{ $col->name }}">
                                    <span class="text-green-600 dark:text-green-400">{{ $col->name }}</span>
                                    <div class="text-[10px] text-gray-400 font-normal">Max: {{ $col->weight }} Poin</div>
                                </th>
                            @endforeach
                        @endif

                        {{-- Header Nilai Akhir (Highlight) --}}
                        <th scope="col" class="px-4 py-3 border-r dark:border-gray-700 text-center whitespace-nowrap bg-primary-50 dark:bg-primary-900/20">
                            <span class="text-primary-600 dark:text-primary-400 font-bold">NILAI AKHIR</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $index => $enrollment)
                        <tr class="bg-white border-b dark:bg-gray-900 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-4 py-3 border-r dark:border-gray-700 sticky left-0 bg-white dark:bg-gray-900 z-10">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 border-r dark:border-gray-700 sticky left-10 bg-white dark:bg-gray-900 z-10 font-medium text-gray-900 dark:text-white">
                                {{ $enrollment->student->name }}
                            </td>

                            {{-- Data Sumatif --}}
                            @foreach ($sumatif as $col)
                                @php
                                    $grade = $col->grades->where('student_id', $enrollment->student_id)->first();
                                    $score = $grade ? $grade->score : '-';
                                @endphp
                                <td class="px-4 py-3 border-r dark:border-gray-700 text-center {{ is_numeric($score) && $score < ($record->kktp ?? 75) ? 'text-danger-600 font-medium' : '' }}">
                                    {{ $score }}
                                </td>
                            @endforeach

                            {{-- Data Formatif Poin --}}
                            @if($record->use_formative_boost)
                                @foreach ($formatifPoin as $col)
                                    @php
                                        $grade = $col->grades->where('student_id', $enrollment->student_id)->first();
                                        $score = $grade ? $grade->score : 0;
                                    @endphp
                                    <td class="px-4 py-3 border-r dark:border-gray-700 text-center">
                                        @if($score > 0)
                                            <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">+{{ $score }}</span>
                                        @else
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </td>
                                @endforeach
                            @endif

                            {{-- Kalkulasi Nilai Akhir --}}
                            @php
                                $finalGrade = $record->calculateFinalGrade($enrollment->student_id);
                            @endphp
                            <td class="px-4 py-3 border-r dark:border-gray-700 text-center font-bold bg-primary-50 dark:bg-primary-900/20 {{ $finalGrade < ($record->kktp ?? 75) ? 'text-danger-600' : 'text-primary-600 dark:text-primary-400' }}">
                                {{ $finalGrade > 0 ? $finalGrade : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="100%" class="px-4 py-8 text-center text-gray-500">
                                Belum ada siswa yang terdaftar di kelas ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
    </div>
</x-filament-panels::page>