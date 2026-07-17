@php
    /** @var array<int, array{id:int,left:float,top:float,size:int,label:string,serial:?string,imageUrl:?string,hasImage:bool,flagged:bool,selected?:bool,url:string}> $markers */
    $markers = $getState();
    $placeholderImage = asset('assets/luminaire-subgroups/image_placeholder.png');
@endphp

@if (count($markers) > 0)
    <div class="relative aspect-[4/3] w-full overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-white/10 dark:bg-white/5">
        @foreach ($markers as $marker)
            <a
                href="{{ $marker['url'] }}"
                title="{{ $marker['serial'] ?? ('#'.$marker['label']) }}"
                class="absolute overflow-hidden rounded-lg border border-slate-500/40 bg-slate-950 shadow-md dark:border-slate-400/25 {{ ($marker['selected'] ?? false) ? 'ring-4 ring-offset-2 ring-sky-500 dark:ring-offset-gray-900' : '' }}"
                style="left: {{ $marker['left'] }}%; top: {{ $marker['top'] }}%; width: {{ $marker['size'] }}px; height: {{ $marker['size'] }}px; transform: translate(-50%, -50%);"
            >
                @if (! empty($marker['imageUrl']))
                    <img
                        src="{{ $marker['imageUrl'] }}"
                        alt=""
                        class="h-full w-full object-contain p-1.5 opacity-90"
                        loading="lazy"
                        onerror="this.onerror=null;this.src='{{ $placeholderImage }}';"
                    >
                @else
                    <div class="grid h-full w-full place-items-center bg-slate-950 text-white">
                        <svg class="h-4 w-4 opacity-85" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3v18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M5 10h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            <path d="M7.5 7h9l2 3.5-6.5 6.5-6.5-6.5L7.5 7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        </svg>
                    </div>
                @endif
                <span class="absolute left-1 top-1 rounded bg-slate-900/85 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-white backdrop-blur-sm">
                    {{ $marker['label'] }}
                </span>
            </a>
        @endforeach
    </div>
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('fieldops::resource.luminaire_frames.no_luminaires') }}</p>
@endif
