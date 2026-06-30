<x-filament-panels::page>
    @if($errorMessage)
        <x-filament::section>
            <p class="text-danger-600 dark:text-danger-400">{{ $errorMessage }}</p>
        </x-filament::section>
    @elseif($data)
        @php
            $isNl   = app()->getLocale() === 'nl';
            $weeks  = $data['weeks'] ?? [];
            $totalH = collect($weeks)->sum('total_hours');
            $laden  = collect($weeks)->sum('labor_hours.laden_hours');
            $werf   = collect($weeks)->sum('labor_hours.werf_hours');
            $trans  = collect($weeks)->sum('labor_hours.transport_hours');
            $dist   = collect($weeks)->sum('total_distance');
        @endphp

        {{-- Monthly summary cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($totalH, 1) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Totaal' : 'Total' }}</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-cyan-500">{{ number_format($laden, 1) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Laden</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-lime-500">{{ number_format($werf, 1) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Werf</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-pink-500">{{ number_format($trans, 1) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Transport' : 'Transport' }}</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xl font-bold text-gray-600 dark:text-gray-300">{{ number_format($dist, 0) }} km</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Afstand' : 'Distance' }}</div>
                </div>
            </x-filament::section>
        </div>

        {{-- Weeks table --}}
        <x-filament::section>
            <x-slot name="heading">{{ $isNl ? 'Weken' : 'Weeks' }}</x-slot>

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
                                    $pct   = $week['achievement_percentage'] ?? 0;
                                    $color = $pct >= 100
                                        ? 'text-success-600 dark:text-success-400'
                                        : ($pct >= 75 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400');
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="py-2.5 pr-4">
                                        <a wire:navigate
                                           href="{{ \Modules\Employee\Filament\Pages\EmployeeWeekStats::getUrl(['employee_id' => $this->record->id, 'start_date' => $week['start_date'], 'end_date' => $week['end_date']]) }}"
                                           class="font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                            {{ \Carbon\Carbon::parse($week['start_date'])->format('d/m') }}
                                            &ndash;
                                            {{ \Carbon\Carbon::parse($week['end_date'])->format('d/m') }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 pr-4 text-right text-gray-700 dark:text-gray-300">{{ $week['days_worked'] }}/{{ $week['working_days'] }}</td>
                                    <td class="py-2.5 pr-4 text-right text-gray-700 dark:text-gray-300">{{ number_format($week['labor_hours']['laden_hours'] ?? 0, 1) }}h</td>
                                    <td class="py-2.5 pr-4 text-right text-gray-700 dark:text-gray-300">{{ number_format($week['labor_hours']['werf_hours'] ?? 0, 1) }}h</td>
                                    <td class="py-2.5 pr-4 text-right text-gray-700 dark:text-gray-300">{{ number_format($week['labor_hours']['transport_hours'] ?? 0, 1) }}h</td>
                                    <td class="py-2.5 pr-4 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($week['total_hours'], 1) }}h</td>
                                    <td class="py-2.5 pr-4 text-right text-gray-500 dark:text-gray-400">{{ number_format($week['target_hours'], 0) }}h</td>
                                    <td class="py-2.5 text-right font-medium {{ $color }}">{{ number_format($pct, 0) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
