@props([
    'categories' => [],
    'total' => 0,
    'height' => 'h-2',
])

@php
    // Handle Filament state if used as a ViewEntry
    if (isset($getState) && is_callable($getState)) {
        $filamentState = $getState();
        $categories = $filamentState['categories'] ?? [];
        $total = $filamentState['total'] ?? 0;
    }

    $total = (float) $total;
    if ($total <= 0) {
        $total = array_sum($categories);
    }
    
    $segments = [
        'Werf' => [
            'value' => (float) ($categories['Werf'] ?? 0),
            'color' => 'bg-emerald-500', // Green for Work
            'label' => 'Werf',
        ],
        'Laden' => [
            'value' => (float) ($categories['Laden'] ?? 0),
            'color' => 'bg-indigo-500', // Blue for Loading
            'label' => 'Laden',
        ],
        'Mobiliteit' => [
            'value' => (float) ($categories['Mobiliteit'] ?? 0),
            'color' => 'bg-amber-500',
            'label' => 'Reis',
        ],
    ];
@endphp

<div class="w-full">
    <div class="flex {{ $height }} w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800 shadow-inner">
        @if($total > 0)
            @foreach($segments as $key => $segment)
                @php
                    $percentage = ($segment['value'] / $total) * 100;
                @endphp
                @if($percentage > 0)
                    <div 
                        class="{{ $segment['color'] }} transition-all duration-500 ease-out h-full"
                        style="width: {{ $percentage }}%"
                        title="{{ $segment['label'] }}: {{ number_format($segment['value'], 2) }}h ({{ round($percentage) }}%)"
                    ></div>
                @endif
            @endforeach
        @endif
    </div>
    
    @if($total > 0 && $height !== 'h-1')
        <div class="flex justify-between items-center mt-1 text-[10px] text-slate-500 font-medium">
            <div class="flex gap-2">
                @foreach($segments as $key => $segment)
                    @if($segment['value'] > 0)
                        <div class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full {{ $segment['color'] }}"></span>
                            <span>{{ number_format($segment['value'], 1) }}h</span>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="font-bold text-slate-700 dark:text-slate-300">
                {{ number_format($total, 2) }}h
            </div>
        </div>
    @endif
</div>
