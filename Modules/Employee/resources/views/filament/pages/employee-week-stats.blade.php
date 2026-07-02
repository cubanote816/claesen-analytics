<x-filament-panels::page>
    @php
        $isNl = app()->getLocale() === 'nl';
    @endphp

    @if($errorMessage)
        <x-filament::section>
            <p class="text-danger-600 dark:text-danger-400">{{ $errorMessage }}</p>
        </x-filament::section>
    @elseif($data)
        @php
            $summary      = $data['summary'] ?? [];
            $laborHours   = $data['labor_hours'] ?? [];
            $financial    = $data['financial'] ?? [];
            $transport    = $data['transport'] ?? [];
            $dailyBreakdown = $data['daily_breakdown'] ?? [];
            $projects     = $data['projects'] ?? [];
            $period       = $data['period'] ?? [];

            $pct   = $summary['achievement_percentage'] ?? 0;
            $color = $pct >= 100 ? 'text-success-600 dark:text-success-400' : ($pct >= 75 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400');

            $start = \Carbon\Carbon::parse($startDate);
            $end   = \Carbon\Carbon::parse($endDate);
        @endphp

        {{-- Week header --}}
        <div class="flex items-center justify-between mb-6">
            @php
                $prevStart = $start->copy()->subWeek()->format('Y-m-d');
                $prevEnd   = $end->copy()->subWeek()->format('Y-m-d');
                $nextStart = $start->copy()->addWeek()->format('Y-m-d');
                $nextEnd   = $end->copy()->addWeek()->format('Y-m-d');
            @endphp
            <a wire:navigate href="{{ \Modules\Employee\Filament\Pages\EmployeeWeekStats::getUrl(['employee_id' => $employeeId, 'start_date' => $prevStart, 'end_date' => $prevEnd, 'from' => $from]) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                <x-heroicon-o-chevron-left class="w-4 h-4" />
                {{ $isNl ? 'Vorige week' : 'Previous week' }}
            </a>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ $start->format('d/m') }} &ndash; {{ $end->format('d/m/Y') }}
            </h2>
            <a wire:navigate href="{{ \Modules\Employee\Filament\Pages\EmployeeWeekStats::getUrl(['employee_id' => $employeeId, 'start_date' => $nextStart, 'end_date' => $nextEnd, 'from' => $from]) }}"
               class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                {{ $isNl ? 'Volgende week' : 'Next week' }}
                <x-heroicon-o-chevron-right class="w-4 h-4" />
            </a>
        </div>

        {{-- Stats summary --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ number_format($summary['total_hours'] ?? 0, 1) }}h</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Totale uren' : 'Total hours' }}</div>
                    <div class="text-xs {{ $color }} font-medium">{{ number_format($pct, 0) }}% {{ $isNl ? 'van doel' : 'of target' }}</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ $summary['days_worked'] ?? 0 }}/{{ $period['working_days'] ?? 5 }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Dagen gewerkt' : 'Days worked' }}</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                        €{{ number_format($financial['revenue'] ?? 0, 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Omzet' : 'Revenue' }}</div>
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
            {{-- Daily breakdown --}}
            <x-filament::section>
                <x-slot name="heading">{{ $isNl ? 'Dagelijks overzicht' : 'Daily breakdown' }}</x-slot>

                @if(empty($dailyBreakdown))
                    <p class="text-sm text-gray-500 italic">{{ $isNl ? 'Geen uren gevonden.' : 'No hours found.' }}</p>
                @else
                    <div class="space-y-1">
                        @foreach($dailyBreakdown as $day)
                            @php
                                $dayDate = \Carbon\Carbon::parse($day['date']);
                                $dayUrl  = \Modules\Employee\Filament\Pages\EmployeeDayStats::getUrl(['employee_id' => $employeeId, 'date' => $day['date'], 'from' => $from]);
                            @endphp
                            <div
                                x-data
                                x-on:click="if (!$event.target.closest('a')) { Livewire.navigate('{{ $dayUrl }}') }"
                                class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-800 last:border-0 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50"
                            >
                                <a wire:navigate href="{{ $dayUrl }}"
                                   class="font-medium text-primary-600 dark:text-primary-400 hover:underline min-w-28">
                                    {{ $dayDate->locale($isNl ? 'nl' : 'en')->isoFormat('ddd D/MM') }}
                                </a>
                                <div class="flex gap-3 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="text-cyan-500">{{ number_format($day['labor_hours']['laden_hours'] ?? 0, 1) }}h</span>
                                    <span class="text-lime-500">{{ number_format($day['labor_hours']['werf_hours'] ?? 0, 1) }}h</span>
                                    <span class="text-pink-500">{{ number_format($day['labor_hours']['transport_hours'] ?? 0, 1) }}h</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100 w-12 text-right">{{ number_format($day['hours'], 1) }}h</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            {{-- Projects --}}
            <x-filament::section>
                <x-slot name="heading">{{ $isNl ? 'Projecten deze week' : 'Projects this week' }}</x-slot>

                @if(empty($projects))
                    <p class="text-sm text-gray-500 italic">{{ $isNl ? 'Geen projecten gevonden.' : 'No projects found.' }}</p>
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
                                    {{ number_format($project['hours'], 1) }}h
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
