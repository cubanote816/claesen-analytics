@php
    /** @var array<int, array{id:int,left:float,top:float,size:int,label:string,serial:?string,flagged:bool,url:string}> $markers */
    $markers = $getState();
@endphp

@if (count($markers) > 0)
    <div class="relative aspect-[4/3] w-full overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-white/10 dark:bg-white/5">
        @foreach ($markers as $marker)
            <a
                href="{{ $marker['url'] }}"
                title="{{ $marker['serial'] ?? ('#'.$marker['label']) }}"
                class="absolute flex items-center justify-center rounded-full border-2 border-white font-mono text-[10px] font-semibold text-white shadow dark:border-gray-900 {{ $marker['flagged'] ? 'bg-amber-500' : 'bg-sky-500 dark:bg-sky-400' }}"
                style="left: {{ $marker['left'] }}%; top: {{ $marker['top'] }}%; width: {{ $marker['size'] }}px; height: {{ $marker['size'] }}px; transform: translate(-50%, -50%);"
            >{{ $marker['label'] }}</a>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('fieldops::resource.luminaire_frames.no_luminaires') }}</p>
@endif
