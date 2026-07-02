<x-filament-panels::page>
    @php
        $isNl       = app()->getLocale() === 'nl';
        $parsedDate = $date ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::now();
        $prevDay    = $parsedDate->copy()->subDay()->format('Y-m-d');
        $nextDay    = $parsedDate->copy()->addDay()->format('Y-m-d');
    @endphp

    @if($errorMessage)
        <x-filament::section>
            <p class="text-danger-600 dark:text-danger-400">{{ $errorMessage }}</p>
        </x-filament::section>
    @elseif($data)
        @php
            $dayInfo   = $data['day_info'] ?? [];
            $summary   = $data['summary'] ?? [];
            $schedule  = $data['schedule'] ?? [];
            $labor     = $data['labor_hours'] ?? [];
            $financial = $data['financial'] ?? [];
            $transport = $data['transport'] ?? [];
            $projects  = $data['projects'] ?? [];

            $pct   = $summary['achievement_percentage'] ?? 0;
            $color = $pct >= 100 ? 'text-success-600 dark:text-success-400' : ($pct >= 75 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400');
        @endphp

        {{-- Day navigation --}}
        <div class="flex items-center justify-between mb-6">
            <a wire:navigate href="{{ \Modules\Employee\Filament\Pages\EmployeeDayStats::getUrl(['employee_id' => $employeeId, 'date' => $prevDay, 'from' => $from]) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                <x-heroicon-o-chevron-left class="w-4 h-4" />
                {{ \Carbon\Carbon::parse($prevDay)->locale($isNl ? 'nl' : 'en')->isoFormat('ddd D/MM') }}
            </a>

            <div class="text-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $parsedDate->locale($isNl ? 'nl' : 'en')->isoFormat('dddd D MMMM Y') }}
                </h2>
                @if($dayInfo['is_weekend'] ?? false)
                    <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded-full">
                        {{ $isNl ? 'Weekend' : 'Weekend' }}
                    </span>
                @endif
                @if($schedule['start_time'] && $schedule['end_time'])
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $schedule['start_time'] }} &ndash; {{ $schedule['end_time'] }}
                    </div>
                @endif
            </div>

            <a wire:navigate href="{{ \Modules\Employee\Filament\Pages\EmployeeDayStats::getUrl(['employee_id' => $employeeId, 'date' => $nextDay, 'from' => $from]) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                {{ \Carbon\Carbon::parse($nextDay)->locale($isNl ? 'nl' : 'en')->isoFormat('ddd D/MM') }}
                <x-heroicon-o-chevron-right class="w-4 h-4" />
            </a>
        </div>

        {{-- Stats grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($summary['total_hours'] ?? 0, 2) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Totale uren' : 'Total hours' }}</div>
                    <div class="text-xs {{ $color }} font-medium">{{ number_format($pct, 0) }}%</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400">{{ number_format($summary['approved_hours'] ?? 0, 2) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Goedgekeurd' : 'Approved' }}</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold {{ ($financial['profit'] ?? 0) >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                        €{{ number_format($financial['profit'] ?? 0, 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Marge' : 'Margin' }}</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ number_format($transport['total_distance'] ?? 0, 0) }} km</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Afstand' : 'Distance' }}</div>
                </div>
            </x-filament::section>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Labor breakdown --}}
            <x-filament::section>
                <x-slot name="heading">{{ $isNl ? 'Urenopbouw' : 'Labor breakdown' }}</x-slot>

                <div class="space-y-3">
                    @php
                        $totalLabor = ($labor['laden_hours'] ?? 0) + ($labor['werf_hours'] ?? 0) + ($labor['transport_hours'] ?? 0);
                    @endphp
                    @foreach([
                        ['label' => 'Laden', 'hours' => $labor['laden_hours'] ?? 0, 'color' => '#00aeef'],
                        ['label' => 'Werf', 'hours' => $labor['werf_hours'] ?? 0, 'color' => '#a5d610'],
                        ['label' => app()->getLocale() === 'nl' ? 'Transport' : 'Transport', 'hours' => $labor['transport_hours'] ?? 0, 'color' => '#e6007e'],
                    ] as $item)
                        @if($item['hours'] > 0)
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($item['hours'], 2) }}h</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full"
                                         style="width: {{ $totalLabor > 0 ? min(($item['hours'] / $totalLabor) * 100, 100) : 0 }}%; background-color: {{ $item['color'] }};"></div>
                                </div>
                            </div>
                        @endif
                    @endforeach

                    <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ $isNl ? 'Kostprijs' : 'Cost' }}</span>
                            <span class="text-gray-700 dark:text-gray-300">€{{ number_format($financial['costs'] ?? 0, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            <span class="text-gray-500 dark:text-gray-400">{{ $isNl ? 'Verkoopprijs' : 'Revenue' }}</span>
                            <span class="text-gray-700 dark:text-gray-300">€{{ number_format($financial['revenue'] ?? 0, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </x-filament::section>

            {{-- Projects --}}
            <x-filament::section>
                <x-slot name="heading">{{ $isNl ? 'Projecten vandaag' : 'Projects today' }}</x-slot>

                @if(empty($projects))
                    <p class="text-sm text-gray-500 italic">
                        {{ $isNl ? 'Geen projecten voor deze dag.' : 'No projects for this day.' }}
                    </p>
                @else
                    <div class="space-y-1">
                        @foreach($projects as $project)
                            @php
                                $projectUrl = \Modules\Intelligence\Filament\Pages\ProjectIntelligenceDetail::getProjectUrl($project['id']);
                            @endphp
                            <div
                                x-data
                                x-on:click="if (!$event.target.closest('a')) { Livewire.navigate('{{ $projectUrl }}') }"
                                class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800 last:border-0 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            >
                                <a wire:navigate href="{{ $projectUrl }}"
                                   class="text-sm text-primary-600 dark:text-primary-400 hover:underline truncate max-w-xs">
                                    {{ $project['name'] ?? '—' }}
                                </a>
                                <span class="text-sm font-semibold text-primary-600 dark:text-primary-400 ml-2">
                                    {{ number_format($project['hours'], 2) }}h
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
