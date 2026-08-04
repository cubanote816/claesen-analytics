@php
    /**
     * @var array{
     *     title: string,
     *     subtitle?: string,
     *     emptyTitle?: string,
     *     emptyDescription?: string,
     *     items?: array<int, array{type: string, label: string, description?: ?string, lat: float|int|string|null, lng: float|int|string|null, hasCoordinates?: bool, url?: ?string}>,
     *     markers: array<int, array{type: string, label: string, description?: ?string, terrainTypeCode?: ?string, terrainTypeColor?: ?string, structureTypeCode?: ?string, structureTypeColor?: ?string, lat: float|int|string|null, lng: float|int|string|null, url?: ?string}>,
     *     summary?: array<int, array{label: string, value: int|string}>,
     * } $data
    */
    $data = $getState();
    $items = collect($data['items'] ?? $data['markers'] ?? [])
        ->values()
        ->all();

    $markers = collect($data['markers'] ?? [])
        ->filter(fn ($marker) => is_numeric($marker['lat'] ?? null) && is_numeric($marker['lng'] ?? null))
        ->values()
        ->all();

    $hasMap = ! empty($markers);
    $mapConfig = [
        'markers' => $markers,
    ];

    $typeStyles = [
        'complex' => ['label' => 'Complex', 'color' => '#e6007e', 'text' => '#ffffff', 'initial' => 'C'],
        'terrain' => ['label' => 'Terrain', 'color' => '#a5d610', 'text' => '#102014', 'initial' => 'T'],
        'structure' => ['label' => 'Structure', 'color' => '#f59e0b', 'text' => '#111827', 'initial' => 'S'],
        'electrical-board' => ['label' => 'Electrical board', 'color' => '#00aeef', 'text' => '#ffffff', 'initial' => 'E'],
    ];

@endphp

