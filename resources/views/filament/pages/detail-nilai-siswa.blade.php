{{-- resources/views/filament/pages/detail-nilai-siswa.blade.php --}}

<x-filament-panels::page>
    {{-- Form Pemilihan Siswa --}}
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if($student_id && !empty($chartData))
        {{-- Info Siswa --}}
        @php $student = $this->getStudentInfo() @endphp
        @if($student)
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $student->name }}
                        </h2>
                        <p class="text-sm text-gray-500">
                            NISN: {{ $student->nisn ?? '-' }}
                        </p>
                    </div>
                    <x-filament::badge color="primary">
                        {{ count($subjectList) }} Mata Pelajaran
                    </x-filament::badge>
                </div>
            </x-filament::section>
        @endif

        {{-- Grafik Longitudinal --}}
        <x-filament::section heading="Grafik Nilai Per Semester">
            <div
                x-data="{
                    chart: null,
                    chartData: {{ json_encode($chartData) }},

                    init() {
                        this.renderChart();
                    },

                    renderChart() {
                        if (this.chart) {
                            this.chart.destroy();
                        }

                        const ctx = this.$refs.canvas.getContext('2d');
                        this.chart = new Chart(ctx, {
                            type: 'line',
                            data: this.chartData,
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: {
                                    mode: 'index',
                                    intersect: false,
                                },
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: { usePointStyle: true, padding: 20 }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y ?? 'N/A'}`
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        min: 0,
                                        max: 100,
                                        ticks: { stepSize: 10 },
                                        grid: { color: '#E5E7EB' },
                                        title: {
                                            display: true,
                                            text: 'Nilai'
                                        }
                                    },
                                    x: {
                                        grid: { display: false },
                                        title: {
                                            display: true,
                                            text: 'Periode'
                                        }
                                    }
                                }
                            }
                        });
                    }
                }"
                x-on:livewire:navigated.window="renderChart()"
                wire:key="chart-{{ $student_id }}-{{ $selectedSubject }}"
                style="height: 420px; position: relative;"
            >
                <canvas x-ref="canvas"></canvas>
            </div>
        </x-filament::section>

        {{-- Tabel Ringkasan Nilai --}}
        <x-filament::section heading="Tabel Nilai Per Periode">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 border-b">
                                Mata Pelajaran
                            </th>
                            @foreach(array_keys($chartData['labels'] ? array_flip($chartData['labels']) : []) as $period)
                                <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200 border-b whitespace-nowrap">
                                    {{ $chartData['labels'][array_search($period, array_keys(array_flip($chartData['labels'])))] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($chartData['datasets'] as $idx => $dataset)
                            <tr class="{{ $idx % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800' }}">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white border-b">
                                    <span
                                        class="inline-block w-3 h-3 rounded-full mr-2"
                                        style="background-color: {{ $dataset['borderColor'] }}"
                                    ></span>
                                    {{ $dataset['label'] }}
                                </td>
                                @foreach($dataset['data'] as $nilai)
                                    <td class="px-4 py-2 text-center border-b">
                                        @if($nilai !== null)
                                            <span class="font-semibold
                                                {{ $nilai >= 90 ? 'text-green-600' :
                                                   ($nilai >= 75 ? 'text-blue-600' :
                                                   ($nilai >= 60 ? 'text-yellow-600' : 'text-red-600')) }}">
                                                {{ $nilai }}
                                            </span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

    @elseif($student_id && empty($chartData))
        <x-filament::section>
            <div class="text-center py-12 text-gray-400">
                <x-heroicon-o-chart-bar class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p>Belum ada data nilai untuk siswa ini.</p>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>