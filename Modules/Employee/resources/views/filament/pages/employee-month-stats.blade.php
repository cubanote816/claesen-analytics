<x-filament-panels::page>
    @if($errorMessage)
        <x-filament::section>
            <p class="text-danger-600 dark:text-danger-400">{{ $errorMessage }}</p>
        </x-filament::section>
    @elseif($data)
        {{-- Month navigation --}}
        @php
            $currentMonth = \Carbon\Carbon::createFromFormat('Y-m', $month);
            $prevMonth    = $currentMonth->copy()->subMonth()->format('Y-m');
            $nextMonth    = $currentMonth->copy()->addMonth()->format('Y-m');
            $isNl         = app()->getLocale() === 'nl';
        @endphp

        <div class="flex items-center justify-between mb-6">
            <a wire:navigate
               href="{{ \Modules\Employee\Filament\Pages\EmployeeMonthStats::getUrl(['employee_id' => $employeeId, 'month' => $prevMonth]) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                <x-heroicon-o-chevron-left class="w-4 h-4" />
                {{ $currentMonth->copy()->subMonth()->locale($isNl ? 'nl' : 'en')->isoFormat('MMMM Y') }}
            </a>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ $currentMonth->locale($isNl ? 'nl' : 'en')->isoFormat('MMMM Y') }}
            </h2>
            <a wire:navigate
               href="{{ \Modules\Employee\Filament\Pages\EmployeeMonthStats::getUrl(['employee_id' => $employeeId, 'month' => $nextMonth]) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                {{ $currentMonth->copy()->addMonth()->locale($isNl ? 'nl' : 'en')->isoFormat('MMMM Y') }}
                <x-heroicon-o-chevron-right class="w-4 h-4" />
            </a>
        </div>

        {{-- Monthly summary (aggregate from weeks) --}}
        @php
            $weeks      = $data['weeks'] ?? [];
            $totalHours = collect($weeks)->sum('total_hours');
            $totalLaden = collect($weeks)->sum('labor_hours.laden_hours');
            $totalWerf  = collect($weeks)->sum('labor_hours.werf_hours');
            $totalTrans = collect($weeks)->sum('labor_hours.transport_hours');
            $totalDist  = collect($weeks)->sum('total_distance');
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($totalHours, 1) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Totaal' : 'Total' }}</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-cyan-500">{{ number_format($totalLaden, 1) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Laden</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-lime-500">{{ number_format($totalWerf, 1) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Werf</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-pink-500">{{ number_format($totalTrans, 1) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Transport' : 'Transport' }}</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-gray-600 dark:text-gray-300">{{ number_format($totalDist, 0) }} km</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Afstand' : 'Distance' }}</div>
                </div>
            </x-filament::section>
        </div>

        {{-- Weeks table --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ $isNl ? 'Weken' : 'Weeks' }}
            </x-slot>

            @if(empty($weeks))
                <p class="text-sm text-gray-500 dark:text-gray-400 italic py-4">
                    {{ $isNl ? 'Geen uren geregistreerd voor deze maand.' : 'No hours recorded for this month.' }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                                <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400">{{ $isNl ? 'Week' : 'Week' }}</th>
                                <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">{{ $isNl ? 'Dagen gewerkt' : 'Days worked' }}</th>
                                <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">Laden</th>
                                <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">Werf</th>
                                <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">{{ $isNl ? 'Transport' : 'Transport' }}</th>
                                <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">{{ $isNl ? 'Totaal' : 'Total' }}</th>
                                <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">{{ $isNl ? 'Doel' : 'Target' }}</th>
                                <th class="pb-2 font-medium text-gray-600 dark:text-gray-400 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($weeks as $week)
                                @php
                                    $pct = $week['achievement_percentage'] ?? 0;
                                    $color = $pct >= 100 ? 'text-success-600 dark:text-success-400' : ($pct >= 75 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400');
                                    $weekUrl = \Modules\Employee\Filament\Pages\EmployeeWeekStats::getUrl(['employee_id' => $employeeId, 'start_date' => $week['start_date'], 'end_date' => $week['end_date']]);
                                @endphp
                                <tr
                                    x-data
                                    x-on:click="if (!$event.target.closest('a')) { Livewire.navigate('{{ $weekUrl }}') }"
                                    class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer"
                                >
                                    <td class="py-2.5 pr-4">
                                        <a wire:navigate href="{{ $weekUrl }}"
                                           class="font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ \Carbon\Carbon::parse($week['start_date'])->format('d/m') }}
                                            &ndash;
                                            {{ \Carbon\Carbon::parse($week['end_date'])->format('d/m') }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 pr-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ $week['days_worked'] }}/{{ $week['working_days'] }}
                                    </td>
                                    <td class="py-2.5 pr-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($week['labor_hours']['laden_hours'] ?? 0, 1) }}h
                                    </td>
                                    <td class="py-2.5 pr-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($week['labor_hours']['werf_hours'] ?? 0, 1) }}h
                                    </td>
                                    <td class="py-2.5 pr-4 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($week['labor_hours']['transport_hours'] ?? 0, 1) }}h
                                    </td>
                                    <td class="py-2.5 pr-4 text-right font-semibold text-gray-900 dark:text-gray-100">
                                        {{ number_format($week['total_hours'], 1) }}h
                                    </td>
                                    <td class="py-2.5 pr-4 text-right text-gray-500 dark:text-gray-400">
                                        {{ number_format($week['target_hours'], 0) }}h
                                    </td>
                                    <td class="py-2.5 text-right font-medium {{ $color }}">
                                        {{ number_format($pct, 0) }}%
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
