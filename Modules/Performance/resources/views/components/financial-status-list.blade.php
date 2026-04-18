@php
    $viewData = $entry->getViewData();
    $metrics = $viewData['metrics'] ?? [];
    $totalInvoiced = $metrics['total_invoiced_amount']['value'] ?? 0;
    $totalPaid = $metrics['total_paid_amount']['value'] ?? 0;
    
    // Calculate collection progress
    $progress = $totalInvoiced > 0 ? min(100, round(($totalPaid / $totalInvoiced) * 100)) : 0;
    $progressColor = $progress >= 100 ? 'bg-emerald-500' : ($progress > 50 ? 'bg-blue-500' : 'bg-amber-500');
@endphp

<div 
    {{ $attributes->merge($entry->getExtraAttributes())->class([
        'relative overflow-hidden rounded-2xl border p-4 transition-all duration-300',
        'backdrop-blur-xl shadow-lg bg-white/5 dark:bg-gray-900/40 border-white/20 dark:border-white/10',
    ]) }}
>
    <!-- Background Watermark -->
    <div class="absolute -right-6 -bottom-6 opacity-5 dark:opacity-10">
        <x-filament::icon
            icon="heroicon-m-currency-euro"
            class="h-32 w-32"
        />
    </div>

    <div class="space-y-6 relative z-10">
        <!-- Collection Progress Header -->
        <div class="space-y-2">
            <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                <span>{{ __('performance::project_insight.fields.financial_health') }}</span>
                <span>{{ $progress }}%</span>
            </div>
            <div class="h-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div 
                    class="h-full {{ $progressColor }} transition-all duration-1000 ease-out shadow-[0_0_10px_rgba(0,0,0,0.2)]" 
                    style="width: {{ $progress }}%"
                ></div>
            </div>
        </div>

        <!-- Metrics List -->
        <div class="divide-y divide-white/10 dark:divide-white/5">
            @foreach($metrics as $key => $metric)
                <div class="py-3 flex items-center justify-between group">
                    <div class="flex items-center gap-x-3">
                        <div @class([
                            'p-2 rounded-lg border transition-colors',
                            match($metric['color'] ?? 'info') {
                                'success' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400',
                                'danger' => 'bg-rose-500/10 border-rose-500/20 text-rose-600 dark:text-rose-400',
                                'warning' => 'bg-amber-500/10 border-amber-500/20 text-amber-600 dark:text-amber-400',
                                'info' => 'bg-blue-500/10 border-blue-500/20 text-blue-600 dark:text-blue-400',
                                default => 'bg-gray-500/10 border-gray-500/20 text-gray-600 dark:text-gray-400',
                            }
                        ])>
                            <x-filament::icon
                                :icon="$metric['icon']"
                                class="h-4 w-4"
                            />
                        </div>
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                            {{ $metric['label'] }}
                        </span>
                    </div>

                    <div class="flex items-center gap-x-3">
                        <span @class([
                            'text-sm font-bold tabular-nums tracking-tight',
                            match($metric['color'] ?? 'info') {
                                'success' => 'text-emerald-600 dark:text-emerald-400',
                                'danger' => 'text-rose-600 dark:text-rose-400',
                                'warning' => 'text-amber-600 dark:text-amber-400',
                                'info' => 'text-blue-600 dark:text-blue-400',
                                default => 'text-gray-900 dark:text-white',
                            }
                        ])>
                            {{ $metric['formatted'] }}
                        </span>

                        @if(isset($metric['action']) && $metric['action'])
                            <div class="shrink-0">
                                {{ $metric['action'] }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
