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
     *     variantInputId: string,
     *     pinVariants: array<string, array{label: string, initial: string, code?: ?string, color: string, text: string}>,
     *     defaultPinVariant: string,
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
        'variantInputId' => 'form.terrain_pin_variant',
        'pinVariants' => [],
        'defaultPinVariant' => 'generic',
    ], $getViewData());
@endphp

@once
    <style>
        .fieldops-terrain-location-picker {
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.25rem;
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.07), transparent 35%),
                linear-gradient(180deg, #ffffff 0%, #f7fbfe 100%);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .dark .fieldops-terrain-location-picker {
            border-color: rgba(255, 255, 255, 0.08);
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.14), transparent 30%),
                linear-gradient(180deg, #171725 0%, #11111a 100%);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 24px 60px -18px rgba(0, 0, 0, 0.62);
        }

        .fieldops-terrain-location-picker__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.35rem 1rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
        }

        .dark .fieldops-terrain-location-picker__header {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .fieldops-terrain-location-picker__eyebrow {
            color: #009bd6;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .fieldops-terrain-location-picker__title {
            margin-top: 0.35rem;
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .dark .fieldops-terrain-location-picker__title {
            color: #f8fafc;
        }

        .fieldops-terrain-location-picker__description {
            margin-top: 0.3rem;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.45;
        }

        .dark .fieldops-terrain-location-picker__description {
            color: #94a3b8;
        }

        .fieldops-terrain-location-picker__coords {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.45rem;
            min-width: 11rem;
            text-align: right;
        }

        .fieldops-terrain-location-picker__coords-pill {
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

        .dark .fieldops-terrain-location-picker__coords-pill {
            border-color: rgba(0, 174, 239, 0.24);
            background: rgba(0, 174, 239, 0.12);
            color: #7dd3fc;
        }

        .fieldops-terrain-location-picker__hint {
            color: #64748b;
            font-size: 0.78rem;
            line-height: 1.4;
        }

        .dark .fieldops-terrain-location-picker__hint {
            color: #94a3b8;
        }

        .fieldops-terrain-location-picker__map {
            height: 480px;
            min-height: 480px;
            width: 100%;
            background: linear-gradient(180deg, #eff7fb, #dbe8ee);
        }

        .fieldops-terrain-location-picker__footer {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
            padding: 0.9rem 1.35rem 1.15rem;
            border-top: 1px solid rgba(148, 163, 184, 0.14);
            color: #64748b;
            font-size: 0.86rem;
        }

        .dark .fieldops-terrain-location-picker__footer {
            border-top-color: rgba(255, 255, 255, 0.08);
            color: #94a3b8;
        }

        .fieldops-terrain-location-picker .leaflet-container {
            background: transparent;
        }

        .fieldops-terrain-location-picker .leaflet-control-zoom,
        .fieldops-terrain-location-picker .leaflet-control-attribution {
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.12);
        }

        .fieldops-terrain-location-picker .leaflet-control-zoom a {
            background: #fff;
            color: #0f172a;
        }

        .fieldops-terrain-location-picker .leaflet-control-attribution {
            background: rgba(255, 255, 255, 0.9);
            color: #64748b;
        }

        .fieldops-terrain-location-picker[data-theme="dark"] .leaflet-control-zoom a {
            background: #1d2030;
            color: #e2e8f0;
        }

        .fieldops-terrain-location-picker[data-theme="dark"] .leaflet-control-zoom,
        .fieldops-terrain-location-picker[data-theme="dark"] .leaflet-control-attribution {
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.35);
        }

        .fieldops-terrain-location-picker[data-theme="dark"] .leaflet-control-attribution {
            background: rgba(15, 23, 42, 0.9);
            color: #94a3b8;
        }

        .fieldops-terrain-location-picker[data-theme="dark"] .fieldops-terrain-location-picker__leaflet-corners--dark .leaflet-control-zoom a {
            background: #1d2030;
            color: #e2e8f0;
        }

        .fieldops-terrain-location-picker__pin {
            --fieldops-pin-color: #e6007e;
            --fieldops-pin-text: #ffffff;
            width: 1.25rem;
            height: 1.25rem;
            border: 3px solid #fff;
            border-radius: 999px 999px 999px 0;
            background: var(--fieldops-pin-color);
            box-shadow: 0 12px 20px color-mix(in srgb, var(--fieldops-pin-color) 35%, transparent);
            transform: rotate(-45deg);
        }

        .fieldops-terrain-location-picker__pin::after {
            content: "";
            position: absolute;
            inset: 0;
            margin: auto;
            width: 0.35rem;
            height: 0.35rem;
            border-radius: 999px;
            background: var(--fieldops-pin-text);
            transform: rotate(45deg);
        }

        @media (max-width: 768px) {
            .fieldops-terrain-location-picker__header {
                flex-direction: column;
            }

            .fieldops-terrain-location-picker__coords {
                align-items: flex-start;
                text-align: left;
            }

            .fieldops-terrain-location-picker__map {
                height: 360px;
                min-height: 360px;
            }
        }
    </style>
@endonce

<div
    class="fieldops-terrain-location-picker"
    data-theme="light"
    wire:ignore
    x-data="fieldopsTerrainLocationPicker({
        latInputId: @js($data['latInputId']),
        lngInputId: @js($data['lngInputId']),
        centerLatInputId: @js($data['centerLatInputId']),
        centerLngInputId: @js($data['centerLngInputId']),
        variantInputId: @js($data['variantInputId']),
        defaultLat: @js($data['defaultLat']),
        defaultLng: @js($data['defaultLng']),
        defaultZoom: @js($data['defaultZoom']),
        complexLabel: @js($data['complexLabel']),
        pinVariants: @js($data['pinVariants']),
        defaultPinVariant: @js($data['defaultPinVariant']),
    })"
    x-init="init()"
