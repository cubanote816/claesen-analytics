<div class="w-full space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <!-- Date Selector Section (Command Center) -->
    <div class="bg-gray-50/80 backdrop-blur-md border border-gray-200/50 rounded-[2rem] p-6 shadow-sm ring-1 ring-black/[0.02]">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-8">
            <!-- Quick Selection Tabs -->
            <div class="flex items-center bg-white p-1.5 rounded-2xl border border-gray-200/50 shadow-inner overflow-hidden">
                @foreach(['this_month', 'last_quarter', 'last_semester', 'previous_year'] as $period)
                <button wire:click="setPeriod('{{ $period }}')"
                    class="px-5 py-2.5 text-xs font-black rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ $this->getActivePeriod() == $period ? 'bg-primary-500 text-white shadow-md shadow-primary-500/20' : 'text-gray-500 hover:bg-gray-50' }}">
                    @php
                    $icon = match($period) {
                    'this_month' => 'heroicon-m-calendar',
                    'last_quarter' => 'heroicon-m-chart-pie',
                    'last_semester' => 'heroicon-m-rectangle-group',
                    'previous_year' => 'heroicon-m-archive-box',
                    };
                    @endphp
                    <x-dynamic-component :component="$icon" class="w-4 h-4" />
                    {{ __('employees/resource.placeholders.' . $period) }}
                </button>
                @endforeach
            </div>

            <!-- Date Range Inputs -->
            <div class="flex items-center gap-6 flex-1 xl:max-w-xl">
                <div class="flex-1">
                    <label class="block text-[10px] uppercase tracking-[0.2em] font-black text-gray-400 mb-2 ml-1">{{ __('employees/resource.placeholders.from') }}</label>
                    <input type="date" wire:model.live="fromDate"
                        class="w-full bg-white border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 shadow-sm h-12 transition-all px-4 cursor-pointer">
                </div>
                <div class="flex-1">
                    <label class="block text-[10px] uppercase tracking-[0.2em] font-black text-gray-400 mb-2 ml-1">{{ __('employees/resource.placeholders.to') }}</label>
                    <input type="date" wire:model.live="toDate"
                        class="w-full bg-white border-gray-200 rounded-2xl text-sm focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 shadow-sm h-12 transition-all px-4 cursor-pointer">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">

        <!-- Left Column: Hours Distribution -->
        <div class="lg:col-span-5 bg-white border border-gray-100 rounded-[2.5rem] p-10 shadow-sm flex flex-col items-center">
            <h4 class="text-[10px] uppercase tracking-[0.3em] font-black text-gray-400 mb-12 text-center">{{ __('employees/resource.sections.hours_distribution') ?? 'HOURS DISTRIBUTION' }}</h4>

            <div class="relative w-64 h-64 mb-16">
                <!-- Doughnut Chart (SVG) -->
                @php
                $total = max($totalHours, 0.01);
                $effPerc = ($distribution['effective'] / $total) * 100;
                $loadPerc = ($distribution['loading'] / $total) * 100;
                $transPerc = ($distribution['transport'] / $total) * 100;

                $dashEff = ($effPerc / 100) * 251.2;
                $dashLoad = ($loadPerc / 100) * 251.2;
                $dashTrans = ($transPerc / 100) * 251.2;
                @endphp
                <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                    <circle cx="50" cy="50" r="40" stroke="#f8fafc" stroke-width="10" fill="transparent" />

                    <circle cx="50" cy="50" r="40" stroke="#10b981" stroke-width="10" fill="transparent"
                        stroke-dasharray="{{ $dashEff }} 251.2" stroke-dashoffset="0"
                        class="transition-all duration-1000 ease-out" />

                    <circle cx="50" cy="50" r="40" stroke="#f59e0b" stroke-width="10" fill="transparent"
                        stroke-dasharray="{{ $dashLoad }} 251.2" stroke-dashoffset="-{{ $dashEff }}"
                        class="transition-all duration-1000 ease-out" />

                    <circle cx="50" cy="50" r="40" stroke="#6366f1" stroke-width="10" fill="transparent"
                        stroke-dasharray="{{ $dashTrans }} 251.2" stroke-dashoffset="-{{ $dashEff + $dashLoad }}"
                        class="transition-all duration-1000 ease-out" />
                </svg>

                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-5xl font-black text-gray-900 tracking-tighter">{{ round($totalHours) }}h</span>
                    <span class="text-[10px] uppercase tracking-widest font-bold text-gray-400">Total</span>
                </div>
            </div>

            <!-- Legend with better spacing -->
            <div class="w-full max-w-xs space-y-5">
                <div class="flex items-center justify-between group">
                    <div class="flex items-center gap-4">
                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.3)]"></div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">Effective</span>
                    </div>
                    <span class="text-sm font-black text-gray-900 tracking-tight">{{ number_format($distribution['effective'], 1) }}h</span>
                </div>
                <div class="flex items-center justify-between group">
                    <div class="flex items-center gap-4">
                        <div class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.3)]"></div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">Loading</span>
                    </div>
                    <span class="text-sm font-black text-gray-900 tracking-tight">{{ number_format($distribution['loading'], 1) }}h</span>
                </div>
                <div class="flex items-center justify-between group">
                    <div class="flex items-center gap-4">
                        <div class="w-2.5 h-2.5 rounded-full bg-indigo-500 shadow-[0_0_8px_rgba(99,102,241,0.3)]"></div>
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-widest">Transport</span>
                    </div>
                    <span class="text-sm font-black text-gray-900 tracking-tight">{{ number_format($distribution['transport'], 1) }}h</span>
                </div>
            </div>
        </div>

        <!-- Right Column: Project Timeline -->
        <div class="lg:col-span-7 bg-white border border-gray-100 rounded-[2.5rem] p-10 shadow-sm h-full">
            <h4 class="text-[10px] uppercase tracking-[0.3em] font-black text-gray-400 mb-10">{{ __('employees/resource.sections.project_timeline_title') ?? 'PROJECT TIMELINE' }}</h4>

            <div class="space-y-4">
                @forelse($timeline as $item)
                <div class="relative group p-6 rounded-3xl transition-all hover:bg-gray-50/50 border border-transparent hover:border-gray-100 ring-1 ring-transparent">
                    <div class="flex justify-between items-start mb-4 gap-4">
                        <div class="flex-1">
                            <h5 class="font-black text-gray-900 group-hover:text-primary-600 transition-colors text-lg leading-snug mb-1">
                                {{ $item['project_name'] }}
                            </h5>
                            <div class="flex items-center gap-3">
                                <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider bg-gray-100 px-2 py-0.5 rounded-md">{{ $item['project_code'] }}</span>
                                <span class="text-[10px] font-black text-primary-500 tracking-tighter italic">{{ $item['month_label'] }}</span>
                            </div>
                        </div>
                        <div class="bg-white px-4 py-2 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-center min-w-[70px]">
                            <span class="text-sm font-black text-gray-900">{{ number_format($item['total_hours'], 1) }}h</span>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="space-y-2">
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-primary-500 rounded-full transition-all duration-1000 ease-out shadow-[0_0_12px_rgba(99,102,241,0.3)]"
                                style="width: {{ $item['percentage'] }}%"></div>
                        </div>
                        <div class="flex justify-between items-center px-0.5">
                            <span class="text-[9px] font-black text-gray-300 uppercase tracking-widest">Utilisatie</span>
                            <span class="text-[10px] font-black text-primary-600">{{ $item['percentage'] }}%</span>
                        </div>
                    </div>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center py-32 text-gray-300 border-2 border-dashed border-gray-50 rounded-[2rem]">
                    <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mb-4 opacity-10" />
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] italic">Geen projectdata gevonden</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>