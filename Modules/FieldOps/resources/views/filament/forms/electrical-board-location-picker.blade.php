@php
    /**
     * @var array{
     *     complexLabel?: ?string,
     *     defaultLat: float|int|string,
     *     defaultLng: float|int|string,
     *     defaultZoom: int,
     *     latInputId: string,
     *     lngInputId: string,
     *     centerLatInputId: string,
     *     centerLngInputId: string,
     * } $data
     */
    $data = array_merge([
        'complexLabel' => null,
        'defaultLat' => 51.1635,
        'defaultLng' => 5.1640,
        'defaultZoom' => 16,
        'latInputId' => 'form.lat',
        'lngInputId' => 'form.lng',
        'centerLatInputId' => 'form.map_center_lat',
        'centerLngInputId' => 'form.map_center_lng',
    ], $getViewData());
@endphp

<div
    class="fieldops-electrical-board-location-picker"
    wire:ignore
    x-data="fieldopsElectricalBoardLocationPicker({
        latInputId: @js($data['latInputId']),
        lngInputId: @js($data['lngInputId']),
        centerLatInputId: @js($data['centerLatInputId']),
        centerLngInputId: @js($data['centerLngInputId']),
        defaultLat: @js($data['defaultLat']),
        defaultLng: @js($data['defaultLng']),
        defaultZoom: @js($data['defaultZoom']),
        complexLabel: @js($data['complexLabel']),
    })"
    x-init="init()"
