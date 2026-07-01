<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full flex-wrap gap-3">
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
                <div class="flex items-center gap-3">
                    <input type="month" wire:model.live="selectedMonth"
                           class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-2 py-1">
                    <a wire:navigate href="{{ \Modules\Employee\Filament\Pages\EmployeeHoursDashboard::getUrl() }}"
                       class="text-xs text-primary-500 hover:text-primary-600 dark:hover:text-primary-400 font-medium flex items-center gap-1 transition-colors">
                        {{ app()->getLocale() === 'nl' ? 'Volledig dashboard' : 'Full dashboard' }}
                        <x-heroicon-m-arrow-right class="h-3 w-3" />
                    </a>
                </div>
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
            @if($hasHoursLogged && !empty($topThree))
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">
                        {{ app()->getLocale() === 'nl' ? 'Top 3 medewerkers' : 'Top 3 employees' }}
                    </p>
                    <div class="flex gap-3">
                        @foreach($topThree as $i => $emp)
                            <div class="relative flex-1 flex flex-col items-center text-center p-3 rounded-xl
                                {{ $i === 0 ? 'bg-yellow-500/5 ring-1 ring-yellow-400/40' : '' }}
                                {{ $i === 1 ? 'bg-gray-500/5 ring-1 ring-gray-400/30' : '' }}
                                {{ $i === 2 ? 'bg-amber-600/5 ring-1 ring-amber-500/30' : '' }}">
                                <span class="absolute -top-2 left-1/2 -translate-x-1/2 text-[10px] font-black px-2 py-0.5 rounded-full
                                    {{ $i === 0 ? 'bg-yellow-500 text-white' : '' }}
                                    {{ $i === 1 ? 'bg-gray-400 text-white' : '' }}
                                    {{ $i === 2 ? 'bg-amber-600 text-white' : '' }}">
                                    #{{ $i + 1 }}
                                </span>
                                <p class="mt-2 text-sm font-semibold text-gray-800 dark:text-gray-100 leading-tight line-clamp-2">
                                    {{ $emp['name'] }}
                                </p>
                                <p class="mt-1 text-lg font-bold
                                    {{ $i === 0 ? 'text-yellow-600 dark:text-yellow-400' : '' }}
                                    {{ $i === 1 ? 'text-gray-500 dark:text-gray-300' : '' }}
                                    {{ $i === 2 ? 'text-amber-600 dark:text-amber-400' : '' }}">
                                    {{ number_format($emp['total_hours'], 1, ',', '.') }}h
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400 italic py-2">
                    {{ app()->getLocale() === 'nl' ? 'Geen medewerkers met geregistreerde uren in deze maand.' : 'No employees logged hours this month.' }}
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
