<div class="w-full space-y-4 animate-in fade-in slide-in-from-bottom-2 duration-700">
    <!-- Date Selector Section (Compact Glass Command Center) -->
    <div class="relative overflow-hidden bg-white/40 dark:bg-gray-800/30 backdrop-blur-3xl border border-white/40 dark:border-white/10 rounded-[2rem] p-4 shadow-xl shadow-black/5 ring-1 ring-black/[0.01]">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            <!-- Quick Selection Tabs -->
            <div class="flex items-center bg-gray-200/40 dark:bg-black/20 p-1 rounded-xl border border-white/20 dark:border-white/5">
                @foreach(['this_month', 'last_quarter', 'last_semester', 'previous_year'] as $period)
                <button wire:click="setPeriod('{{ $period }}')"
                    class="px-4 py-1.5 text-[10px] font-black tracking-wider uppercase rounded-lg transition-all flex items-center gap-2 whitespace-nowrap {{ $this->getActivePeriod() == $period ? 'bg-primary-500 text-white shadow-md shadow-primary-500/20' : 'text-gray-500 dark:text-gray-400 hover:bg-white/40 dark:hover:bg-white/5' }}">
                    @php
                    $icon = match($period) {
                    'this_month' => 'heroicon-m-calendar',
                    'last_quarter' => 'heroicon-m-chart-pie',
                    'last_semester' => 'heroicon-m-rectangle-group',
                    'previous_year' => 'heroicon-m-archive-box',
                    };
                    @endphp
                    <x-dynamic-component :component="$icon" class="w-3.5 h-3.5" />
                    {{ __('employees/resource.placeholders.' . $period) }}
                </button>
                @endforeach
            </div>

            <!-- Date Range Inputs (Compact) -->
            <div class="flex items-center gap-4 flex-1 xl:max-w-md">
                <div class="flex-1 flex items-center gap-2 group">
                    <label class="shrink-0 text-[9px] uppercase tracking-widest font-black text-gray-400 dark:text-gray-500">{{ __('employees/resource.placeholders.from') }}</label>
                    <input type="date" wire:model.live="fromDate"
                        class="w-full bg-white/30 dark:bg-black/10 border-white/20 dark:border-white/5 rounded-xl text-xs text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 shadow-sm h-9 px-3 cursor-pointer backdrop-blur-md">
                </div>
                <div class="flex-1 flex items-center gap-2 group">
                    <label class="shrink-0 text-[9px] uppercase tracking-widest font-black text-gray-400 dark:text-gray-500">{{ __('employees/resource.placeholders.to') }}</label>
                    <input type="date" wire:model.live="toDate"
                        class="w-full bg-white/30 dark:bg-black/10 border-white/20 dark:border-white/5 rounded-xl text-xs text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 shadow-sm h-9 px-3 cursor-pointer backdrop-blur-md">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Grid (Optimized Spacing) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">

        <!-- Left Column: Hours Distribution (Compact Visual) -->
        <div class="lg:col-span-4 relative bg-white/70 dark:bg-gray-900/40 backdrop-blur-2xl border border-white/20 dark:border-white/5 rounded-[2rem] p-6 shadow-xl flex flex-col items-center">
            <h4 class="text-[9px] uppercase tracking-[0.3em] font-black text-gray-400 dark:text-gray-500 mb-6 text-center">{{ __('employees/resource.sections.hours_distribution') }}</h4>

            <div class="relative w-44 h-44 mb-8 group">
                <!-- Doughnut Chart (SVG - Resized) -->
                @php
                $total = max($totalHours, 0.01);
                $effPerc = ($distribution['effective'] / $total) * 100;
                $loadPerc = ($distribution['loading'] / $total) * 100;
                $transPerc = ($distribution['transport'] / $total) * 100;

                $dashEff = ($effPerc / 100) * 251.2;
                $dashLoad = ($loadPerc / 100) * 251.2;
                $dashTrans = ($transPerc / 100) * 251.2;
                @endphp
                <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90 filter drop-shadow-[0_0_10px_rgba(99,102,241,0.1)] group-hover:scale-105 transition-transform duration-500">
                    <circle cx="50" cy="50" r="40" stroke="currentColor" stroke-width="12" fill="transparent" class="text-gray-100 dark:text-gray-800/40" />

                    <circle cx="50" cy="50" r="40" stroke="#10b981" stroke-width="12" fill="transparent"
                        stroke-dasharray="{{ $dashEff }} 251.2" stroke-dashoffset="0"
                        stroke-linecap="round"
                        class="transition-all duration-700 ease-in-out" />

                    <circle cx="50" cy="50" r="40" stroke="#f59e0b" stroke-width="12" fill="transparent"
                        stroke-dasharray="{{ $dashLoad }} 251.2" stroke-dashoffset="-{{ $dashEff }}"
                        stroke-linecap="round"
                        class="transition-all duration-700 ease-in-out" />

                    <circle cx="50" cy="50" r="40" stroke="#6366f1" stroke-width="12" fill="transparent"
                        stroke-dasharray="{{ $dashTrans }} 251.2" stroke-dashoffset="-{{ $dashEff + $dashLoad }}"
                        stroke-linecap="round"
                        class="transition-all duration-700 ease-in-out" />
                </svg>

                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-3xl font-black text-gray-900 dark:text-white tracking-tighter">{{ round($totalHours) }}<span class="text-lg opacity-40 ml-0.5">h</span></span>
                    <span class="text-[8px] uppercase tracking-[0.2em] font-black text-gray-400 dark:text-gray-500 mt-0.5">Total</span>
                </div>
            </div>

            <!-- Compact Legend -->
            <div class="w-full space-y-3 px-2">
                @foreach([
                ['Effective', '#10b981', $distribution['effective']],
                ['Loading', '#f59e0b', $distribution['loading']],
                ['Transport', '#6366f1', $distribution['transport']]
                ] as [$label, $color, $value])
                <div class="flex items-center justify-between group cursor-default">
                    <div class="flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full border border-white/20" style="background-color: {{ $color }}; box-shadow: 0 0 8px {{ $color }}66"></div>
                        <span class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">{{ $label }}</span>
                    </div>
                    <span class="text-xs font-black text-gray-900 dark:text-gray-100">{{ number_format($value, 1) }}h</span>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Right Column: Project Timeline (Dense List) -->
        <div class="lg:col-span-8 bg-white/70 dark:bg-gray-900/40 backdrop-blur-2xl border border-white/20 dark:border-white/5 rounded-[2rem] p-6 shadow-xl h-full">
            <h4 class="text-[9px] uppercase tracking-[0.3em] font-black text-gray-400 dark:text-gray-500 mb-6 px-2">{{ __('employees/resource.sections.project_timeline_title') }}</h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($timeline as $item)
                <a href="{{ route('filament.admin.resources.project-insights.view', ['record' => $item['project_id']]) }}" class="block">
                    <div class="relative group p-4 rounded-2xl transition-all hover:bg-white/50 dark:hover:bg-white/5 border border-transparent hover:border-white/20 dark:hover:border-white/5 shadow-none hover:shadow-lg hover:shadow-primary-500/5 cursor-pointer h-full">
                        <div class="flex justify-between items-start mb-3 gap-3">
                            <div class="flex-1 min-w-0">
                                <h5 class="font-black text-gray-900 dark:text-white group-hover:text-primary-500 transition-colors text-sm leading-tight truncate mb-1" title="{{ $item['project_name'] }}">
                                    {{ $item['project_name'] }}
                                </h5>
                                <div class="flex items-center gap-2">
                                    <span class="text-[8px] uppercase font-black text-gray-500 dark:text-gray-400 tracking-wider bg-gray-100 dark:bg-white/5 px-1.5 py-0.5 rounded-md border border-gray-200/50 dark:border-white/5">{{ $item['project_code'] }}</span>
                                    <span class="inline-flex items-center px-1 py-0.5 rounded-md bg-primary-100/50 dark:bg-primary-900/30 text-[8px] font-black text-primary-600 dark:text-primary-400 uppercase">{{ $item['month_label'] }}</span>
                                </div>
                            </div>
                            <div class="bg-gray-100 dark:bg-white/5 px-2.5 py-1 rounded-xl border border-gray-200/30 dark:border-white/5 flex items-center justify-center shrink-0">
                                <span class="text-xs font-black text-gray-900 dark:text-white">{{ number_format($item['total_hours'], 1) }}h</span>
                            </div>
                        </div>

                        <!-- Compact Progress Bar -->
                        <div class="space-y-1.5">
                            <div class="relative h-1.5 w-full bg-gray-200/50 dark:bg-gray-800/50 rounded-full overflow-hidden">
                                <div class="absolute inset-0 bg-gradient-to-r from-primary-500 to-indigo-500 rounded-full transition-all duration-700 ease-out shadow-[0_0_10px_rgba(99,102,241,0.2)]"
                                    style="width: {{ $item['percentage'] }}%">
                                </div>
                            </div>
                            <div class="flex justify-between items-center px-0.5">
                                <span class="text-[8px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Utilisatie</span>
                                <span class="text-[9px] font-black text-primary-600 dark:text-primary-400">{{ $item['percentage'] }}%</span>
                            </div>
                        </div>
                    </div>
                </a>
                @empty
                <div class="col-span-full flex flex-col items-center justify-center py-16 text-gray-300 dark:text-gray-600 border-2 border-dashed border-gray-50 dark:border-gray-800/50 rounded-2xl">
                    <x-heroicon-o-document-magnifying-glass class="w-10 h-10 mb-3 opacity-10" />
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] italic">Geen projectdata</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>