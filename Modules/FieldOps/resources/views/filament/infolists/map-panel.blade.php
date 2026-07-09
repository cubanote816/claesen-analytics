@php
    /**
     * @var array{
     *     title: string,
     *     subtitle?: string,
     *     emptyTitle?: string,
     *     emptyDescription?: string,
     *     items?: array<int, array{type: string, label: string, description?: ?string, lat: float|int|string|null, lng: float|int|string|null, hasCoordinates?: bool, url?: ?string}>,
     *     markers: array<int, array{type: string, label: string, description?: ?string, lat: float|int|string|null, lng: float|int|string|null, url?: ?string}>,
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
                            href="{{ $item['url'] }}"
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
                                    ? '#a5d610'
                                    : marker.type === 'structure'
                                        ? '#f59e0b'
                                        : '#00aeef';
                            const initial = marker.initial || (marker.label ? marker.label.charAt(0).toUpperCase() : 'P');
                            const icon = L.divIcon({
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
                                    window.location.href = marker.url;
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
