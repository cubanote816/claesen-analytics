<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2 text-danger-600 dark:text-danger-400">
                    <div class="p-2 bg-danger-500/10 rounded-lg">
                        <x-heroicon-o-shield-check class="h-6 w-6" />
                    </div>
                    <span class="text-xl font-extrabold tracking-tight">Vanguard Financial Watchdog</span>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] hidden sm:block">
                        AI Powered Audit
                    </div>
                    <x-filament::badge color="warning">
                        DEMO
                    </x-filament::badge>
                </div>
            </div>
        </x-slot>

        <div class="space-y-6">
            @if(is_array($report))
            <div class="bg-primary-500/5 border border-primary-500/10 p-4 rounded-xl">
                <p class="text-lg font-bold text-primary-700 dark:text-primary-300">{{ $report['greeting'] }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $report['intro'] }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($report['risky_projects'] as $p)
                <div class="group relative overflow-hidden bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-5 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300">
                    <!-- Background Accent -->
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-16 h-16 bg-danger-500/5 rounded-full blur-2xl group-hover:bg-danger-500/10 transition-colors"></div>

                    <div class="flex flex-col h-full">
                        <div class="flex justify-between items-start mb-3">
                            <span class="text-xs font-mono text-gray-400">{{ $p['id'] }}</span>
                            <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-danger-500/10 text-danger-600 border border-danger-500/20">
                                WIP Risico
                            </span>
                        </div>

                        <h3 class="text-base font-bold text-gray-900 dark:text-white line-clamp-1 mb-1">{{ $p['name'] }}</h3>
                        <div class="text-2xl font-black text-danger-600 dark:text-danger-400 mb-4">
                            {{ $p['wip'] }}
                        </div>

                        <div class="mt-auto pt-4 border-t border-gray-100 dark:border-gray-900">
                            <p class="text-[11px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold mb-2">Aanbevolen Actie</p>
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <x-heroicon-m-bolt class="h-4 w-4 text-warning-500" />
                                {{ $p['action'] }}
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <p class="text-sm italic text-gray-500 mt-6">{{ $report['footer'] }}</p>
            @elseif($report)
            <div class="prose dark:prose-invert max-w-none p-6 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 whitespace-pre-wrap font-sans text-sm leading-relaxed">
                {!! nl2br(e($report)) !!}
            </div>
            @else
            <div class="flex flex-col items-center justify-center p-12 text-gray-400 gap-4">
                <x-filament::loading-indicator class="h-10 w-10 text-primary-500" />
                <span class="font-medium animate-pulse">Vanguard is vást de cijfers aan het controleren...</span>
            </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>