@php
    $viewData = $entry->getViewData();
    $color = $viewData['color'] ?? 'info';
    $icon = $viewData['icon'] ?? null;
    $label = $entry->getLabel();
    $state = $viewData['formattedState'] ?? $entry->getState();

    // If color/icon are closures, resolve them
    if (is_callable($color)) {
        $color = $color($entry->getState());
    }
    if (is_callable($icon)) {
        $icon = $icon($entry->getState());
    }
    
    $themes = [
        'info' => [
            'bg' => 'bg-blue-500/10 dark:bg-blue-400/5',
            'border' => 'border-blue-500/20 dark:border-blue-400/20',
            'icon' => 'text-blue-600 dark:text-blue-400',
            'glow' => 'shadow-blue-500/10',
        ],
        'success' => [
            'bg' => 'bg-emerald-500/10 dark:bg-emerald-400/5',
            'border' => 'border-emerald-500/20 dark:border-emerald-400/20',
            'icon' => 'text-emerald-600 dark:text-emerald-400',
            'glow' => 'shadow-emerald-500/10',
        ],
        'warning' => [
            'bg' => 'bg-amber-500/10 dark:bg-amber-400/5',
            'border' => 'border-amber-500/20 dark:border-amber-400/20',
            'icon' => 'text-amber-600 dark:text-amber-400',
            'glow' => 'shadow-amber-500/10',
        ],
        'danger' => [
            'bg' => 'bg-rose-500/10 dark:bg-rose-400/5',
            'border' => 'border-rose-500/20 dark:border-rose-400/20',
            'icon' => 'text-rose-600 dark:text-rose-400',
            'glow' => 'shadow-rose-500/10',
        ],
    ];
    
    $theme = $themes[$color] ?? $themes['info'];
@endphp

<div 
    {{ $attributes->merge($entry->getExtraAttributes())->class([
        'relative group overflow-hidden rounded-2xl border p-5 transition-all duration-300 hover:scale-[1.02]',
        'backdrop-blur-xl shadow-lg',
        $theme['bg'],
        $theme['border'],
        $theme['glow'],
    ]) }}
>
    <!-- Background Glow Effect -->
    <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-current opacity-[0.03] transition-all duration-500 group-hover:scale-150 {{ $theme['icon'] }}"></div>

    <div class="flex flex-col gap-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ $label }}
            </span>
            
            @if ($icon)
                <x-filament::icon
                    :icon="$icon"
                    class="h-5 w-5 {{ $theme['icon'] }}"
                />
            @endif
        </div>

        <div class="flex items-end justify-between">
            <div class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                {{ $state }}
            </div>
            
            @if($entry->getAction('view_pending_details'))
                <div class="relative z-10">
                    {{ $entry->getAction('view_pending_details') }}
                </div>
            @endif
        </div>
    </div>
</div>
