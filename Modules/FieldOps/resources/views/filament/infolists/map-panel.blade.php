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
        'complex' => ['label' => 'Complex', 'color' => '#e6007e', 'text' => '#ffffff', 'initial' => 'C'],
        'terrain' => ['label' => 'Terrain', 'color' => '#a5d610', 'text' => '#102014', 'initial' => 'T'],
        'structure' => ['label' => 'Structure', 'color' => '#f59e0b', 'text' => '#111827', 'initial' => 'S'],
        'electrical-board' => ['label' => 'Electrical board', 'color' => '#00aeef', 'text' => '#ffffff', 'initial' => 'E'],
    ];

    $legend = collect($markers)
        ->pluck('type')
        ->unique()
        ->map(fn ($type) => $typeStyles[$type] ?? ['label' => ucfirst(str_replace('-', ' ', $type)), 'color' => '#64748b', 'text' => '#ffffff', 'initial' => 'P'])
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

<div class="fieldops-map-panel-root" style="width: 100%;">
<div
    id="{{ $id }}"
    class="fieldops-map-panel"
    style="overflow: hidden; border: 1px solid #d9e2ea; border-radius: 18px; background: #ffffff; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.09);"
    data-fieldops-map-panel
>
    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding: 20px 24px; border-bottom: 1px solid #dbe5ed; background: linear-gradient(135deg, #ffffff 0%, #f6fbfe 58%, #edf8fd 100%);">
        <div style="min-width: 0;">
            <div style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: 8px; color: #008fc8; font-size: 11px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase;">
                <span style="display: inline-block; width: 24px; height: 3px; border-radius: 999px; background: #00aeef;"></span>
                FieldOps map
            </div>
            <h3 style="margin: 0; color: #0f172a; font-size: 20px; font-weight: 800; letter-spacing: -0.02em;">{{ $data['title'] }}</h3>
            @if (! empty($data['subtitle']))
                <p style="margin: 6px 0 0; max-width: 680px; color: #64748b; font-size: 13px; line-height: 1.5;">{{ $data['subtitle'] }}</p>
            @endif
        </div>

        @if (! empty($data['summary']))
            <div style="display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px;">
                @foreach ($data['summary'] as $item)
                    <span style="display: inline-flex; align-items: center; gap: 8px; min-height: 34px; padding: 6px 11px; border: 1px solid #cfeaf6; border-radius: 999px; background: rgba(255,255,255,0.86); color: #475569; font-size: 12px; font-weight: 700; box-shadow: 0 8px 20px rgba(0, 174, 239, 0.07);">
                        <span style="display: inline-grid; min-width: 24px; height: 24px; place-items: center; border-radius: 999px; background: #e6f7fd; color: #008fc8; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 900;">{{ $item['value'] }}</span>
                        <span>{{ $item['label'] }}</span>
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    @if (empty($markers))
        <div style="display: grid; min-height: 360px; place-items: center; padding: 48px 24px; text-align: center; background: radial-gradient(circle at center, #effbff 0%, #f8fafc 62%, #ffffff 100%);">
            <div style="max-width: 560px;">
                <div style="color: #0f172a; font-size: 15px; font-weight: 800;">{{ $data['emptyTitle'] ?? 'No coordinates available yet' }}</div>
                <p style="margin: 6px 0 0; color: #64748b; font-size: 13px; line-height: 1.55;">
                    {{ $data['emptyDescription'] ?? 'Add coordinates to this record or one of its related records to show the map.' }}
                </p>
            </div>
        </div>
    @else
        <div style="display: grid; grid-template-columns: minmax(0, 1fr) 360px; min-height: 540px;">
            <div style="position: relative; min-height: 540px; overflow: hidden; background: #dbe8ee;">
                <iframe
                    title="{{ $data['title'] }}"
                    src="{{ $mapUrl }}"
                    style="position: absolute; inset: 0; width: 100%; height: 100%; border: 0; filter: saturate(0.96) contrast(1.02);"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
                <div style="pointer-events: none; position: absolute; inset: 0; z-index: 2; background: linear-gradient(180deg, rgba(15, 23, 42, 0.06), transparent 40%, rgba(15, 23, 42, 0.13));"></div>
                <div style="pointer-events: none; position: absolute; inset: 18px; z-index: 3; border: 1px solid rgba(255,255,255,0.48); border-radius: 18px; box-shadow: inset 0 0 0 1px rgba(15,23,42,0.08);"></div>
                @foreach ($displayMarkers as $marker)
                    @php
                        $style = $typeStyles[$marker['type']] ?? ['label' => ucfirst(str_replace('-', ' ', $marker['type'])), 'color' => '#64748b', 'text' => '#ffffff', 'initial' => 'P'];
                        $markerTag = ! empty($marker['url']) ? 'a' : 'span';
                    @endphp
                    <{{ $markerTag }}
                        @if (! empty($marker['url'])) href="{{ $marker['url'] }}" @endif
                        title="{{ $marker['label'] }}"
                        style="position: absolute; z-index: 5; left: {{ number_format($marker['left'], 4, '.', '') }}%; top: {{ number_format($marker['top'], 4, '.', '') }}%; display: grid; width: 34px; height: 34px; place-items: center; transform: translate(-50%, -50%); border: 3px solid #ffffff; border-radius: 999px; background: {{ $style['color'] }}; color: {{ $style['text'] }}; font-size: 12px; font-weight: 900; text-decoration: none; box-shadow: 0 12px 26px rgba(15, 23, 42, 0.28), 0 0 0 6px rgba(255,255,255,0.28);"
                    >
                        {{ $style['initial'] }}
                    </{{ $markerTag }}>
                @endforeach
                @if (! empty($legend))
                    <div style="pointer-events: none; position: absolute; left: 28px; bottom: 26px; z-index: 6; display: flex; max-width: calc(100% - 56px); flex-wrap: wrap; gap: 8px;">
                        @foreach ($legend as $item)
                            <span style="display: inline-flex; align-items: center; gap: 7px; padding: 8px 10px; border: 1px solid rgba(255,255,255,0.22); border-radius: 999px; background: rgba(15,23,42,0.82); color: #ffffff; font-size: 12px; font-weight: 750; box-shadow: 0 10px 24px rgba(15,23,42,0.22); backdrop-filter: blur(8px);">
                                <span style="width: 10px; height: 10px; border-radius: 999px; background: {{ $item['color'] }};"></span>
                                {{ $item['label'] }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div style="display: flex; flex-direction: column; min-height: 540px; max-height: 540px; border-left: 1px solid #dbe5ed; background: #f8fbfd;">
                <div style="padding: 18px 18px 12px; border-bottom: 1px solid #e5edf3;">
                    <div style="color: #0f172a; font-size: 14px; font-weight: 850;">Map objects</div>
                    <div style="margin-top: 3px; color: #64748b; font-size: 12px;">Click an item to open its detail page when available.</div>
                </div>
                <div style="display: flex; flex: 1; flex-direction: column; gap: 10px; overflow-y: auto; padding: 14px;">
                    @foreach ($markers as $marker)
                        @php
                            $style = $typeStyles[$marker['type']] ?? ['label' => ucfirst(str_replace('-', ' ', $marker['type'])), 'color' => '#64748b', 'text' => '#ffffff', 'initial' => 'P'];
                        @endphp
                        <a
                            @if (! empty($marker['url'])) href="{{ $marker['url'] }}" @endif
                            style="display: block; border: 1px solid #dbe5ed; border-radius: 14px; background: #ffffff; padding: 13px; color: inherit; text-decoration: none; box-shadow: 0 8px 20px rgba(15,23,42,0.045);"
                        >
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
                                <div style="min-width: 0;">
                                    <div style="overflow: hidden; color: #0f172a; font-size: 13px; font-weight: 850; text-overflow: ellipsis; white-space: nowrap;">{{ $marker['label'] }}</div>
                                    @if (! empty($marker['description']))
                                        <div style="margin-top: 3px; overflow: hidden; color: #64748b; font-size: 12px; text-overflow: ellipsis; white-space: nowrap;">{{ $marker['description'] }}</div>
                                    @endif
                                </div>
                                <span style="display: inline-flex; flex-shrink: 0; align-items: center; gap: 6px; padding: 5px 8px; border-radius: 999px; background: #eef4f7; color: #475569; font-size: 11px; font-weight: 800;">
                                    <span style="width: 7px; height: 7px; border-radius: 999px; background: {{ $style['color'] }};"></span>
                                    {{ $style['label'] }}
                                </span>
                            </div>
                            <div style="margin-top: 10px; color: #94a3b8; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px;">
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
