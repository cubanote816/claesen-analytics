@php
    $state = $getState();
    $values = $state['values'] ?? [];
    $momentum = $state['momentum'] ?? 0;
    $periodLabel = $state['period_label'] ?? 'Maart vs Feb';
    
    // SVG Settings
    $width = 140;
    $height = 40;
    $padding = 4;
    
    $points = [];
    if (count($values) > 1) {
        $max = max($values) ?: 1;
        $min = min($values);
        $range = $max - $min ?: 1;
        
        foreach ($values as $i => $v) {
            $x = ($i / (count($values) - 1)) * ($width - 2 * $padding) + $padding;
            $y = $height - (($v - $min) / $range) * ($height - 2 * $padding) - $padding;
            $points[] = "$x,$y";
        }
    }
    
    $polyline = implode(' ', $points);
    $color = $momentum >= 0 ? '#10b981' : '#f43f5e'; // Emerald-500 or Rose-500
    $gradientId = 'gradient-' . uniqid();
    
    // Create an area path string
    $areaPath = "";
    if (count($points) > 1) {
        $areaPath = "M " . $points[0] . " ";
        for ($i = 1; $i < count($points); $i++) {
            $areaPath .= "L " . $points[$i] . " ";
        }
        $areaPath .= "L " . ($width - $padding) . ",$height L $padding,$height Z";
    }
@endphp

<div class="flex items-center gap-x-3 py-1">
    <div class="relative shrink-0" style="width: 140px; height: 40px;">
        @if(count($values) > 1)
            <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full h-full overflow-visible">
                <defs>
                    <linearGradient id="{{ $gradientId }}" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="{{ $color }}" stop-opacity="0.2" />
                        <stop offset="100%" stop-color="{{ $color }}" stop-opacity="0" />
                    </linearGradient>
                </defs>
                
                <!-- Area Fill with Gradient -->
                <path d="{{ $areaPath }}" fill="url(#{{ $gradientId }})" />
                
                <!-- Trend Line -->
                <polyline
                    points="{{ $polyline }}"
                    fill="none"
                    stroke="{{ $color }}"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    filter="drop-shadow(0px 2px 4px {{ $color }}44)"
                />
                
                <!-- Latest Point Indicator -->
                @php
                    $lastPoint = explode(',', end($points));
                @endphp
                <circle cx="{{ $lastPoint[0] }}" cy="{{ $lastPoint[1] }}" r="3" fill="{{ $color }}" stroke="white" stroke-width="1" />
            </svg>
        @else
            <div class="flex items-center justify-center w-full h-full border border-dashed border-gray-500/30 rounded-lg text-[8px] text-gray-500 uppercase tracking-tighter font-bold bg-gray-500/5">
                No data
            </div>
        @endif
    </div>

    <div class="flex flex-col justify-center min-w-[50px]">
        <div class="flex items-center gap-x-1">
            <span @class([
                'text-xs font-black tabular-nums tracking-tighter',
                'text-emerald-500' => $momentum >= 0,
                'text-rose-500' => $momentum < 0,
            ])>
                {{ $momentum >= 0 ? '+' : '-' }}{{ number_format(abs($momentum), 0) }}%
            </span>
            @if($momentum >= 0)
                <x-heroicon-m-arrow-trending-up class="w-3 h-3 text-emerald-500" />
            @else
                <x-heroicon-m-arrow-trending-down class="w-3 h-3 text-rose-500" />
            @endif
        </div>
        <span class="text-[8px] font-bold text-slate-500 uppercase tracking-[0.2em] leading-none">
            {{ $periodLabel }}
        </span>
    </div>
</div>
