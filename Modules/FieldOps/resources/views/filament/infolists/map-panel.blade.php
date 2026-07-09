@php
    /**
     * @var array{
     *     title: string,
     *     subtitle?: string,
     *     emptyTitle?: string,
     *     emptyDescription?: string,
     *     markers: array<int, array{type: string, label: string, description?: ?string, lat: float|int|string|null, lng: float|int|string|null, url?: ?string}>,
     *     summary?: array<int, array{label: string, value: int|string}>,
     * } $data
     */
    $data = $getState();
    $id = 'fieldops-map-'.md5(json_encode($data));
    $markers = collect($data['markers'] ?? [])
        ->filter(fn ($marker) => is_numeric($marker['lat'] ?? null) && is_numeric($marker['lng'] ?? null))
        ->values()
        ->all();

    $typeStyles = [
        'complex' => ['label' => 'Complex', 'class' => 'bg-rose-500'],
        'terrain' => ['label' => 'Terrain', 'class' => 'bg-lime-500'],
        'structure' => ['label' => 'Structure', 'class' => 'bg-amber-500'],
        'electrical-board' => ['label' => 'Electrical board', 'class' => 'bg-slate-500'],
    ];

    $legend = collect($markers)
        ->pluck('type')
        ->unique()
        ->map(fn ($type) => $typeStyles[$type] ?? ['label' => ucfirst(str_replace('-', ' ', $type)), 'class' => 'bg-primary-500'])
        ->values()
        ->all();

    $latitudes = collect($markers)->pluck('lat')->map(fn ($value) => (float) $value);
    $longitudes = collect($markers)->pluck('lng')->map(fn ($value) => (float) $value);
    $centerLat = $latitudes->avg();
    $centerLng = $longitudes->avg();
    $latMin = $latitudes->min();
    $latMax = $latitudes->max();
    $lngMin = $longitudes->min();
    $lngMax = $longitudes->max();
    $latPadding = max(0.002, (($latMax ?? 0) - ($latMin ?? 0)) * 0.35);
    $lngPadding = max(0.002, (($lngMax ?? 0) - ($lngMin ?? 0)) * 0.35);
    $boundsLatMin = ($latMin ?? 0) - $latPadding;
    $boundsLatMax = ($latMax ?? 0) + $latPadding;
    $boundsLngMin = ($lngMin ?? 0) - $lngPadding;
    $boundsLngMax = ($lngMax ?? 0) + $lngPadding;
    $mapUrl = ! empty($markers)
        ? 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
            'bbox' => implode(',', [
                $boundsLngMin,
                $boundsLatMin,
                $boundsLngMax,
                $boundsLatMax,
            ]),
            'layer' => 'mapnik',
            'marker' => $centerLat.','.$centerLng,
        ])
        : null;
    $displayMarkers = collect($markers)
        ->map(function ($marker) use ($boundsLatMin, $boundsLatMax, $boundsLngMin, $boundsLngMax) {
            $latRange = max(0.000001, $boundsLatMax - $boundsLatMin);
            $lngRange = max(0.000001, $boundsLngMax - $boundsLngMin);

            return array_merge($marker, [
                'left' => max(4, min(96, (((float) $marker['lng'] - $boundsLngMin) / $lngRange) * 100)),
                'top' => max(4, min(96, (($boundsLatMax - (float) $marker['lat']) / $latRange) * 100)),
            ]);
        })
        ->values()
        ->all();
@endphp

<div class="fieldops-map-panel-root">
<div
    id="{{ $id }}"
    class="fieldops-map-panel overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
    data-fieldops-map-panel
>
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10">
        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $data['title'] }}</h3>
            @if (! empty($data['subtitle']))
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $data['subtitle'] }}</p>
            @endif
        </div>

        @if (! empty($data['summary']))
            <div class="flex flex-wrap gap-2">
                @foreach ($data['summary'] as $item)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                        <span class="font-mono text-primary-600 dark:text-primary-400">{{ $item['value'] }}</span>
                        {{ $item['label'] }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    @if (empty($markers))
        <div class="grid min-h-64 place-items-center bg-gray-50 px-6 py-12 text-center dark:bg-white/5">
            <div>
                <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $data['emptyTitle'] ?? 'No coordinates available yet' }}</div>
                <p class="mt-1 max-w-xl text-sm text-gray-500 dark:text-gray-400">
                    {{ $data['emptyDescription'] ?? 'Add coordinates to this record or one of its related records to show the map.' }}
                </p>
            </div>
        </div>
    @else
        <div class="grid min-h-[30rem] grid-cols-1 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="relative min-h-[30rem]">
                <iframe
                    title="{{ $data['title'] }}"
                    src="{{ $mapUrl }}"
                    class="absolute inset-0 h-full w-full border-0"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
                <div class="pointer-events-none absolute inset-0 z-[350] bg-gradient-to-b from-gray-950/5 via-transparent to-gray-950/10"></div>
                @foreach ($displayMarkers as $marker)
                    @php
                        $style = $typeStyles[$marker['type']] ?? ['label' => ucfirst(str_replace('-', ' ', $marker['type'])), 'class' => 'bg-primary-500'];
                        $markerTag = ! empty($marker['url']) ? 'a' : 'span';
                    @endphp
                    <{{ $markerTag }}
                        @if (! empty($marker['url'])) href="{{ $marker['url'] }}" @endif
                        title="{{ $marker['label'] }}"
                        class="absolute z-[410] grid h-7 w-7 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border-2 border-white text-[0.65rem] font-bold text-white shadow-lg ring-4 ring-white/30 transition hover:scale-110 {{ $style['class'] }}"
                        style="left: {{ number_format($marker['left'], 4, '.', '') }}%; top: {{ number_format($marker['top'], 4, '.', '') }}%;"
                    >
                        {{ strtoupper(substr($style['label'], 0, 1)) }}
                    </{{ $markerTag }}>
                @endforeach
                @if (! empty($legend))
                    <div class="pointer-events-none absolute bottom-4 left-4 z-[400] flex max-w-[calc(100%-2rem)] flex-wrap gap-2">
                        @foreach ($legend as $item)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-950/80 px-2.5 py-1 text-xs font-medium text-white shadow-sm">
                                <span class="h-2.5 w-2.5 rounded-full {{ $item['class'] }}"></span>
                                {{ $item['label'] }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="border-t border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5 xl:border-l xl:border-t-0">
                <div class="space-y-3">
                    @foreach ($markers as $marker)
                        @php
                            $style = $typeStyles[$marker['type']] ?? ['label' => ucfirst(str_replace('-', ' ', $marker['type'])), 'class' => 'bg-primary-500'];
                        @endphp
                        <a
                            @if (! empty($marker['url'])) href="{{ $marker['url'] }}" @endif
                            class="block rounded-lg border border-gray-200 bg-white p-3 transition hover:border-primary-300 hover:shadow-sm dark:border-white/10 dark:bg-gray-900"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $marker['label'] }}</div>
                                    @if (! empty($marker['description']))
                                        <div class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $marker['description'] }}</div>
                                    @endif
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[0.65rem] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $style['class'] }}"></span>
                                    {{ $style['label'] }}
                                </span>
                            </div>
                            <div class="mt-2 font-mono text-[0.7rem] text-gray-400">
                                {{ number_format((float) $marker['lat'], 6) }}, {{ number_format((float) $marker['lng'], 6) }}
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

</div>
