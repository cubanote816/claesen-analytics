<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2 text-primary-600 dark:text-primary-400">
                    <div class="p-1.5 bg-primary-500/10 rounded-lg">
                        <x-heroicon-o-clock class="h-5 w-5" />
                    </div>
                    <span class="font-semibold">
                        {{ app()->getLocale() === 'nl' ? 'Uren Overzicht' : 'Hours Overview' }}
                        @if($period)
                            <span class="text-sm font-normal text-gray-400 dark:text-gray-500 ml-1">— {{ $period }}</span>
                        @endif
                    </span>
                </div>
                <a href="{{ \Modules\Employee\Filament\Pages\EmployeeHoursDashboard::getUrl() }}"
                   class="text-xs text-primary-500 hover:text-primary-600 dark:hover:text-primary-400 font-medium flex items-center gap-1 transition-colors">
                    {{ app()->getLocale() === 'nl' ? 'Volledig dashboard' : 'Full dashboard' }}
                    <x-heroicon-m-arrow-right class="h-3 w-3" />
                </a>
            </div>
        </x-slot>

        @if(empty($summary))
            <p class="text-sm text-gray-500 dark:text-gray-400 italic py-2">
                {{ app()->getLocale() === 'nl' ? 'Geen gegevens beschikbaar.' : 'No data available.' }}
            </p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                {{-- Total hours --}}
                <div class="bg-primary-500/5 border border-primary-500/10 rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        {{ number_format($summary['total_hours'], 1, ',', '.') }}h
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ app()->getLocale() === 'nl' ? 'Totale uren' : 'Total hours' }}
                    </div>
                </div>

                {{-- Active employees --}}
                <div class="bg-success-500/5 border border-success-500/10 rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-success-600 dark:text-success-400">
                        {{ $summary['emp_count'] }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ app()->getLocale() === 'nl' ? 'Actieve medewerkers' : 'Active employees' }}
                    </div>
                </div>

                {{-- Avg hours per employee --}}
                <div class="bg-gray-500/5 border border-gray-500/10 rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-gray-600 dark:text-gray-300">
                        {{ number_format($summary['avg_hours'], 1, ',', '.') }}h
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ app()->getLocale() === 'nl' ? 'Gem. uren/medewerker' : 'Avg hours/employee' }}
                    </div>
                </div>
            </div>

            {{-- Top 3 --}}
            @if(!empty($topThree))
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">
                        {{ app()->getLocale() === 'nl' ? 'Top 3 medewerkers' : 'Top 3 employees' }}
                    </p>
                    <div class="space-y-2">
                        @foreach($topThree as $i => $emp)
                            @php
                                $medals = ['text-yellow-500', 'text-gray-400', 'text-amber-600'];
                                $color  = $medals[$i] ?? 'text-gray-500';
                            @endphp
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold {{ $color }} w-5 text-center">{{ $i + 1 }}</span>
                                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                        {{ $emp['name'] }}
                                    </span>
                                </div>
                                <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                    {{ number_format($emp['total_hours'], 1, ',', '.') }}h
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