>
    <div class="fieldops-terrain-location-picker__header">
        <div>
            <div class="fieldops-terrain-location-picker__eyebrow">Location</div>
            <div class="fieldops-terrain-location-picker__title">Adjust the terrain pin</div>
            <div class="fieldops-terrain-location-picker__description">
                Click on the map or drag the pin to set the exact terrain coordinates.
            </div>
        </div>

        <div class="fieldops-terrain-location-picker__coords">
            <div class="fieldops-terrain-location-picker__coords-pill" x-text="coordsLabel"></div>
            <div class="fieldops-terrain-location-picker__hint">
                <template x-if="complexLabel">
                    <span>Starting from <span x-text="complexLabel"></span>.</span>
                </template>
                <template x-if="! complexLabel">
                    <span>Starting from the default site center.</span>
                </template>
            </div>
        </div>
    </div>

    <div x-ref="map" class="fieldops-terrain-location-picker__map"></div>

    <div class="fieldops-terrain-location-picker__footer">
        <span>Latitude and longitude stay synchronized automatically.</span>
        <span>Move the pin before saving.</span>
    </div>
</div>

@once
    <script>
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

        const buildTerrainMarkerSvg = (code, color) => {
            const fill = color || '#e6007e';

            switch (code) {
                case 'soccer':
                    return `
                        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
                            <rect x="7" y="7" width="26" height="26" fill="none" stroke="white" stroke-width="2"/>
                            <line x1="20" y1="7" x2="20" y2="33" stroke="white" stroke-width="2"/>
                            <circle cx="20" cy="20" r="5" fill="none" stroke="white" stroke-width="2"/>
                        </svg>
                    `;
                case 'athletics':
                    return `
                        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <ellipse cx="20" cy="20" rx="15" ry="12" fill="${fill}"/>
                            <ellipse cx="20" cy="20" rx="12" ry="9" fill="none" stroke="white" stroke-width="2"/>
                            <ellipse cx="20" cy="20" rx="9" ry="6" fill="none" stroke="white" stroke-width="2"/>
                        </svg>
                    `;
                case 'tennis':
                    return `
                        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
                            <line x1="20" y1="7" x2="20" y2="33" stroke="white" stroke-width="2"/>
                            <rect x="7" y="7" width="26" height="26" fill="none" stroke="white" stroke-width="2"/>
                            <path d="M7,20 Q20,25 33,20 M7,20 Q20,15 33,20" fill="none" stroke="white" stroke-width="1"/>
                        </svg>
                    `;
                case 'padel':
                    return `
                        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
                            <path d="M20,10 Q26,10 26,18 Q26,25 20,27 Q14,25 14,18 Q14,10 20,10 Z" fill="white"/>
                            <circle cx="17.5" cy="15" r="1.1" fill="${fill}"/>
                            <circle cx="22.5" cy="15" r="1.1" fill="${fill}"/>
                            <circle cx="20" cy="19" r="1.1" fill="${fill}"/>
                            <line x1="20" y1="27" x2="20" y2="32" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    `;
                case 'hockey':
                    return `
                        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
                            <path d="M17,10 L17,24 Q17,29 23,29" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
                            <circle cx="26.5" cy="29" r="1.8" fill="white"/>
                        </svg>
                    `;
                case 'basketball':
                    return `
                        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
                            <circle cx="20" cy="20" r="9" fill="none" stroke="white" stroke-width="1.6"/>
                            <line x1="20" y1="11" x2="20" y2="29" stroke="white" stroke-width="1.4"/>
                            <line x1="11" y1="20" x2="29" y2="20" stroke="white" stroke-width="1.4"/>
                            <path d="M12.5,15 Q20,18.5 27.5,15" fill="none" stroke="white" stroke-width="1.4"/>
                            <path d="M12.5,25 Q20,21.5 27.5,25" fill="none" stroke="white" stroke-width="1.4"/>
                        </svg>
                    `;
                case 'volleyball':
                    return `
                        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
                            <circle cx="20" cy="20" r="9" fill="none" stroke="white" stroke-width="1.6"/>
                            <path d="M13,13 Q22,17 17,27" fill="none" stroke="white" stroke-width="1.3"/>
                            <path d="M13,13 Q18,21 28,16" fill="none" stroke="white" stroke-width="1.3"/>
                            <path d="M17,27 Q23,24 28,16" fill="none" stroke="white" stroke-width="1.3"/>
                        </svg>
                    `;
                case 'petanque':
                    return `
                        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
                            <circle cx="17" cy="22" r="5.2" fill="none" stroke="white" stroke-width="1.6"/>
                            <circle cx="24.5" cy="17.5" r="5.2" fill="none" stroke="white" stroke-width="1.6"/>
                            <circle cx="20.5" cy="26.5" r="3.8" fill="none" stroke="white" stroke-width="1.3"/>
                            <circle cx="11.5" cy="12.5" r="1.7" fill="white"/>
                        </svg>
                    `;
                case 'multi_sport':
                    return `
                        <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                            <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
                            <rect x="9" y="12" width="22" height="16" rx="1.5" fill="none" stroke="white" stroke-width="1.6"/>
                            <line x1="20" y1="12" x2="20" y2="28" stroke="white" stroke-width="1.4"/>
                            <circle cx="20" cy="20" r="3.4" fill="none" stroke="white" stroke-width="1.4"/>
                        </svg>
                    `;
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

        const registerTerrainLocationPicker = () => {
            if (! window.Alpine) {
                return;
            }

            window.Alpine.data('fieldopsTerrainLocationPicker', (config) => ({
                config,
                map: null,
                marker: null,
                latInput: null,
                lngInput: null,
                centerLatInput: null,
                centerLngInput: null,
                variantInput: null,
                coordsLabel: '',
                complexLabel: config.complexLabel,
                currentPinCoords: null,
                currentCenterCoords: null,
                currentVariant: null,
                lastCenterKey: null,
                stateObserver: null,
                statePoller: null,

                async init() {
                    this.latInput = document.getElementById(this.config.latInputId);
                    this.lngInput = document.getElementById(this.config.lngInputId);
                    this.centerLatInput = document.getElementById(this.config.centerLatInputId);
                    this.centerLngInput = document.getElementById(this.config.centerLngInputId);
                    this.variantInput = document.getElementById(this.config.variantInputId);

                    await window.fieldopsLoadLeaflet();

                    if (! this.$refs.map || this.$refs.map.dataset.fieldopsInitialized === '1') {
                        return;
                    }

                    this.$refs.map.dataset.fieldopsInitialized = '1';

                    const initial = this.readPinCoords();
                    const variant = this.readVariant();
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
                        icon: this.buildMarkerIcon(L, variant),
                    }).addTo(this.map);

                    this.map.setView([initial.lat, initial.lng], Number(this.config.defaultZoom || 16));
                    this.marker.on('dragend', () => this.syncFromLatLng(this.marker.getLatLng(), false));
                    this.map.on('click', (event) => this.syncFromLatLng(event.latlng));

                    this.syncFromLatLng(initial, false, false);
                    this.syncMarkerVariant(variant, true);
                    this.bindStateObservers();
                    this.syncExternalState(true);
                    this.syncTheme();
                    this.observeTheme();

                    setTimeout(() => this.map.invalidateSize(), 0);
                },

                bindStateObservers() {
                    const handler = () => this.syncExternalState(false);

                    [this.centerLatInput, this.centerLngInput, this.variantInput].forEach((input) => {
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

                readVariant() {
                    const variant = String(this.variantInput?.value ?? '').trim();

                    return variant || this.config.defaultPinVariant || 'generic';
                },

                parseValue(value, fallback) {
                    const parsed = Number.parseFloat(value);

                    return Number.isFinite(parsed) ? parsed : Number.parseFloat(fallback);
                },

                syncExternalState(force = false) {
                    const center = this.readCenterCoords();
                    const centerKey = `${center.lat.toFixed(6)},${center.lng.toFixed(6)}`;
                    const variant = this.readVariant();

                    if (force || centerKey !== this.lastCenterKey) {
                        this.syncFromLatLng(center, true, true);
                        this.lastCenterKey = centerKey;
                    }

                    if (force || variant !== this.currentVariant) {
                        this.syncMarkerVariant(variant);
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

                    const position = [Number.parseFloat(lat), Number.parseFloat(lng)];
                    this.marker.setLatLng(position);

                    if (moveMap) {
                        this.map.setView(position, this.map.getZoom(), { animate: true });
                    }
                },

                syncMarkerVariant(variant, force = false) {
                    const resolvedVariant = this.resolveVariantKey(variant);

                    if (! force && resolvedVariant === this.currentVariant) {
                        return;
                    }

                    this.currentVariant = resolvedVariant;

                    if (this.marker && window.L) {
                        this.marker.setIcon(this.buildMarkerIcon(window.L, resolvedVariant));
                    }
                },

                resolveVariantKey(variant) {
                    if (this.config.pinVariants && this.config.pinVariants[variant]) {
                        return variant;
                    }

                    if (this.config.pinVariants && this.config.pinVariants.generic) {
                        return 'generic';
                    }

                    const keys = Object.keys(this.config.pinVariants || {});

                    return keys[0] || 'generic';
                },

                getVariantData(variant) {
                    const resolvedVariant = this.resolveVariantKey(variant);

                    return this.config.pinVariants?.[resolvedVariant]
                        ?? this.config.pinVariants?.generic
                        ?? { color: '#e6007e', text: '#ffffff', code: null };
                },

                buildMarkerIcon(L, variant) {
                    const style = this.getVariantData(variant);
                    const svg = style.code
                        ? buildTerrainMarkerSvg(style.code, style.color)
                        : buildTerrainMarkerSvg(null, style.color);

                    return L.icon({
                        iconUrl: `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`,
                        iconSize: [40, 40],
                        iconAnchor: [20, 40],
                        popupAnchor: [0, -40],
                    });
                },

                syncTheme() {
                    if (! this.map || ! this.map._controlCorners) {
                        return;
                    }

                    const isDark = document.documentElement.classList.contains('dark');
                    this.$el.dataset.theme = isDark ? 'dark' : 'light';

                    Object.values(this.map._controlCorners).forEach((corner) => {
                        if (corner && corner.classList) {
                            corner.classList.toggle('fieldops-terrain-location-picker__leaflet-corners--dark', isDark);
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
            registerTerrainLocationPicker();
        } else {
            document.addEventListener('alpine:init', registerTerrainLocationPicker);
        }
    </script>
@endonce
