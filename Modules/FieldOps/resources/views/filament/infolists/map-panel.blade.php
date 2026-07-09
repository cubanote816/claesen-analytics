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
    $id = 'fieldops-map-'.md5(json_encode($data));
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

    <div style="display: grid; grid-template-columns: minmax(0, 1fr) 360px; min-height: 540px;">
        <div
            style="position: relative; min-height: 540px; overflow: hidden; background: linear-gradient(180deg, #eff7fb, #dbe8ee);"
            wire:ignore
            x-data="fieldopsMapPanel(@js($mapConfig))"
            x-init="init()"
        >
            @if ($hasMap)
                <div x-ref="map" style="height: 100%; min-height: 540px;"></div>
            @else
                <div style="display: grid; min-height: 540px; place-items: center; padding: 48px 24px; text-align: center; background: radial-gradient(circle at center, #effbff 0%, #f8fafc 62%, #ffffff 100%);">
                    <div style="max-width: 560px;">
                        <div style="color: #0f172a; font-size: 15px; font-weight: 800;">{{ $data['emptyTitle'] ?? 'No coordinates available yet' }}</div>
                        <p style="margin: 6px 0 0; color: #64748b; font-size: 13px; line-height: 1.55;">
                            {{ $data['emptyDescription'] ?? 'Add coordinates to this record or one of its related records to show the map.' }}
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <div style="display: flex; flex-direction: column; min-height: 540px; max-height: 540px; border-left: 1px solid #dbe5ed; background: #f8fbfd;">
            <div style="padding: 18px 18px 12px; border-bottom: 1px solid #e5edf3;">
                <div style="color: #0f172a; font-size: 14px; font-weight: 850;">Map objects</div>
                <div style="margin-top: 3px; color: #64748b; font-size: 12px;">Everything related to this record, including items without coordinates.</div>
            </div>
            <div style="display: flex; flex: 1; flex-direction: column; gap: 10px; overflow-y: auto; padding: 14px;">
                @foreach ($items as $item)
                    @php
                        $style = $typeStyles[$item['type']] ?? ['label' => ucfirst(str_replace('-', ' ', $item['type'])), 'color' => '#64748b', 'text' => '#ffffff', 'initial' => 'P'];
                        $hasCoordinates = is_numeric($item['lat'] ?? null) && is_numeric($item['lng'] ?? null);
                    @endphp
                    @if (! empty($item['url']))
                        <a
                            href="{{ $item['url'] }}"
                            style="display: block; border: 1px solid #dbe5ed; border-radius: 14px; background: #ffffff; padding: 13px; color: inherit; text-decoration: none; box-shadow: 0 8px 20px rgba(15,23,42,0.045);"
                        >
                    @else
                        <div style="display: block; border: 1px solid #dbe5ed; border-radius: 14px; background: #ffffff; padding: 13px; color: inherit; text-decoration: none; box-shadow: 0 8px 20px rgba(15,23,42,0.045);">
                    @endif
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
                                <div style="min-width: 0;">
                                    <div style="overflow: hidden; color: #0f172a; font-size: 13px; font-weight: 850; text-overflow: ellipsis; white-space: nowrap;">{{ $item['label'] }}</div>
                                    @if (! empty($item['description']))
                                        <div style="margin-top: 3px; overflow: hidden; color: #64748b; font-size: 12px; text-overflow: ellipsis; white-space: nowrap;">{{ $item['description'] }}</div>
                                    @endif
                                </div>
                                <span style="display: inline-flex; flex-shrink: 0; align-items: center; gap: 6px; padding: 5px 8px; border-radius: 999px; background: #eef4f7; color: #475569; font-size: 11px; font-weight: 800;">
                                    <span style="width: 7px; height: 7px; border-radius: 999px; background: {{ $style['color'] }};"></span>
                                    {{ $style['label'] }}
                                </span>
                            </div>
                            <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; gap: 12px; color: #94a3b8; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px;">
                                <span>
                                    @if ($hasCoordinates)
                                        {{ number_format((float) $item['lat'], 6) }}, {{ number_format((float) $item['lng'], 6) }}
                                    @else
                                        No coordinates yet
                                    @endif
                                </span>
                                <span style="display: inline-flex; align-items: center; border-radius: 999px; background: {{ $hasCoordinates ? '#e6f7fd' : '#eef2f7' }}; padding: 3px 8px; color: {{ $hasCoordinates ? '#008fc8' : '#64748b' }}; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;">
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

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap contributors',
                        }).addTo(map);

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

                        setTimeout(() => map.invalidateSize(), 0);
                    },
                };
            };
        })();
    </script>
@endonce
