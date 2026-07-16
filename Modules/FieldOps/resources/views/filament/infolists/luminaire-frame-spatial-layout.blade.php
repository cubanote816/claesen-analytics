@php
    /**
     * @var array{
     *     eyebrow: string,
     *     title: string,
     *     subtitle: string,
     *     frameType: ?string,
     *     summary: array<int, array{label: string, value: int|string}>,
     *     bounds: ?array{minX: float, maxX: float, minY: float, maxY: float},
     *     markers: array<int, array{
     *         id: int,
     *         label: string,
     *         serial: ?string,
     *         title: string,
     *         subgroup: ?string,
     *         frameX: float|int|string|null,
     *         frameY: float|int|string|null,
     *         scaleX: float|int|string|null,
     *         scaleY: float|int|string|null,
     *         positionLabel: string,
     *         left: float,
     *         top: float,
     *         size: int,
     *         flagged: bool,
     *         selected: bool,
     *         url: string,
     *     }>,
     *     unpositioned: array<int, array{
     *         id: int,
     *         label: string,
     *         serial: ?string,
     *         title: string,
     *         subgroup: ?string,
     *         frameX: float|int|string|null,
     *         frameY: float|int|string|null,
     *         scaleX: float|int|string|null,
     *         scaleY: float|int|string|null,
     *         positionLabel: string,
     *         flagged: bool,
     *         selected: bool,
     *         url: string,
     *     }>,
     *     selectedId: ?int,
     *     selectedMarker: ?array<string, mixed>,
     * } $data
     */

    $data = $getState();
    $payload = [
        'markers' => $data['markers'] ?? [],
        'unpositioned' => $data['unpositioned'] ?? [],
        'summary' => $data['summary'] ?? [],
        'bounds' => $data['bounds'] ?? null,
        'selectedId' => $data['selectedId'] ?? null,
        'frameType' => $data['frameType'] ?? null,
        'title' => $data['title'] ?? '',
        'subtitle' => $data['subtitle'] ?? '',
        'eyebrow' => $data['eyebrow'] ?? '',
        'selectedMarker' => $data['selectedMarker'] ?? null,
    ];
@endphp