>
    @once
        @push('styles')
            <style>
        .fieldops-electrical-board-location-picker {
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.25rem;
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.08), transparent 35%),
                linear-gradient(180deg, #ffffff 0%, #f7fbfe 100%);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .dark .fieldops-electrical-board-location-picker {
            border-color: rgba(255, 255, 255, 0.08);
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.14), transparent 30%),
                linear-gradient(180deg, #171725 0%, #11111a 100%);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 24px 60px -18px rgba(0, 0, 0, 0.62);
        }

        .fieldops-electrical-board-location-picker__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.35rem 1rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
        }

        .dark .fieldops-electrical-board-location-picker__header {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .fieldops-electrical-board-location-picker__eyebrow {
            color: #009bd6;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .fieldops-electrical-board-location-picker__title {
            margin-top: 0.35rem;
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .dark .fieldops-electrical-board-location-picker__title {
            color: #f8fafc;
        }

        .fieldops-electrical-board-location-picker__description {
            margin-top: 0.3rem;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .dark .fieldops-electrical-board-location-picker__description {
            color: #94a3b8;
        }

        .fieldops-electrical-board-location-picker__coords {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.45rem;
            min-width: 11rem;
            text-align: right;
        }

        .fieldops-electrical-board-location-picker__coords-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2rem;
            padding: 0.35rem 0.75rem;
            border: 1px solid rgba(0, 174, 239, 0.22);
            border-radius: 999px;
            background: rgba(0, 174, 239, 0.08);
            color: #006f9b;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.88rem;
            font-weight: 800;
        }

        .dark .fieldops-electrical-board-location-picker__coords-pill {
            border-color: rgba(0, 174, 239, 0.24);
            background: rgba(0, 174, 239, 0.12);
            color: #7dd3fc;
        }

        .fieldops-electrical-board-location-picker__hint {
            color: #64748b;
            font-size: 0.78rem;
            line-height: 1.4;
        }

        .dark .fieldops-electrical-board-location-picker__hint {
            color: #94a3b8;
        }

        .fieldops-electrical-board-location-picker__map {
            position: relative;
            z-index: 0;
            height: 480px;
            min-height: 480px;
            width: 100%;
            background: linear-gradient(180deg, #eff7fb, #dbe8ee);
        }

        .fieldops-electrical-board-location-picker__footer {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
            padding: 0.9rem 1.35rem 1.15rem;
            border-top: 1px solid rgba(148, 163, 184, 0.14);
            color: #64748b;
            font-size: 0.86rem;
        }

        .dark .fieldops-electrical-board-location-picker__footer {
            border-top-color: rgba(255, 255, 255, 0.08);
            color: #94a3b8;
        }

        .fieldops-electrical-board-location-picker .leaflet-container {
            background: transparent;
        }

        .fieldops-electrical-board-location-picker .leaflet-control-zoom,
        .fieldops-electrical-board-location-picker .leaflet-control-attribution {
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.12);
        }

        .fieldops-electrical-board-location-picker .leaflet-control-zoom a {
            background: #fff;
            color: #0f172a;
        }

        .fieldops-electrical-board-location-picker .leaflet-control-attribution {
            background: rgba(255, 255, 255, 0.9);
            color: #64748b;
        }

        .fieldops-electrical-board-location-picker[data-theme="dark"] .leaflet-control-zoom a {
            background: #1d2030;
            color: #e2e8f0;
        }

        .fieldops-electrical-board-location-picker[data-theme="dark"] .leaflet-control-zoom,
        .fieldops-electrical-board-location-picker[data-theme="dark"] .leaflet-control-attribution {
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.35);
        }

        .fieldops-electrical-board-location-picker[data-theme="dark"] .leaflet-control-attribution {
            background: rgba(15, 23, 42, 0.9);
            color: #94a3b8;
        }

        .fieldops-electrical-board-location-picker[data-theme="dark"] .fieldops-electrical-board-location-picker__leaflet-corners--dark .leaflet-control-zoom a {
            background: #1d2030;
            color: #e2e8f0;
        }

        .fieldops-electrical-board-location-picker__pin {
            width: 1.25rem;
            height: 1.25rem;
            border: 3px solid #fff;
            border-radius: 999px 999px 999px 0;
            background: #00aeef;
            box-shadow: 0 12px 20px rgba(0, 174, 239, 0.35);
            transform: rotate(-45deg);
        }

        .fieldops-electrical-board-location-picker__pin::after {
            content: "";
            position: absolute;
            inset: 0;
            margin: auto;
            width: 0.35rem;
            height: 0.35rem;
            border-radius: 999px;
            background: #ffffff;
            transform: rotate(45deg);
        }

        @media (max-width: 768px) {
            .fieldops-electrical-board-location-picker__header {
                flex-direction: column;
            }

            .fieldops-electrical-board-location-picker__coords {
                align-items: flex-start;
                text-align: left;
            }

            .fieldops-electrical-board-location-picker__map {
                height: 360px;
                min-height: 360px;
            }
        }
            </style>
        @endpush
    @endonce

    <div class="fieldops-electrical-board-location-picker__header">
        <div>
            <div class="fieldops-electrical-board-location-picker__eyebrow">{{ __('fieldops::resource.electrical_boards.map.section_label') }}</div>
            <div class="fieldops-electrical-board-location-picker__title">{{ __('fieldops::resource.electrical_boards.map.title') }}</div>
            <div class="fieldops-electrical-board-location-picker__description">
                {{ __('fieldops::resource.electrical_boards.map.description') }}
            </div>
        </div>

        <div class="fieldops-electrical-board-location-picker__coords">
            <div class="fieldops-electrical-board-location-picker__coords-pill" x-text="coordsLabel"></div>
            <div class="fieldops-electrical-board-location-picker__hint">
                <template x-if="complexLabel">
                    <span>{{ __('fieldops::resource.electrical_boards.map.starting_from') }} <span x-text="complexLabel"></span>.</span>
                </template>
                <template x-if="! complexLabel">
                    <span>{{ __('fieldops::resource.electrical_boards.map.default_center') }}</span>
                </template>
            </div>
        </div>
    </div>

    <div x-ref="map" class="fieldops-electrical-board-location-picker__map"></div>

    <div class="fieldops-electrical-board-location-picker__footer">
        <span>{{ __('fieldops::resource.electrical_boards.map.footer_sync') }}</span>
        <span>{{ __('fieldops::resource.electrical_boards.map.footer_move') }}</span>
    </div>

    @once
        @push('scripts')
            <script>
        (() => {
        if (window.fieldopsElectricalBoardLocationPickerBootstrapped) {
            return;
        }

        window.fieldopsElectricalBoardLocationPickerBootstrapped = true;

        window.fieldopsLoadLeaflet = window.fieldopsLoadLeaflet || function () {
            if (window.L) {
                return Promise.resolve(window.L);
            }

            if (window.__fieldopsLeafletPromise) {
                return window.__fieldopsLeafletPromise;
            }

            window.__fieldopsLeafletPromise = new Promise((resolve, reject) => {
                const cssId = 'fieldops-leaflet-css';

                if (! document.getElementById(cssId)) {
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

        window.fieldopsRegisterElectricalBoardLocationPicker = window.fieldopsRegisterElectricalBoardLocationPicker || function () {
            if (! window.Alpine) {
                return;
            }

            window.Alpine.data('fieldopsElectricalBoardLocationPicker', (config) => ({
                config,
                map: null,
                marker: null,
                latInput: null,
                lngInput: null,
                centerLatInput: null,
                centerLngInput: null,
                coordsLabel: '',
                complexLabel: config.complexLabel,
                currentPinCoords: null,
                currentCenterCoords: null,
                lastCenterKey: null,
                stateObserver: null,
                statePoller: null,

                async init() {
                    this.latInput = document.getElementById(this.config.latInputId);
                    this.lngInput = document.getElementById(this.config.lngInputId);
                    this.centerLatInput = document.getElementById(this.config.centerLatInputId);
                    this.centerLngInput = document.getElementById(this.config.centerLngInputId);

                    await window.fieldopsLoadLeaflet();

                    if (! this.$refs.map || this.$refs.map.dataset.fieldopsInitialized === '1') {
                        return;
                    }

                    this.$refs.map.dataset.fieldopsInitialized = '1';

                    const initial = this.readPinCoords();
                    const L = window.L;

                    this.map = L.map(this.$refs.map, {
                        zoomControl: false,
                        scrollWheelZoom: false,
                    });

                    L.control.zoom({ position: 'topright' }).addTo(this.map);
                    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                        attribution: 'Tiles &copy; Esri',
                    }).addTo(this.map);

                    this.marker = L.marker([initial.lat, initial.lng], {
                        draggable: true,
                        icon: L.divIcon({
                            className: '',
                            html: '<div class="fieldops-electrical-board-location-picker__pin"></div>',
                            iconSize: [24, 24],
                            iconAnchor: [12, 24],
                        }),
                    }).addTo(this.map);

                    this.map.setView([initial.lat, initial.lng], Number(this.config.defaultZoom || 16));
                    this.marker.on('dragend', () => this.syncFromLatLng(this.marker.getLatLng(), false));
                    this.map.on('click', (event) => this.syncFromLatLng(event.latlng));

                    this.syncFromLatLng(initial, false, false);
                    this.bindStateObservers();
                    this.syncExternalState(true);
                    this.syncTheme();
                    this.observeTheme();

                    setTimeout(() => this.map.invalidateSize(), 0);
                },

                bindStateObservers() {
                    const handler = () => this.syncExternalState(false);

                    [this.centerLatInput, this.centerLngInput].forEach((input) => {
                        if (! input) {
                            return;
                        }

                        input.addEventListener('input', handler);
                        input.addEventListener('change', handler);
                    });

                    this.statePoller = window.setInterval(() => this.syncExternalState(false), 350);
                },

                readPinCoords() {
                    const lat = this.parseValue(this.latInput?.value ?? this.currentPinCoords?.lat, this.config.defaultLat);
                    const lng = this.parseValue(this.lngInput?.value ?? this.currentPinCoords?.lng, this.config.defaultLng);

                    this.currentPinCoords = { lat, lng };

                    return { lat, lng };
                },

                readCenterCoords() {
                    const centerLat = this.parseValue(this.centerLatInput?.value ?? this.currentCenterCoords?.lat, NaN);
                    const centerLng = this.parseValue(this.centerLngInput?.value ?? this.currentCenterCoords?.lng, NaN);
                    const pinCoords = this.readPinCoords();
                    const defaultLat = Number.parseFloat(this.config.defaultLat);
                    const defaultLng = Number.parseFloat(this.config.defaultLng);

                    if (
                        Number.isFinite(centerLat)
                        && Number.isFinite(centerLng)
                        && (centerLat !== defaultLat || centerLng !== defaultLng)
                    ) {
                        this.currentCenterCoords = { lat: centerLat, lng: centerLng };
                        return { lat: centerLat, lng: centerLng };
                    }

                    return pinCoords;
                },

                parseValue(value, fallback) {
                    const parsed = Number.parseFloat(value);

                    return Number.isFinite(parsed) ? parsed : Number.parseFloat(fallback);
                },

                syncExternalState(force = false) {
                    const center = this.readCenterCoords();
                    const centerKey = `${center.lat.toFixed(6)},${center.lng.toFixed(6)}`;

                    if (force || centerKey !== this.lastCenterKey) {
                        this.syncFromLatLng(center, true, true);
                        this.lastCenterKey = centerKey;
                    }
                },

                syncFromLatLng(latlng, moveMap = true, syncCenterFields = false) {
                    const lat = Number.parseFloat(latlng.lat).toFixed(6);
                    const lng = Number.parseFloat(latlng.lng).toFixed(6);
                    const parsedLat = Number.parseFloat(lat);
                    const parsedLng = Number.parseFloat(lng);

                    this.currentPinCoords = { lat: parsedLat, lng: parsedLng };
                    this.coordsLabel = `${lat}, ${lng}`;

                    if (this.latInput) {
                        this.latInput.value = lat;
                        this.latInput.dispatchEvent(new Event('input', { bubbles: true }));
                        this.latInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    if (this.lngInput) {
                        this.lngInput.value = lng;
                        this.lngInput.dispatchEvent(new Event('input', { bubbles: true }));
                        this.lngInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    if (syncCenterFields) {
                        if (this.centerLatInput) {
                            this.centerLatInput.value = lat;
                        }

                        if (this.centerLngInput) {
                            this.centerLngInput.value = lng;
                        }

                        this.currentCenterCoords = { lat: parsedLat, lng: parsedLng };
                    }

                    if (! this.map || ! this.marker) {
                        return;
                    }

                    const position = [parsedLat, parsedLng];
                    this.marker.setLatLng(position);

                    if (moveMap) {
                        this.map.setView(position, this.map.getZoom(), { animate: true });
                    }
                },

                syncTheme() {
                    if (! this.map || ! this.map._controlCorners) {
                        return;
                    }

                    const isDark = document.documentElement.classList.contains('dark');
                    this.$el.dataset.theme = isDark ? 'dark' : 'light';

                    Object.values(this.map._controlCorners).forEach((corner) => {
                        if (corner && corner.classList) {
                            corner.classList.toggle('fieldops-electrical-board-location-picker__leaflet-corners--dark', isDark);
                        }
                    });
                },

                observeTheme() {
                    const observer = new MutationObserver(() => this.syncTheme());
                    observer.observe(document.documentElement, {
                        attributes: true,
                        attributeFilter: ['class'],
                    });
                    this.stateObserver = observer;
                },
            }));
        };

        if (window.Alpine) {
            window.fieldopsRegisterElectricalBoardLocationPicker();
        } else {
            document.addEventListener('alpine:init', window.fieldopsRegisterElectricalBoardLocationPicker);
        }
        })();
            </script>
        @endpush
    @endonce
</div>
