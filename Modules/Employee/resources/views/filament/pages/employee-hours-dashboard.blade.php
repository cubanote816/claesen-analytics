<x-filament-panels::page>
    {{-- Period filter --}}
    <x-filament::section class="mb-6">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    {{ app()->getLocale() === 'nl' ? 'Periode' : 'Period' }}
                </label>
                <select wire:model.live="periodPreset"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-3 py-1.5 focus:ring-2 focus:ring-primary-500">
                    <option value="q1">Q1</option>
                    <option value="q2">Q2</option>
                    <option value="q3">Q3</option>
                    <option value="q4">Q4</option>
                    <option value="h1">{{ app()->getLocale() === 'nl' ? 'S1 (Jan-Jun)' : 'H1 (Jan-Jun)' }}</option>
                    <option value="h2">{{ app()->getLocale() === 'nl' ? 'S2 (Jul-Dec)' : 'H2 (Jul-Dec)' }}</option>
                    <option value="year">{{ app()->getLocale() === 'nl' ? 'Volledig jaar' : 'Full year' }}</option>
                    <option value="custom">{{ app()->getLocale() === 'nl' ? 'Aangepast' : 'Custom' }}</option>
                </select>
            </div>

            @if($periodPreset !== 'custom')
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                        {{ app()->getLocale() === 'nl' ? 'Jaar' : 'Year' }}
                    </label>
                    <select wire:model="periodYear"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-3 py-1.5 focus:ring-2 focus:ring-primary-500">
                        @foreach(range(now()->year, now()->year - 4, -1) as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                        {{ app()->getLocale() === 'nl' ? 'Van' : 'From' }}
                    </label>
                    <input type="date" wire:model="customStartDate"
                           class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-2 py-1.5">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                        {{ app()->getLocale() === 'nl' ? 'Tot' : 'To' }}
                    </label>
                    <input type="date" wire:model="customEndDate"
                           class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-2 py-1.5">
                </div>
            @endif

            <x-filament::button wire:click="applyFilter" wire:loading.attr="disabled" size="sm">
                <span wire:loading.remove wire:target="applyFilter">
                    {{ app()->getLocale() === 'nl' ? 'Filteren' : 'Filter' }}
                </span>
                <span wire:loading wire:target="applyFilter">...</span>
            </x-filament::button>
        </div>
    </x-filament::section>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-filament::section class="col-span-1">
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                    {{ number_format($summary['total_hours'] ?? 0, 0, ',', '.') }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ app()->getLocale() === 'nl' ? 'Totale uren' : 'Total hours' }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section class="col-span-1">
            <div class="text-center">
                <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                    {{ number_format($summary['total_approved_hours'] ?? 0, 0, ',', '.') }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ app()->getLocale() === 'nl' ? 'Goedgekeurde uren' : 'Approved hours' }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section class="col-span-1">
            <div class="text-center">
                <div class="text-2xl font-bold text-warning-600 dark:text-warning-400">
                    {{ $summary['total_employees'] ?? 0 }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ app()->getLocale() === 'nl' ? 'Actieve medewerkers' : 'Active employees' }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section class="col-span-1">
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-600 dark:text-gray-300">
                    {{ number_format($summary['average_hours_per_employee'] ?? 0, 1, ',', '.') }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ app()->getLocale() === 'nl' ? 'Gem. uren/medewerker' : 'Avg hours/employee' }}
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Monthly trend chart --}}
    <x-filament::section>
        <x-slot name="heading">
            @php $monthCount = count($chartLabels); @endphp
            {{ app()->getLocale() === 'nl' ? "Maandelijkse Uren Trend ({$monthCount} maanden)" : "Monthly Hours Trend ({$monthCount} months)" }}
        </x-slot>

        <div
            wire:ignore
            x-data="{
                chart: null,
                labels: [],
                hoursData: [],
                ensureChartJs() {
                    return new Promise((resolve) => {
                        if (window.Chart) { resolve(); return; }
                        let script = document.getElementById('cafca-chartjs-cdn');
                        if (!script) {
                            script = document.createElement('script');
                            script.id = 'cafca-chartjs-cdn';
                            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4';
                            document.head.appendChild(script);
                        }
                        script.addEventListener('load', () => resolve());
                    });
                },
                render() {
                    this.ensureChartJs().then(() => this.renderChart());
                },
                renderChart() {
                    if (this.chart) { this.chart.destroy(); this.chart = null; }
                    const canvas = this.$el.querySelector('#hours-trend-chart');
                    if (!canvas || !window.Chart) return;
                    this.chart = new window.Chart(canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: this.labels,
                            datasets: [{
                                label: '{{ app()->getLocale() === 'nl' ? 'Uren' : 'Hours' }}',
                                data: this.hoursData,
                                backgroundColor: 'rgba(0,174,239,0.6)',
                                borderColor: '#00aeef',
                                borderWidth: 2,
                                borderRadius: 5,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: 'rgba(148,163,184,0.1)' },
                                    ticks: { color: '#94a3b8' }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#94a3b8', maxRotation: 45 }
                                }
                            }
                        }
                    });
                }
            }"
            x-init="$nextTick(() => render())"
            x-on:hours-chart-updated.window="
                labels = $event.detail.labels;
                hoursData = $event.detail.hoursData;
                $nextTick(() => render());
            "
            class="h-72"
        >
            <canvas id="hours-trend-chart" style="width:100%;height:100%;"></canvas>
        </div>
    </x-filament::section>

    {{-- Rankings filter + table --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">
            {{ app()->getLocale() === 'nl' ? 'Medewerkers' : 'Employees' }}
        </x-slot>

        {{-- Rankings table --}}
        @if(empty($rankings))
            <p class="text-sm text-gray-500 dark:text-gray-400 italic py-4">
                {{ app()->getLocale() === 'nl' ? 'Geen gegevens gevonden voor de geselecteerde periode.' : 'No data found for the selected period.' }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 w-8">#</th>
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400">
                                {{ app()->getLocale() === 'nl' ? 'Medewerker' : 'Employee' }}
                            </th>
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">Laden</th>
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">Werf</th>
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">
                                {{ app()->getLocale() === 'nl' ? 'Transport' : 'Transport' }}
                            </th>
                            <th class="pb-2 font-medium text-gray-600 dark:text-gray-400 text-right">
                                {{ app()->getLocale() === 'nl' ? 'Totaal' : 'Total' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rankings as $i => $employee)
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="py-2 pr-4">
                                    @if($i === 0)
                                        <span class="text-yellow-500 font-bold">1</span>
                                    @elseif($i === 1)
                                        <span class="text-gray-400 font-bold">2</span>
                                    @elseif($i === 2)
                                        <span class="text-amber-600 font-bold">3</span>
                                    @else
                                        <span class="text-gray-500">{{ $i + 1 }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">
                                    <a wire:navigate href="{{ \Modules\Employee\Filament\Pages\EmployeeMonthStats::getUrl(['employee_id' => $employee['id'], 'month' => now()->format('Y-m')]) }}"
                                       class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                                        {{ $employee['name'] }}
                                    </a>
                                </td>
                                <td class="py-2 pr-4 text-right text-gray-700 dark:text-gray-300">
                                    {{ number_format($employee['labor_hours']['laden_hours'], 1) }}h
                                </td>
                                <td class="py-2 pr-4 text-right text-gray-700 dark:text-gray-300">
                                    {{ number_format($employee['labor_hours']['werf_hours'], 1) }}h
                                </td>
                                <td class="py-2 pr-4 text-right text-gray-700 dark:text-gray-300">
                                    {{ number_format($employee['labor_hours']['transport_hours'], 1) }}h
                                </td>
                                <td class="py-2 text-right font-semibold text-primary-600 dark:text-primary-400">
                                    {{ number_format($employee['total_hours'], 1) }}h
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
