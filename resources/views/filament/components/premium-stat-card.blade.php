@php
    $state = $getState();
    $label = $getLabel();
    
    $icon = $icon ?? 'heroicon-m-chart-bar';
    $color = $color ?? 'primary';
    $compact = $compact ?? false;
    $chart = $chart ?? false;
    $isHero = $is_hero ?? false;

    // Resolve closures
    if (is_callable($icon)) $icon = $icon($state);
    if (is_callable($color)) $color = $color($state);
    if (is_callable($compact)) $compact = $compact($state);
    if (is_callable($chart)) $chart = $chart($state);
    if (is_callable($isHero)) $isHero = $isHero($state);

    $accentColor = match($color) {
        'primary', 'indigo' => 'text-indigo-500',
        'emerald', 'success', 'success' => 'text-emerald-500',
        'orange' => 'text-claesen-orange',
        'rose', 'danger' => 'text-rose-500',
        default => 'text-slate-400',
    };

    $displayValue = is_array($state) ? ($state['value'] ?? 'N/A') : ($state ?? 'N/A');
@endphp

<div @class([
    'glass-signature relative overflow-hidden group transition-all duration-500 hover:-translate-y-1',
    'p-6 lg:p-7' => !$compact,
    'p-4 lg:p-5' => $compact,
    'rounded-[2.5rem]' => !$compact,
    'rounded-2xl' => $compact,
])>
    <!-- Background Glow -->
    <div @class([
        'absolute -right-4 -top-4 w-32 h-32 blur-3xl opacity-0 group-hover:opacity-10 transition-opacity duration-1000 rounded-full',
        match($color) {
            'primary', 'indigo' => 'bg-indigo-500',
            'emerald', 'success' => 'bg-emerald-500',
            'orange' => 'bg-claesen-orange',
            'rose', 'danger' => 'bg-rose-500',
            default => 'bg-slate-500',
        }
    ])></div>

    <div class="relative flex flex-col gap-3 lg:gap-4">
        <!-- Header: Icon + Label -->
        <div class="flex items-center gap-3">
            <div @class([
                'flex items-center justify-center rounded-xl glass-signature border-white/5 dark:border-white/10 shrink-0 shadow-sm',
                'w-11 h-11' => !$compact,
                'w-9 h-9' => $compact,
            ])>
                <x-dynamic-component :component="$icon" @class(['w-5 h-5' => !$compact, 'w-4 h-4' => $compact, $accentColor]) />
            </div>
            
            <span class="text-[9px] lg:text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-[0.2em] truncate">
                {{ $label }}
            </span>
        </div>

        <!-- Body: Balanced Value -->
        <div class="flex flex-col">
            <span @class([
                'font-bold text-slate-950 dark:text-white tracking-signature',
                'text-4xl lg:text-5xl' => !$compact,
                'text-2xl lg:text-3xl' => $compact,
            ])>
                {{ $displayValue }}
            </span>

            @if($chart && is_array($state))
                <div class="flex items-center gap-1.5 mt-2">
                    <div class="flex items-center gap-0.5 px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/10">
                        <x-heroicon-m-arrow-trending-up class="w-2.5 h-2.5 text-emerald-500" />
                        <span class="text-[9px] font-black text-emerald-500 tracking-tighter">
                            {{ $state['change'] ?? '0' }}%
                        </span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