@once
    <style>
        .fieldops-map-panel {
            overflow: hidden;
            border: 1px solid #d9e2ea;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.09);
        }

        .fieldops-map-panel__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            padding: 20px 24px;
            border-bottom: 1px solid #dbe5ed;
            background: linear-gradient(135deg, #ffffff 0%, #f6fbfe 58%, #edf8fd 100%);
        }

        .fieldops-map-panel__header-copy {
            min-width: 0;
        }

        .fieldops-map-panel__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: #008fc8;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .fieldops-map-panel__eyebrow-bar {
            display: inline-block;
            width: 24px;
            height: 3px;
            border-radius: 999px;
            background: #00aeef;
        }

        .fieldops-map-panel__title {
            margin: 0;
            color: #0f172a;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .fieldops-map-panel__subtitle {
            margin: 6px 0 0;
            max-width: 680px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .fieldops-map-panel__summary {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .fieldops-map-panel__summary-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            padding: 6px 11px;
            border: 1px solid #cfeaf6;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.86);
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(0, 174, 239, 0.07);
        }

        .fieldops-map-panel__summary-value {
            display: inline-grid;
            min-width: 24px;
            height: 24px;
            place-items: center;
            border-radius: 999px;
            background: #e6f7fd;
            color: #008fc8;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-weight: 900;
        }

        .fieldops-map-panel__body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            min-height: 540px;
        }

        .fieldops-map-panel__map-shell {
            position: relative;
            min-height: 540px;
            overflow: hidden;
            background: linear-gradient(180deg, #eff7fb, #dbe8ee);
        }

        .fieldops-map-panel__map {
            height: 540px;
            min-height: 540px;
            width: 100%;
        }

        .fieldops-map-panel__empty {
            display: grid;
            min-height: 540px;
            place-items: center;
            padding: 48px 24px;
            text-align: center;
            background: radial-gradient(circle at center, #effbff 0%, #f8fafc 62%, #ffffff 100%);
        }

        .fieldops-map-panel__empty-copy {
            max-width: 560px;
        }

        .fieldops-map-panel__empty-title {
            color: #0f172a;
            font-size: 15px;
            font-weight: 800;
        }

        .fieldops-map-panel__empty-text {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.55;
        }

        .fieldops-map-panel__rail {
            display: flex;
            flex-direction: column;
            min-height: 540px;
            max-height: 540px;
            border-left: 1px solid #dbe5ed;
            background: #f8fbfd;
        }

        .fieldops-map-panel__rail-header {
            padding: 18px 18px 12px;
            border-bottom: 1px solid #e5edf3;
        }

        .fieldops-map-panel__rail-title {
            color: #0f172a;
            font-size: 14px;
            font-weight: 850;
        }

        .fieldops-map-panel__rail-subtitle {
            margin-top: 3px;
            color: #64748b;
            font-size: 12px;
        }

        .fieldops-map-panel__rail-list {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
            padding: 14px;
        }

        .fieldops-map-panel__item {
            display: block;
            border: 1px solid #dbe5ed;
            border-radius: 14px;
            background: #ffffff;
            padding: 13px;
            color: inherit;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.045);
        }

        .fieldops-map-panel__item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .fieldops-map-panel__item-copy {
            min-width: 0;
        }

        .fieldops-map-panel__item-title {
            overflow: hidden;
            color: #0f172a;
            font-size: 13px;
            font-weight: 850;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .fieldops-map-panel__item-description {
            margin-top: 3px;
            overflow: hidden;
            color: #64748b;
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .fieldops-map-panel__badge {
            display: inline-flex;
            flex-shrink: 0;
            align-items: center;
            gap: 6px;
            padding: 5px 8px;
            border-radius: 999px;
            background: #eef4f7;
            color: #475569;
            font-size: 11px;
            font-weight: 800;
        }

        .fieldops-map-panel__badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
        }

        .fieldops-map-panel__item-bottom {
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: #94a3b8;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
        }

        .fieldops-map-panel__coords {
            min-width: 0;
        }

        .fieldops-map-panel__state {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .fieldops-map-panel__state.is-pinned {
            background: #e6f7fd;
            color: #008fc8;
        }

        .fieldops-map-panel__state.is-unmapped {
            background: #eef2f7;
            color: #64748b;
        }

        .fieldops-map-panel .leaflet-container {
            background: #dbe8ee;
        }

        .fieldops-map-panel .leaflet-control-zoom,
        .fieldops-map-panel .leaflet-control-attribution {
            border: 1px solid rgba(255, 255, 255, 0.65);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.16);
        }

        .fieldops-map-panel .leaflet-control-zoom a {
            background: rgba(255, 255, 255, 0.92);
            color: #0f172a;
        }

        .fieldops-map-panel .leaflet-control-attribution {
            background: rgba(255, 255, 255, 0.84);
            color: #334155;
        }

        .fieldops-map-panel[data-theme="dark"] {
            border-color: #2a3240;
            background: #10151d;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.45);
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__header {
            border-bottom-color: #243042;
            background: linear-gradient(135deg, #111827 0%, #0f172a 58%, #101d2b 100%);
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__eyebrow {
            color: #80dfff;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__title {
            color: #f8fafc;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__subtitle {
            color: #94a3b8;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__summary-pill {
            border-color: #2a4455;
            background: rgba(15, 23, 42, 0.82);
            color: #dbeafe;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.28);
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__summary-value {
            background: #12364a;
            color: #80dfff;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__map-shell {
            background: linear-gradient(180deg, #0f172a, #111827);
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__rail {
            border-left-color: #243042;
            background: #0f172a;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__rail-header {
            border-bottom-color: #1f2a39;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__rail-title {
            color: #f8fafc;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__rail-subtitle {
            color: #94a3b8;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__item {
            border-color: #243042;
            background: #111827;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__item-title {
            color: #f8fafc;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__item-description {
            color: #94a3b8;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__badge {
            background: #1f2a39;
            color: #e2e8f0;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__item-bottom {
            color: #94a3b8;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__state.is-pinned {
            background: #12364a;
            color: #80dfff;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__state.is-unmapped {
            background: #1f2a39;
            color: #94a3b8;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__empty {
            background: radial-gradient(circle at center, #111827 0%, #0f172a 62%, #0b0f17 100%);
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__empty-title {
            color: #f8fafc;
        }

        .fieldops-map-panel[data-theme="dark"] .fieldops-map-panel__empty-text {
            color: #94a3b8;
        }

        .fieldops-map-panel[data-theme="dark"] .leaflet-control-zoom a,
        .fieldops-map-panel[data-theme="dark"] .leaflet-control-attribution {
            background: rgba(15, 23, 42, 0.82);
            color: #e2e8f0;
            border-color: rgba(255, 255, 255, 0.1);
        }
    </style>
@endonce

<div class="fieldops-map-panel-root" style="width: 100%;">
<div
    class="fieldops-map-panel"
    data-fieldops-map-panel
>
    <div class="fieldops-map-panel__header">
        <div class="fieldops-map-panel__header-copy">
            <div class="fieldops-map-panel__eyebrow">
                <span class="fieldops-map-panel__eyebrow-bar"></span>
                FieldOps map
            </div>
            <h3 class="fieldops-map-panel__title">{{ $data['title'] }}</h3>
            @if (! empty($data['subtitle']))
                <p class="fieldops-map-panel__subtitle">{{ $data['subtitle'] }}</p>
            @endif
        </div>

        @if (! empty($data['summary']))
            <div class="fieldops-map-panel__summary">
                @foreach ($data['summary'] as $item)
                    <span class="fieldops-map-panel__summary-pill">
                        <span class="fieldops-map-panel__summary-value">{{ $item['value'] }}</span>
                        <span>{{ $item['label'] }}</span>
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="fieldops-map-panel__body">
        <div
            class="fieldops-map-panel__map-shell"
            wire:ignore
            x-data="fieldopsMapPanel(@js($mapConfig))"
            x-init="init()"
        >
            @if ($hasMap)
                <div x-ref="map" class="fieldops-map-panel__map"></div>
            @else
                <div class="fieldops-map-panel__empty">
                    <div class="fieldops-map-panel__empty-copy">
                        <div class="fieldops-map-panel__empty-title">{{ $data['emptyTitle'] ?? 'No coordinates available yet' }}</div>
                        <p class="fieldops-map-panel__empty-text">
                            {{ $data['emptyDescription'] ?? 'Add coordinates to this record or one of its related records to show the map.' }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <div class="fieldops-map-panel__rail">
            <div class="fieldops-map-panel__rail-header">
                <div class="fieldops-map-panel__rail-title">Map objects</div>
                <div class="fieldops-map-panel__rail-subtitle">Everything related to this record, including items without coordinates.</div>
            </div>
            <div class="fieldops-map-panel__rail-list">
                @foreach ($items as $item)
                    @php
                        $style = $typeStyles[$item['type']] ?? ['label' => ucfirst(str_replace('-', ' ', $item['type'])), 'color' => '#64748b', 'text' => '#ffffff', 'initial' => 'P'];
                        $hasCoordinates = is_numeric($item['lat'] ?? null) && is_numeric($item['lng'] ?? null);
                    @endphp
                    @if (! empty($item['url']))
                        <a
                            {{ \Filament\Support\generate_href_html($item['url']) }}
                            class="fieldops-map-panel__item"
                        >
                    @else
                        <div class="fieldops-map-panel__item">
                    @endif
                            <div class="fieldops-map-panel__item-top">
                                <div class="fieldops-map-panel__item-copy">
                                    <div class="fieldops-map-panel__item-title">{{ $item['label'] }}</div>
                                    @if (! empty($item['description']))
                                        <div class="fieldops-map-panel__item-description">{{ $item['description'] }}</div>
                                    @endif
                                </div>
                                <span class="fieldops-map-panel__badge">
                                    <span class="fieldops-map-panel__badge-dot" style="background: {{ $style['color'] }};"></span>
                                    {{ $style['label'] }}
                                </span>
                            </div>
                            <div class="fieldops-map-panel__item-bottom">
                                <span class="fieldops-map-panel__coords">
                                    @if ($hasCoordinates)
                                        {{ number_format((float) $item['lat'], 6) }}, {{ number_format((float) $item['lng'], 6) }}
                                    @else
                                        No coordinates yet
                                    @endif
                                </span>
                                <span class="fieldops-map-panel__state {{ $hasCoordinates ? 'is-pinned' : 'is-unmapped' }}">
                                    {{ $hasCoordinates ? 'Pinned' : 'Unmapped' }}
                                </span>
                            </div>
                    @if (! empty($item['url']))
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>

</div>

@once
    <script>
        (function () {
            if (window.fieldopsMapPanel) {
                return;
            }

            const buildTerrainMarkerSvg = (code, color) => {
                const fill = color || '#e6007e';

                switch (code) {
@foreach (\Modules\FieldOps\Support\TerrainPinCatalog::definitions() as $pin)
                    case '{{ $pin['code'] }}':
                        return `{!! trim($pin['svg']) !!}`;
@endforeach
                    default:
                        return `
                            <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 4C14.48 4 10 8.48 10 14C10 21.5 20 36 20 36C20 36 30 21.5 30 14C30 8.48 25.52 4 20 4Z"
                                      fill="${fill}"
                                      stroke="white"
                                      stroke-width="1"/>
                                <line x1="16.5" y1="10" x2="16.5" y2="21" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
                                <path d="M16.5,10 L23,12.7 L16.5,15.4 Z" fill="white"/>
                            </svg>
                        `;
                }
            };

            const buildStructureMarkerSvg = (code) => {
                switch (code) {
@foreach (\Modules\FieldOps\Support\StructurePinCatalog::definitions() as $pin)
                    case '{{ $pin['code'] }}':
                        return `{!! trim($pin['svg']) !!}`;
@endforeach
                    default:
                        return `{!! trim(\Modules\FieldOps\Support\StructurePinCatalog::fallbackSvg()) !!}`;
                }
            };

            const buildElectricalBoardMarkerSvg = () => `{!! trim(\Modules\FieldOps\Support\ElectricalBoardPinCatalog::svg()) !!}`;

            window.fieldopsLoadLeaflet = function () {
                if (window.L) {
                    return Promise.resolve(window.L);
                }

                if (window.__fieldopsLeafletPromise) {
                    return window.__fieldopsLeafletPromise;
                }

                window.__fieldopsLeafletPromise = new Promise((resolve, reject) => {
                    const cssId = 'fieldops-leaflet-css';
                    if (!document.getElementById(cssId)) {
                        const link = document.createElement('link');
                        link.id = cssId;
                        link.rel = 'stylesheet';
                        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                        document.head.appendChild(link);
                    }

                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    script.async = true;
                    script.onload = () => resolve(window.L);
                    script.onerror = reject;
                    document.head.appendChild(script);
                });

                return window.__fieldopsLeafletPromise;
            };

            window.fieldopsMapPanel = function (config) {
                return {
                    markerLayer: null,
                    baseLayer: null,

                    async init() {
                        if (! this.$refs.map) {
                            return;
                        }

                        if (this.$refs.map.dataset.fieldopsInitialized === '1') {
                            return;
                        }

                        await window.fieldopsLoadLeaflet();

                        if (this.$refs.map.dataset.fieldopsInitialized === '1') {
                            return;
                        }

                        this.$refs.map.dataset.fieldopsInitialized = '1';

                        const map = L.map(this.$refs.map, {
                            scrollWheelZoom: false,
                            zoomControl: false,
                            attributionControl: true,
                        });

                        L.control.zoom({ position: 'topright' }).addTo(map);

                        this.baseLayer = L.tileLayer(
                            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                            {
                                maxZoom: 19,
                                attribution: 'Tiles &copy; Esri',
                            }
                        ).addTo(map);

                        L.tileLayer(
                            'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
                            {
                                maxZoom: 19,
                                attribution: 'Labels &copy; Esri',
                                opacity: 0.9,
                            }
                        ).addTo(map);

                        const markers = Array.isArray(config.markers) ? config.markers : [];

                        if (markers.length > 0) {
                            const bounds = L.latLngBounds(markers.map((marker) => [Number(marker.lat), Number(marker.lng)]));
                            map.fitBounds(bounds.pad(markers.length === 1 ? 0.22 : 0.32), {
                                maxZoom: markers.length === 1 ? 17 : 16,
                            });
                        } else {
                            map.setView([51.1635, 5.1640], 16);
                        }

                        markers.forEach((marker) => {
                            const accent = marker.type === 'complex'
                                ? '#e6007e'
                                : marker.type === 'terrain'
                                    ? (marker.terrainTypeColor || '#a5d610')
                                    : marker.type === 'structure'
                                        ? '#f59e0b'
                                        : '#00aeef';
                            const initial = marker.initial || (marker.label ? marker.label.charAt(0).toUpperCase() : 'P');
                            const icon = marker.type === 'terrain' && marker.terrainTypeCode
                                ? L.icon({
                                    iconUrl: `data:image/svg+xml;charset=utf-8,${encodeURIComponent(buildTerrainMarkerSvg(marker.terrainTypeCode, accent))}`,
                                    iconSize: [40, 40],
                                    iconAnchor: [20, 40],
                                    popupAnchor: [0, -12],
                                })
                                : marker.type === 'structure'
                                ? L.icon({
                                    iconUrl: `data:image/svg+xml;charset=utf-8,${encodeURIComponent(buildStructureMarkerSvg(marker.structureTypeCode))}`,
                                    iconSize: [40, 57],
                                    iconAnchor: [20, 56],
                                    popupAnchor: [0, -30],
                                })
                                : marker.type === 'electrical-board'
                                ? L.icon({
                                    iconUrl: `data:image/svg+xml;charset=utf-8,${encodeURIComponent(buildElectricalBoardMarkerSvg())}`,
                                    iconSize: [40, 40],
                                    iconAnchor: [20, 36],
                                    popupAnchor: [0, -12],
                                })
                                : L.divIcon({
                                    className: 'fieldops-map-pin',
                                    html: '<span style="display:grid;width:34px;height:34px;place-items:center;border:3px solid #fff;border-radius:999px;background:' + accent + ';color:' + (marker.type === 'terrain' ? '#102014' : '#fff') + ';font-size:12px;font-weight:900;box-shadow:0 12px 26px rgba(15,23,42,.28),0 0 0 6px rgba(255,255,255,.28);">' + initial + '</span>',
                                    iconSize: [34, 34],
                                    iconAnchor: [17, 17],
                                    popupAnchor: [0, -12],
                                });

                            const leafletMarker = L.marker([Number(marker.lat), Number(marker.lng)], { icon }).addTo(map);

                            if (marker.label) {
                                leafletMarker.bindTooltip(marker.label, {
                                    direction: 'top',
                                    offset: [0, -12],
                                    opacity: 0.95,
                                });
                            }

                            if (marker.url) {
                                leafletMarker.on('click', () => {
                                    window.Alpine.navigate(marker.url);
                                });
                            }
                        });

                        this.syncTheme(map);
                        this.observeTheme(map);
                        setTimeout(() => map.invalidateSize(), 0);
                    },

                    syncTheme(map) {
                        const isDark = document.documentElement.classList.contains('dark');
                        const container = this.$el.closest('.fieldops-map-panel');

                        if (container) {
                            container.dataset.theme = isDark ? 'dark' : 'light';
                        }

                        if (map && map._controlCorners) {
                            const corners = map._controlCorners;
                            Object.values(corners).forEach((corner) => {
                                if (corner) {
                                    corner.classList.toggle('fieldops-map-panel__leaflet-corners--dark', isDark);
                                }
                            });
                        }
                    },

                    observeTheme(map) {
                        if (this._themeObserver) {
                            return;
                        }

                        const observer = new MutationObserver(() => {
                            this.syncTheme(map);
                        });

                        observer.observe(document.documentElement, {
                            attributes: true,
                            attributeFilter: ['class'],
                        });

                        this._themeObserver = observer;
                    },
                };
            };
        })();
    </script>
@endonce