@once
    @push('styles')
        <style>
            .fieldops-luminaire-frame-spatial {
                overflow: hidden;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 1.35rem;
                background:
                    radial-gradient(circle at top left, rgba(0, 174, 239, 0.08), transparent 34%),
                    linear-gradient(180deg, #ffffff 0%, #f7fbfe 100%);
                box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            }

            .dark .fieldops-luminaire-frame-spatial {
                border-color: rgba(255, 255, 255, 0.08);
                background:
                    radial-gradient(circle at top left, rgba(0, 174, 239, 0.15), transparent 28%),
                    linear-gradient(180deg, #171725 0%, #11111a 100%);
                box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 24px 60px -18px rgba(0, 0, 0, 0.62);
            }

            .fieldops-luminaire-frame-spatial__header {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 1rem 1.25rem;
                padding: 1.35rem 1.45rem 1rem;
                border-bottom: 1px solid rgba(148, 163, 184, 0.14);
            }

            .dark .fieldops-luminaire-frame-spatial__header {
                border-bottom-color: rgba(255, 255, 255, 0.08);
            }

            .fieldops-luminaire-frame-spatial__eyebrow {
                color: #008fc8;
                font-size: 0.68rem;
                font-weight: 800;
                letter-spacing: 0.18em;
                text-transform: uppercase;
            }

            .fieldops-luminaire-frame-spatial__title {
                margin-top: 0.35rem;
                color: #0f172a;
                font-size: clamp(1.9rem, 3vw, 2.8rem);
                font-weight: 900;
                letter-spacing: -0.035em;
                line-height: 1.02;
            }

            .dark .fieldops-luminaire-frame-spatial__title {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__subtitle {
                margin-top: 0.55rem;
                max-width: 64rem;
                color: #64748b;
                font-size: 0.95rem;
                line-height: 1.55;
            }

            .dark .fieldops-luminaire-frame-spatial__subtitle {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-spatial__chips,
            .fieldops-luminaire-frame-spatial__summary,
            .fieldops-luminaire-frame-spatial__toolbar {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .fieldops-luminaire-frame-spatial__chips {
                margin-top: 0.95rem;
            }

            .fieldops-luminaire-frame-spatial__chip,
            .fieldops-luminaire-frame-spatial__summary-pill,
            .fieldops-luminaire-frame-spatial__button,
            .fieldops-luminaire-frame-spatial__sidebar-item,
            .fieldops-luminaire-frame-spatial__mini-pill {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.45rem;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.7);
                color: #334155;
                font-weight: 700;
                line-height: 1;
                white-space: nowrap;
            }

            .fieldops-luminaire-frame-spatial__chip,
            .fieldops-luminaire-frame-spatial__summary-pill {
                min-height: 2rem;
                padding: 0.32rem 0.75rem;
                font-size: 0.86rem;
            }

            .dark .fieldops-luminaire-frame-spatial__chip,
            .dark .fieldops-luminaire-frame-spatial__summary-pill,
            .dark .fieldops-luminaire-frame-spatial__button,
            .dark .fieldops-luminaire-frame-spatial__sidebar-item,
            .dark .fieldops-luminaire-frame-spatial__mini-pill {
                border-color: rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.04);
                color: #dbeafe;
            }

            .fieldops-luminaire-frame-spatial__summary {
                justify-content: flex-end;
            }

            .fieldops-luminaire-frame-spatial__summary-pill {
                gap: 0.55rem;
                box-shadow: 0 8px 20px rgba(0, 174, 239, 0.07);
            }

            .fieldops-luminaire-frame-spatial__summary-value {
                display: inline-grid;
                min-width: 2.05rem;
                height: 2.05rem;
                place-items: center;
                border-radius: 999px;
                background: rgba(0, 174, 239, 0.1);
                color: #008fc8;
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-weight: 900;
            }

            .dark .fieldops-luminaire-frame-spatial__summary-value {
                background: rgba(0, 174, 239, 0.14);
                color: #7dd3fc;
            }

            .fieldops-luminaire-frame-spatial__toolbar {
                justify-content: flex-end;
                align-items: flex-start;
            }

            .fieldops-luminaire-frame-spatial__button {
                min-height: 2.35rem;
                padding: 0.45rem 0.8rem;
                font-size: 0.84rem;
                transition: transform 150ms ease, border-color 150ms ease, background-color 150ms ease;
            }

            .fieldops-luminaire-frame-spatial__button:hover,
            .fieldops-luminaire-frame-spatial__sidebar-item:hover,
            .fieldops-luminaire-frame-spatial__marker:hover {
                transform: translateY(-1px);
                border-color: rgba(14, 165, 233, 0.34);
            }

            .fieldops-luminaire-frame-spatial__button--primary {
                border-color: rgba(14, 165, 233, 0.34);
                background: rgba(14, 165, 233, 0.12);
                color: #075985;
            }

            .dark .fieldops-luminaire-frame-spatial__button--primary {
                border-color: rgba(56, 189, 248, 0.28);
                background: rgba(56, 189, 248, 0.14);
                color: #7dd3fc;
            }

            .fieldops-luminaire-frame-spatial__body {
                display: grid;
                grid-template-columns: minmax(0, 1.75fr) minmax(18rem, 0.82fr);
                gap: 1rem;
                padding: 1rem 1.2rem 1.2rem;
            }

            .fieldops-luminaire-frame-spatial__main,
            .fieldops-luminaire-frame-spatial__rail {
                min-width: 0;
            }

            .fieldops-luminaire-frame-spatial__board-shell,
            .fieldops-luminaire-frame-spatial__panel {
                overflow: hidden;
                border: 1px solid rgba(148, 163, 184, 0.16);
                border-radius: 1.15rem;
                background: rgba(255, 255, 255, 0.58);
            }

            .dark .fieldops-luminaire-frame-spatial__board-shell,
            .dark .fieldops-luminaire-frame-spatial__panel {
                border-color: rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.03);
            }

            .fieldops-luminaire-frame-spatial__board-shell {
                background:
                    radial-gradient(circle at top left, rgba(0, 174, 239, 0.1), transparent 34%),
                    linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.58));
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.32);
            }

            .dark .fieldops-luminaire-frame-spatial__board-shell {
                background:
                    radial-gradient(circle at top left, rgba(0, 174, 239, 0.15), transparent 30%),
                    linear-gradient(180deg, rgba(17, 24, 39, 0.82), rgba(12, 18, 32, 0.88));
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
            }

            .fieldops-luminaire-frame-spatial__board-header {
                display: flex;
                justify-content: space-between;
                gap: 1rem;
                padding: 1rem 1rem 0.9rem;
                border-bottom: 1px solid rgba(148, 163, 184, 0.14);
            }

            .dark .fieldops-luminaire-frame-spatial__board-header {
                border-bottom-color: rgba(255, 255, 255, 0.08);
            }

            .fieldops-luminaire-frame-spatial__board-title {
                color: #0f172a;
                font-size: 0.95rem;
                font-weight: 850;
                letter-spacing: -0.01em;
            }

            .dark .fieldops-luminaire-frame-spatial__board-title {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__board-subtitle {
                margin-top: 0.25rem;
                color: #64748b;
                font-size: 0.82rem;
                line-height: 1.45;
            }

            .dark .fieldops-luminaire-frame-spatial__board-subtitle {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-spatial__board-meta {
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-end;
                gap: 0.45rem;
            }

            .fieldops-luminaire-frame-spatial__board-pill {
                min-height: 1.8rem;
                padding: 0.25rem 0.55rem;
                border-radius: 999px;
                border: 1px solid rgba(148, 163, 184, 0.16);
                background: rgba(255, 255, 255, 0.7);
                color: #475569;
                font-size: 0.72rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .dark .fieldops-luminaire-frame-spatial__board-pill {
                border-color: rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.04);
                color: #cbd5e1;
            }

            .fieldops-luminaire-frame-spatial__viewport {
                overflow: auto;
                padding: 1rem;
            }

            .fieldops-luminaire-frame-spatial__surface {
                position: relative;
                width: calc(100% * var(--fieldops-frame-zoom));
                min-height: calc(34rem * var(--fieldops-frame-zoom));
                margin: 0 auto;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 1rem;
                background:
                    linear-gradient(to right, rgba(148, 163, 184, 0.14) 1px, transparent 1px),
                    linear-gradient(to bottom, rgba(148, 163, 184, 0.14) 1px, transparent 1px),
                    radial-gradient(circle at center, rgba(255, 255, 255, 0.12), transparent 72%),
                    linear-gradient(180deg, rgba(248, 250, 252, 0.88), rgba(234, 244, 250, 0.96));
                background-size: 4.75rem 4.75rem, 4.75rem 4.75rem, auto, auto;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.42);
                --fieldops-frame-zoom: 1;
            }

            .dark .fieldops-luminaire-frame-spatial__surface {
                border-color: rgba(255, 255, 255, 0.08);
                background:
                    linear-gradient(to right, rgba(148, 163, 184, 0.11) 1px, transparent 1px),
                    linear-gradient(to bottom, rgba(148, 163, 184, 0.11) 1px, transparent 1px),
                    radial-gradient(circle at center, rgba(255, 255, 255, 0.05), transparent 72%),
                    linear-gradient(180deg, rgba(18, 24, 40, 0.95), rgba(11, 16, 28, 0.98));
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
            }

            .fieldops-luminaire-frame-spatial__surface::before,
            .fieldops-luminaire-frame-spatial__surface::after {
                content: "";
                position: absolute;
                inset: 0;
                pointer-events: none;
            }

            .fieldops-luminaire-frame-spatial__surface::before {
                border-radius: inherit;
                background:
                    linear-gradient(to right, rgba(14, 165, 233, 0.08), transparent 12%, transparent 88%, rgba(14, 165, 233, 0.08)),
                    linear-gradient(to bottom, rgba(14, 165, 233, 0.08), transparent 12%, transparent 88%, rgba(14, 165, 233, 0.08));
                opacity: 0.55;
            }

            .fieldops-luminaire-frame-spatial__surface::after {
                border-radius: inherit;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
                mix-blend-mode: soft-light;
            }

            .fieldops-luminaire-frame-spatial__marker {
                position: absolute;
                z-index: 2;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                border: 2px solid rgba(255, 255, 255, 0.94);
                border-radius: 999px;
                background: linear-gradient(180deg, #00aeef 0%, #008fc8 100%);
                color: #ffffff;
                box-shadow: 0 18px 30px rgba(0, 174, 239, 0.26);
                transform: translate(-50%, -50%);
                transition: transform 150ms ease, box-shadow 150ms ease, background-color 150ms ease, border-color 150ms ease;
            }

            .dark .fieldops-luminaire-frame-spatial__marker {
                border-color: rgba(15, 23, 42, 0.95);
                box-shadow: 0 18px 30px rgba(0, 174, 239, 0.32);
            }

            .fieldops-luminaire-frame-spatial__marker--selected {
                background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
                box-shadow: 0 0 0 0.28rem rgba(245, 158, 11, 0.18), 0 22px 40px rgba(245, 158, 11, 0.28);
            }

            .fieldops-luminaire-frame-spatial__marker--flagged {
                background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
            }

            .fieldops-luminaire-frame-spatial__marker-label {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 1.55rem;
                height: 1.55rem;
                padding: 0 0.35rem;
                border-radius: 999px;
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 0.8rem;
                font-weight: 900;
                line-height: 1;
            }

            .fieldops-luminaire-frame-spatial__marker-sub {
                position: absolute;
                right: -0.2rem;
                bottom: -0.2rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 0.95rem;
                height: 0.95rem;
                border: 2px solid rgba(255, 255, 255, 0.95);
                border-radius: 999px;
                background: #0f172a;
                color: #e2e8f0;
                font-size: 0.48rem;
                font-weight: 900;
            }

            .fieldops-luminaire-frame-spatial__empty {
                display: grid;
                min-height: 34rem;
                place-items: center;
                padding: 2rem;
                text-align: center;
            }

            .fieldops-luminaire-frame-spatial__empty-copy {
                max-width: 38rem;
            }

            .fieldops-luminaire-frame-spatial__empty-title {
                color: #0f172a;
                font-size: 1rem;
                font-weight: 850;
            }

            .dark .fieldops-luminaire-frame-spatial__empty-title {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__empty-text,
            .fieldops-luminaire-frame-spatial__hint,
            .fieldops-luminaire-frame-spatial__selected-meta,
            .fieldops-luminaire-frame-spatial__sidebar-subtitle {
                color: #64748b;
                font-size: 0.92rem;
                line-height: 1.55;
            }

            .dark .fieldops-luminaire-frame-spatial__empty-text,
            .dark .fieldops-luminaire-frame-spatial__hint,
            .dark .fieldops-luminaire-frame-spatial__selected-meta,
            .dark .fieldops-luminaire-frame-spatial__sidebar-subtitle {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-spatial__rail {
                display: flex;
                flex-direction: column;
                gap: 0.9rem;
            }

            .fieldops-luminaire-frame-spatial__panel {
                padding: 0.95rem;
            }

            .fieldops-luminaire-frame-spatial__panel-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
            }

            .fieldops-luminaire-frame-spatial__sidebar-title {
                color: #0f172a;
                font-size: 0.95rem;
                font-weight: 850;
            }

            .dark .fieldops-luminaire-frame-spatial__sidebar-title {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__list {
                display: grid;
                gap: 0.5rem;
                margin-top: 0.85rem;
            }

            .fieldops-luminaire-frame-spatial__sidebar-item {
                width: 100%;
                justify-content: space-between;
                padding: 0.7rem 0.8rem;
                border-radius: 1rem;
                text-align: left;
            }

            .fieldops-luminaire-frame-spatial__sidebar-item--active {
                border-color: rgba(14, 165, 233, 0.38);
                background: rgba(14, 165, 233, 0.12);
                color: #e0f2fe;
                box-shadow: 0 0 0 1px rgba(14, 165, 233, 0.12);
            }

            .fieldops-luminaire-frame-spatial__sidebar-name {
                min-width: 0;
                color: inherit;
                font-weight: 800;
            }

            .fieldops-luminaire-frame-spatial__sidebar-hint {
                margin-top: 0.15rem;
                font-size: 0.78rem;
                font-weight: 600;
                opacity: 0.84;
            }

            .fieldops-luminaire-frame-spatial__mini-pill {
                min-height: 1.65rem;
                padding: 0.18rem 0.5rem;
                font-size: 0.72rem;
            }

            .fieldops-luminaire-frame-spatial__selected-card {
                padding: 1rem;
            }

            .fieldops-luminaire-frame-spatial__selected-title {
                color: #0f172a;
                font-size: 1.1rem;
                font-weight: 900;
                letter-spacing: -0.02em;
            }

            .dark .fieldops-luminaire-frame-spatial__selected-title {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__selected-row {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.65rem;
                margin-top: 0.85rem;
            }

            .fieldops-luminaire-frame-spatial__selected-stat {
                padding: 0.8rem;
                border-radius: 0.95rem;
                border: 1px solid rgba(148, 163, 184, 0.16);
                background: rgba(255, 255, 255, 0.04);
            }

            .fieldops-luminaire-frame-spatial__selected-stat-label {
                color: #94a3b8;
                font-size: 0.7rem;
                font-weight: 800;
                letter-spacing: 0.16em;
                text-transform: uppercase;
            }

            .fieldops-luminaire-frame-spatial__selected-stat-value {
                margin-top: 0.35rem;
                color: #0f172a;
                font-size: 0.96rem;
                font-weight: 800;
            }

            .dark .fieldops-luminaire-frame-spatial__selected-stat-value {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__selected-actions {
                display: flex;
                gap: 0.5rem;
                margin-top: 1rem;
            }

            .fieldops-luminaire-frame-spatial__link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 2.25rem;
                padding: 0.45rem 0.75rem;
                border: 1px solid rgba(56, 189, 248, 0.22);
                border-radius: 0.8rem;
                background: rgba(56, 189, 248, 0.14);
                color: #7dd3fc;
                font-size: 0.84rem;
                font-weight: 700;
            }

            .fieldops-luminaire-frame-spatial__link--ghost {
                background: rgba(255, 255, 255, 0.04);
                color: #0f172a;
            }

            .dark .fieldops-luminaire-frame-spatial__link--ghost {
                color: #e2e8f0;
            }

            .fieldops-luminaire-frame-spatial__hint {
                padding-top: 0.2rem;
            }

            @media (max-width: 1024px) {
                .fieldops-luminaire-frame-spatial__body {
                    grid-template-columns: minmax(0, 1fr);
                }
            }

            @media (max-width: 768px) {
                .fieldops-luminaire-frame-spatial__header,
                .fieldops-luminaire-frame-spatial__body {
                    padding-left: 0.9rem;
                    padding-right: 0.9rem;
                }

                .fieldops-luminaire-frame-spatial__header {
                    grid-template-columns: minmax(0, 1fr);
                }

                .fieldops-luminaire-frame-spatial__summary,
                .fieldops-luminaire-frame-spatial__toolbar {
                    justify-content: flex-start;
                }

                .fieldops-luminaire-frame-spatial__selected-row {
                    grid-template-columns: minmax(0, 1fr);
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            window.fieldopsLuminaireFrameLayout = function (payload) {
                return {
                    zoom: 1,
                    minZoom: 0.75,
                    maxZoom: 1.75,
                    selectedId: payload.selectedId ?? null,
                    markers: payload.markers ?? [],
                    unpositioned: payload.unpositioned ?? [],
                    summary: payload.summary ?? [],
                    bounds: payload.bounds ?? null,
                    frameType: payload.frameType ?? null,
                    init() {
                        if (this.selectedId === null) {
                            const first = this.markers[0] ?? this.unpositioned[0] ?? null;
                            this.selectedId = first ? Number(first.id) : null;
                        }

                        document.addEventListener('fullscreenchange', () => {
                            this.isFullscreen = Boolean(document.fullscreenElement);
                        });
                    },
                    isFullscreen: false,
                    selectedMarker() {
                        return this.markers.find((item) => Number(item.id) === Number(this.selectedId))
                            ?? this.unpositioned.find((item) => Number(item.id) === Number(this.selectedId))
                            ?? null;
                    },
                    selectMarker(id) {
                        this.selectedId = Number(id);
                    },
                    zoomIn() {
                        this.zoom = Math.min(this.maxZoom, Math.round((this.zoom + 0.1) * 10) / 10);
                    },
                    zoomOut() {
                        this.zoom = Math.max(this.minZoom, Math.round((this.zoom - 0.1) * 10) / 10);
                    },
                    resetZoom() {
                        this.zoom = 1;
                    },
                    toggleFullscreen() {
                        const element = this.$root;

                        if (document.fullscreenElement) {
                            document.exitFullscreen?.();
                            return;
                        }

                        element.requestFullscreen?.();
                    },
                    openSelected() {
                        const marker = this.selectedMarker();

                        if (marker?.url) {
                            window.location.href = marker.url;
                        }
                    },
                    surfaceStyle() {
                        return `--fieldops-frame-zoom: ${this.zoom};`;
                    },
                    markerStyle(marker) {
                        return `left: ${marker.left}%; top: ${marker.top}%; width: ${marker.size}px; height: ${marker.size}px;`;
                    },
                };
            };
        </script>
    @endpush
@endonce

<div
    class="fieldops-luminaire-frame-spatial"
    x-data="window.fieldopsLuminaireFrameLayout(@js($payload))"
    x-init="init()"
>
    <div class="fieldops-luminaire-frame-spatial__header">
        <div>
            <div class="fieldops-luminaire-frame-spatial__eyebrow">
                {{ $payload['eyebrow'] }}
            </div>

            <div class="fieldops-luminaire-frame-spatial__title">
                {{ $payload['title'] }}
            </div>

            <div class="fieldops-luminaire-frame-spatial__subtitle">
                {{ $payload['subtitle'] }}
            </div>

            <div class="fieldops-luminaire-frame-spatial__chips">
                @if ($payload['frameType'])
                    <span class="fieldops-luminaire-frame-spatial__chip">
                        {{ $payload['frameType'] }}
                    </span>
                @endif

                @foreach ($payload['summary'] as $item)
                    <span class="fieldops-luminaire-frame-spatial__summary-pill">
                        <span>{{ $item['label'] }}</span>
                        <span class="fieldops-luminaire-frame-spatial__summary-value">{{ $item['value'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>

        <div class="fieldops-luminaire-frame-spatial__toolbar">
            <button type="button" class="fieldops-luminaire-frame-spatial__button" @click="zoomOut()">
                {{ __('fieldops::resource.luminaire_frames.view.zoom_out') }}
            </button>
            <button type="button" class="fieldops-luminaire-frame-spatial__button" @click="zoomIn()">
                {{ __('fieldops::resource.luminaire_frames.view.zoom_in') }}
            </button>
            <button type="button" class="fieldops-luminaire-frame-spatial__button" @click="resetZoom()">
                {{ __('fieldops::resource.luminaire_frames.view.reset_zoom') }}
            </button>
            <button type="button" class="fieldops-luminaire-frame-spatial__button fieldops-luminaire-frame-spatial__button--primary" @click="toggleFullscreen()">
                <span x-show="!isFullscreen">{{ __('fieldops::resource.luminaire_frames.view.fullscreen') }}</span>
                <span x-show="isFullscreen" x-cloak>{{ __('fieldops::resource.luminaire_frames.view.exit_fullscreen') }}</span>
            </button>
        </div>
    </div>

    <div class="fieldops-luminaire-frame-spatial__body">
        <div class="fieldops-luminaire-frame-spatial__main">
            <div class="fieldops-luminaire-frame-spatial__board-shell">
                <div class="fieldops-luminaire-frame-spatial__board-header">
                    <div>
                        <div class="fieldops-luminaire-frame-spatial__board-title">
                            {{ __('fieldops::resource.luminaire_frames.view.sidebar_title') }}
                        </div>
                        <div class="fieldops-luminaire-frame-spatial__board-subtitle">
                            {{ __('fieldops::resource.luminaire_frames.view.layout_hint') }}
                        </div>
                    </div>

                    <div class="fieldops-luminaire-frame-spatial__board-meta">
                        @if ($bounds)
                            <span class="fieldops-luminaire-frame-spatial__board-pill">X {{ $bounds['minX'] }} - {{ $bounds['maxX'] }}</span>
                            <span class="fieldops-luminaire-frame-spatial__board-pill">Y {{ $bounds['minY'] }} - {{ $bounds['maxY'] }}</span>
                        @endif
                        <span class="fieldops-luminaire-frame-spatial__board-pill">
                            {{ count($markers) }} {{ __('fieldops::resource.luminaire_frames.view.summary_positioned') }}
                        </span>
                    </div>
                </div>

                <div class="fieldops-luminaire-frame-spatial__viewport">
                    @if (count($markers) > 0)
                        <div
                            class="fieldops-luminaire-frame-spatial__surface"
                            :style="surfaceStyle()"
                        >
                            @foreach ($markers as $marker)
                                <button
                                    type="button"
                                    class="fieldops-luminaire-frame-spatial__marker {{ $marker['selected'] ? 'fieldops-luminaire-frame-spatial__marker--selected' : '' }} {{ $marker['flagged'] ? 'fieldops-luminaire-frame-spatial__marker--flagged' : '' }}"
                                    :style="markerStyle(@js($marker))"
                                    @click="selectMarker({{ $marker['id'] }})"
                                    :aria-pressed="Number(selectedId) === {{ $marker['id'] }}"
                                    :aria-label="'{{ $marker['title'] }} #{{ $marker['label'] }}'"
                                    title="{{ $marker['title'] }} #{{ $marker['label'] }}"
                                >
                                    <span class="fieldops-luminaire-frame-spatial__marker-label">{{ $marker['label'] }}</span>
                                    @if ($marker['flagged'])
                                        <span class="fieldops-luminaire-frame-spatial__marker-sub" aria-hidden="true">!</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="fieldops-luminaire-frame-spatial__empty">
                            <div class="fieldops-luminaire-frame-spatial__empty-copy">
                                <div class="fieldops-luminaire-frame-spatial__empty-title">
                                    {{ __('fieldops::resource.luminaire_frames.view.layout_empty_title') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__empty-text">
                                    {{ __('fieldops::resource.luminaire_frames.view.layout_empty_text') }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="fieldops-luminaire-frame-spatial__hint">
                    {{ __('fieldops::resource.luminaire_frames.view.layout_hint') }}
                </div>
            </div>
        </div>

        <aside class="fieldops-luminaire-frame-spatial__rail">
            <div class="fieldops-luminaire-frame-spatial__panel">
                <div class="fieldops-luminaire-frame-spatial__panel-header">
                    <div>
                        <div class="fieldops-luminaire-frame-spatial__sidebar-title">
                            {{ __('fieldops::resource.luminaire_frames.view.sidebar_title') }}
                        </div>
                        <div class="fieldops-luminaire-frame-spatial__sidebar-subtitle">
                            {{ __('fieldops::resource.luminaire_frames.view.sidebar_subtitle') }}
                        </div>
                    </div>
                </div>

                <div class="fieldops-luminaire-frame-spatial__list">
                    @foreach ($markers as $marker)
                        <button
                            type="button"
                            class="fieldops-luminaire-frame-spatial__sidebar-item {{ $marker['selected'] ? 'fieldops-luminaire-frame-spatial__sidebar-item--active' : '' }}"
                            @click="selectMarker({{ $marker['id'] }})"
                            :aria-pressed="Number(selectedId) === {{ $marker['id'] }}"
                        >
                            <span class="min-w-0 text-left">
                                <span class="fieldops-luminaire-frame-spatial__sidebar-name">
                                    {{ $marker['title'] }}
                                </span>
                                <span class="fieldops-luminaire-frame-spatial__sidebar-hint block">
                                    #{{ $marker['label'] }} · {{ $marker['positionLabel'] }}
                                </span>
                            </span>

                            <span class="fieldops-luminaire-frame-spatial__mini-pill">
                                {{ $marker['flagged'] ? __('fieldops::resource.luminaire_frames.view.open') : __('fieldops::resource.luminaire_frames.view.resolved') }}
                            </span>
                        </button>
                    @endforeach

                    @if (count($unpositioned) > 0)
                        <div class="pt-2">
                            <div class="fieldops-luminaire-frame-spatial__sidebar-title">
                                {{ __('fieldops::resource.luminaire_frames.view.unpositioned_title') }}
                            </div>
                            <div class="fieldops-luminaire-frame-spatial__sidebar-subtitle">
                                {{ __('fieldops::resource.luminaire_frames.view.unpositioned_text') }}
                            </div>
                        </div>

                        @foreach ($unpositioned as $item)
                            <button
                                type="button"
                                class="fieldops-luminaire-frame-spatial__sidebar-item {{ $item['selected'] ? 'fieldops-luminaire-frame-spatial__sidebar-item--active' : '' }}"
                                @click="selectMarker({{ $item['id'] }})"
                                :aria-pressed="Number(selectedId) === {{ $item['id'] }}"
                            >
                                <span class="min-w-0 text-left">
                                    <span class="fieldops-luminaire-frame-spatial__sidebar-name">
                                        {{ $item['title'] }}
                                    </span>
                                    <span class="fieldops-luminaire-frame-spatial__sidebar-hint block">
                                        #{{ $item['label'] }} · {{ __('fieldops::resource.luminaire_frames.view.no_position') }}
                                    </span>
                                </span>

                                <span class="fieldops-luminaire-frame-spatial__mini-pill">
                                    {{ $item['flagged'] ? __('fieldops::resource.luminaire_frames.view.open') : __('fieldops::resource.luminaire_frames.view.resolved') }}
                                </span>
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="fieldops-luminaire-frame-spatial__panel fieldops-luminaire-frame-spatial__selected-card">
                @php($selected = $data['selectedMarker'] ?? null)

                <div class="fieldops-luminaire-frame-spatial__selected-title">
                    {{ __('fieldops::resource.luminaire_frames.view.selected_label') }}
                </div>

                @if ($selected)
                    <div class="fieldops-luminaire-frame-spatial__selected-row">
                        <div class="fieldops-luminaire-frame-spatial__selected-stat">
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                {{ __('fieldops::resource.luminaire_frames.view.selected_type') }}
                            </div>
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-value">
                                {{ $selected['title'] }}
                            </div>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-stat">
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                {{ __('fieldops::resource.luminaire_frames.view.selected_serial') }}
                            </div>
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-value">
                                {{ $selected['serial'] ?? '—' }}
                            </div>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-stat">
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                {{ __('fieldops::resource.luminaire_frames.view.selected_position') }}
                            </div>
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-value">
                                {{ $selected['positionLabel'] }}
                            </div>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-stat">
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                {{ __('fieldops::resource.luminaire_frames.view.selected_scale') }}
                            </div>
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-value">
                                X {{ $selected['scaleX'] ?? '—' }} / Y {{ $selected['scaleY'] ?? '—' }}
                            </div>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-stat">
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                {{ __('fieldops::resource.luminaire_frames.view.selected_state') }}
                            </div>
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-value">
                                {{ $selected['flagged'] ? __('fieldops::resource.luminaire_frames.view.open') : __('fieldops::resource.luminaire_frames.view.resolved') }}
                            </div>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-stat">
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                {{ __('fieldops::resource.luminaire_frames.view.selected_label') }}
                            </div>
                            <div class="fieldops-luminaire-frame-spatial__selected-stat-value">
                                #{{ $selected['label'] }}
                            </div>
                        </div>
                    </div>

                    <div class="fieldops-luminaire-frame-spatial__selected-actions">
                        <a class="fieldops-luminaire-frame-spatial__link" href="{{ $selected['url'] }}">
                            {{ __('fieldops::resource.luminaire_frames.view.open_luminaire') }}
                        </a>
                        <button type="button" class="fieldops-luminaire-frame-spatial__link fieldops-luminaire-frame-spatial__link--ghost" @click="selectMarker({{ $selected['id'] }})">
                            {{ __('fieldops::resource.luminaire_frames.view.selected_label') }}
                        </button>
                    </div>
                @else
                    <div class="fieldops-luminaire-frame-spatial__empty-text">
                        {{ __('fieldops::resource.luminaire_frames.view.selected_empty') }}
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
