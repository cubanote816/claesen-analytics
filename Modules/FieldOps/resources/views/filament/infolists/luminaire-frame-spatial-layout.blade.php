@php
    /**
     * @var array{
     *     eyebrow: string,
     *     title: string,
     *     subtitle: string,
     *     frameType: ?string,
     *     frameImage: ?string,
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
     *         imageUrl: ?string,
     *         hasImage: bool,
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
     *         imageUrl: ?string,
     *         hasImage: bool,
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
        'frameImage' => $data['frameImage'] ?? null,
        'title' => $data['title'] ?? '',
        'subtitle' => $data['subtitle'] ?? '',
        'eyebrow' => $data['eyebrow'] ?? '',
        'selectedMarker' => $data['selectedMarker'] ?? null,
    ];

    $markers = $payload['markers'];
    $unpositioned = $payload['unpositioned'];
    $summary = $payload['summary'];
    $bounds = $payload['bounds'];
    $selected = $payload['selectedMarker'];
    $placeholderImage = asset('assets/luminaire-subgroups/image_placeholder.png');
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
                aspect-ratio: var(--fieldops-frame-ratio, 2 / 1);
                margin: 0 auto;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 1rem;
                overflow: hidden;
                background:
                    radial-gradient(circle at center, rgba(255, 255, 255, 0.12), transparent 72%),
                    linear-gradient(180deg, rgba(248, 250, 252, 0.88), rgba(234, 244, 250, 0.96));
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.42);
                --fieldops-frame-zoom: 1;
            }

            .dark .fieldops-luminaire-frame-spatial__surface {
                border-color: rgba(255, 255, 255, 0.08);
                background:
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
                z-index: 1;
            }

            .fieldops-luminaire-frame-spatial__surface::after {
                border-radius: inherit;
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
                mix-blend-mode: soft-light;
                z-index: 1;
            }

            .fieldops-luminaire-frame-spatial__surface-frame {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: contain;
                object-position: center center;
                z-index: 0;
                pointer-events: none;
                filter: saturate(0.95) contrast(0.98);
            }

            .fieldops-luminaire-frame-spatial__surface-frame--fallback {
                background:
                    radial-gradient(circle at top left, rgba(0, 174, 239, 0.14), transparent 30%),
                    linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(17, 24, 39, 0.9));
            }

            .fieldops-luminaire-frame-spatial__surface-stage {
                position: absolute;
                inset: 0;
            }

            .fieldops-luminaire-frame-spatial__surface-grid {
                position: absolute;
                inset: 0;
                z-index: 2;
                pointer-events: none;
                background:
                    linear-gradient(to right, rgba(148, 163, 184, 0.12) 1px, transparent 1px),
                    linear-gradient(to bottom, rgba(148, 163, 184, 0.12) 1px, transparent 1px);
                background-size: 10% 10%, 10% 10%;
                mix-blend-mode: soft-light;
            }

            .dark .fieldops-luminaire-frame-spatial__surface-grid {
                background:
                    linear-gradient(to right, rgba(148, 163, 184, 0.1) 1px, transparent 1px),
                    linear-gradient(to bottom, rgba(148, 163, 184, 0.1) 1px, transparent 1px);
                background-size: 10% 10%, 10% 10%;
            }

            .fieldops-luminaire-frame-spatial__surface-placeholder {
                position: absolute;
                inset: 0;
                z-index: 1;
                display: grid;
                place-items: center;
                padding: 1.25rem;
                text-align: center;
                color: #64748b;
            }

            .dark .fieldops-luminaire-frame-spatial__surface-placeholder {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-spatial__surface-placeholder-copy {
                max-width: 28rem;
                padding: 0.85rem 1rem;
                border: 1px solid rgba(148, 163, 184, 0.16);
                border-radius: 0.95rem;
                background: rgba(15, 23, 42, 0.22);
                backdrop-filter: blur(12px);
            }

            .fieldops-luminaire-frame-spatial__surface-placeholder-title {
                color: #e2e8f0;
                font-size: 0.86rem;
                font-weight: 850;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .fieldops-luminaire-frame-spatial__surface-placeholder-text {
                margin-top: 0.4rem;
                color: #94a3b8;
                font-size: 0.82rem;
                line-height: 1.45;
            }

            .fieldops-luminaire-frame-spatial__marker {
                position: relative;
                z-index: 2;
                width: 100%;
                height: 100%;
                display: block;
                padding: 0;
                border: 1px solid rgba(148, 163, 184, 0.42);
                border-radius: 0.7rem;
                overflow: hidden;
                background:
                    linear-gradient(180deg, rgba(15, 23, 42, 0.96) 0%, rgba(17, 24, 39, 0.98) 100%);
                color: #ffffff;
                box-shadow: 0 12px 22px rgba(2, 6, 23, 0.28);
                transition: transform 150ms ease, box-shadow 150ms ease, background-color 150ms ease, border-color 150ms ease;
                cursor: grab;
                touch-action: none;
                user-select: none;
            }

            .fieldops-luminaire-frame-spatial__marker--selected {
                border-color: rgba(56, 189, 248, 0.92);
                box-shadow: 0 0 0 0.22rem rgba(56, 189, 248, 0.16), 0 18px 32px rgba(2, 132, 199, 0.24);
            }

            .fieldops-luminaire-frame-spatial__marker--flagged {
                border-color: rgba(251, 191, 36, 0.88);
            }

            .fieldops-luminaire-frame-spatial__marker-media {
                position: absolute;
                inset: 0;
                z-index: 0;
            }

            .fieldops-luminaire-frame-spatial__marker-image {
                width: 100%;
                height: 100%;
                object-fit: contain;
                object-position: center center;
                display: block;
                padding: 0.3rem;
                filter: saturate(0.82) contrast(1.04) brightness(0.98);
            }

            .fieldops-luminaire-frame-spatial__marker-fallback {
                position: absolute;
                inset: 0;
                display: grid;
                place-items: center;
                gap: 0.25rem;
                padding: 0.35rem;
                text-align: center;
                background:
                    radial-gradient(circle at top, rgba(56, 189, 248, 0.16), transparent 42%),
                    linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(17, 24, 39, 0.98));
            }

            .fieldops-luminaire-frame-spatial__marker-fallback-icon {
                width: 1.1rem;
                height: 1.1rem;
                color: rgba(226, 232, 240, 0.9);
            }

            .fieldops-luminaire-frame-spatial__marker-fallback-text {
                max-width: 100%;
                color: #cbd5e1;
                font-size: 0.55rem;
                font-weight: 800;
                line-height: 1.1;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }

            .fieldops-luminaire-frame-spatial__marker-shell {
                position: absolute;
                z-index: 3;
                transform: translate(-50%, -50%);
                will-change: left, top;
            }

            .dark .fieldops-luminaire-frame-spatial__marker {
                border-color: rgba(15, 23, 42, 0.95);
                box-shadow: 0 18px 30px rgba(0, 174, 239, 0.32);
            }

            .fieldops-luminaire-frame-spatial__marker-label {
                position: absolute;
                left: 0.3rem;
                top: 0.3rem;
                z-index: 2;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 1.3rem;
                height: 1.3rem;
                padding: 0 0.25rem;
                border-radius: 0.35rem;
                background: rgba(15, 23, 42, 0.84);
                border: 1px solid rgba(148, 163, 184, 0.26);
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 0.66rem;
                font-weight: 900;
                line-height: 1;
                letter-spacing: 0.04em;
                backdrop-filter: blur(8px);
            }

            .fieldops-luminaire-frame-spatial__marker-sub {
                position: absolute;
                right: 0.3rem;
                bottom: 0.3rem;
                z-index: 2;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 0.95rem;
                height: 0.95rem;
                border: 1px solid rgba(148, 163, 184, 0.3);
                border-radius: 0.3rem;
                background: rgba(15, 23, 42, 0.92);
                color: #e2e8f0;
                font-size: 0.48rem;
                font-weight: 900;
            }

            .fieldops-luminaire-frame-spatial__marker-open {
                position: absolute;
                z-index: 4;
                right: 0.25rem;
                top: 0.25rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 1.25rem;
                height: 1.25rem;
                border: 1px solid rgba(148, 163, 184, 0.28);
                border-radius: 0.35rem;
                background: rgba(15, 23, 42, 0.86);
                color: #f8fafc;
                box-shadow: 0 8px 14px rgba(0, 0, 0, 0.22);
                transition: transform 150ms ease, border-color 150ms ease, background-color 150ms ease;
            }

            .fieldops-luminaire-frame-spatial__marker-open:hover {
                transform: scale(1.04);
                border-color: rgba(56, 189, 248, 0.46);
                background: rgba(14, 165, 233, 0.92);
            }

            .fieldops-luminaire-frame-spatial__marker-open svg {
                width: 0.78rem;
                height: 0.78rem;
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
                flex-wrap: wrap;
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

            .fieldops-luminaire-frame-spatial__hint {
                padding-top: 0.2rem;
            }

            .fieldops-luminaire-frame-spatial__drag-hint {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                margin-top: 0.6rem;
                color: #7dd3fc;
                font-size: 0.82rem;
                font-weight: 700;
            }

            .dark .fieldops-luminaire-frame-spatial__drag-hint {
                color: #7dd3fc;
            }

            .fieldops-luminaire-frame-spatial__selected-hint {
                margin-top: 0.35rem;
                color: #64748b;
                font-size: 0.82rem;
                line-height: 1.45;
            }

            .dark .fieldops-luminaire-frame-spatial__selected-hint {
                color: #94a3b8;
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
                    frameRatio: '2 / 1',
                    selectedId: payload.selectedId ?? null,
                    markers: (payload.markers ?? []).map((item) => ({
                        ...item,
                        left: Number(item.left ?? item.frameX ?? 50),
                        top: Number(item.top ?? item.frameY ?? 50),
                        size: Number(item.size ?? 30),
                    })),
                    unpositioned: payload.unpositioned ?? [],
                    summary: payload.summary ?? [],
                    bounds: payload.bounds ?? null,
                    frameType: payload.frameType ?? null,
                    frameImage: payload.frameImage ?? null,
                    draggingId: null,
                    dragPointerId: null,
                    dragStartX: 0,
                    dragStartY: 0,
                    dragOffsetX: 0,
                    dragOffsetY: 0,
                    dragStarted: false,
                    suppressNextClick: false,
                    statusMessage: null,
                    savePending: false,
                    dragShellElement: null,
                    init() {
                        if (this.selectedId === null) {
                            const first = this.markers[0] ?? this.unpositioned[0] ?? null;
                            this.selectedId = first ? Number(first.id) : null;
                        }

                        if (this.frameImage) {
                            this.$nextTick(() => this.measureFrameRatio());
                        }

                        document.addEventListener('fullscreenchange', () => {
                            this.isFullscreen = Boolean(document.fullscreenElement);
                        });
                    },
                    isFullscreen: false,
                    clamp(value, min, max) {
                        return Math.min(max, Math.max(min, value));
                    },
                    measureFrameRatio() {
                        const image = this.$refs.frameImage;
                        const stage = this.$refs.stage;

                        if (!image || !stage) {
                            return;
                        }

                        const naturalWidth = Number(image.naturalWidth ?? 0);
                        const naturalHeight = Number(image.naturalHeight ?? 0);

                        if (naturalWidth > 0 && naturalHeight > 0) {
                            this.frameRatio = `${naturalWidth} / ${naturalHeight}`;
                            stage.style.setProperty('--fieldops-frame-ratio', this.frameRatio);
                        }

                        this.$nextTick(() => this.refreshMarkerSizes());
                    },
                    getMarker(id) {
                        return this.markers.find((item) => Number(item.id) === Number(id))
                            ?? this.unpositioned.find((item) => Number(item.id) === Number(id))
                            ?? null;
                    },
                    refreshMarkerSizes() {
                        const stage = this.$refs.stage;
                        if (!stage) {
                            return;
                        }

                        stage.querySelectorAll('.fieldops-luminaire-frame-spatial__marker-shell').forEach((shell) => {
                            const marker = this.getMarker(shell.dataset.markerId);
                            if (!marker) {
                                return;
                            }

                            const size = Math.max(24, Math.round(Number(marker.size) * this.zoom));
                            shell.style.width = `${size}px`;
                            shell.style.height = `${size}px`;
                        });
                    },
                    selectedMarker() {
                        return this.getMarker(this.selectedId);
                    },
                    selectedMarkerTitle() {
                        return this.selectedMarker()?.title ?? '';
                    },
                    selectedMarkerSerial() {
                        return this.selectedMarker()?.serial ?? '—';
                    },
                    selectedMarkerPositionLabel() {
                        return this.selectedMarker()?.positionLabel ?? '';
                    },
                    selectedMarkerScaleLabel() {
                        const marker = this.selectedMarker();
                        if (!marker) {
                            return '—';
                        }

                        return `X ${marker.scaleX ?? '—'} / Y ${marker.scaleY ?? '—'}`;
                    },
                    selectedMarkerStateLabel() {
                        const marker = this.selectedMarker();

                        return marker
                            ? (marker.flagged
                                ? @js(__('fieldops::resource.luminaire_frames.view.open'))
                                : @js(__('fieldops::resource.luminaire_frames.view.resolved')))
                            : '';
                    },
                    selectedMarkerSourceLabel() {
                        const marker = this.selectedMarker();
                        if (!marker || !marker.positionSource) {
                            return '—';
                        }

                        const labels = {
                            frontend: @js(__('fieldops::resource.luminaires.position_sources.frontend')),
                            backoffice: @js(__('fieldops::resource.luminaires.position_sources.backoffice')),
                        };

                        return labels[marker.positionSource] ?? marker.positionSource;
                    },
                    selectedMarkerVerifiedAtLabel() {
                        const marker = this.selectedMarker();
                        if (!marker || !marker.positionVerifiedAt) {
                            return '—';
                        }

                        try {
                            return new Intl.DateTimeFormat(undefined, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            }).format(new Date(marker.positionVerifiedAt));
                        } catch (error) {
                            return marker.positionVerifiedAt;
                        }
                    },
                    selectedMarkerLabel() {
                        return this.selectedMarker()?.label ?? '';
                    },
                    selectMarker(id) {
                        if (this.suppressNextClick) {
                            return;
                        }

                        this.selectedId = Number(id);
                    },
                    handleMarkerClick(id) {
                        this.selectMarker(id);
                    },
                    beginDrag(event, id) {
                        const marker = this.markers.find((item) => Number(item.id) === Number(id));
                        if (!marker) {
                            return;
                        }

                        this.dragPointerId = event.pointerId;
                        this.draggingId = Number(id);
                        this.dragStarted = false;
                        this.dragStartX = event.clientX;
                        this.dragStartY = event.clientY;
                        this.dragShellElement = event.currentTarget?.parentElement ?? null;

                        const rect = this.$refs.stage?.getBoundingClientRect();
                        if (!rect) {
                            return;
                        }

                        const pointerLeft = ((event.clientX - rect.left) / rect.width) * 100;
                        const pointerTop = ((event.clientY - rect.top) / rect.height) * 100;

                        this.dragOffsetX = pointerLeft - Number(marker.left);
                        this.dragOffsetY = pointerTop - Number(marker.top);
                    },
                    onPointerMove(event) {
                        if (this.dragPointerId === null || event.pointerId !== this.dragPointerId) {
                            return;
                        }

                        const marker = this.markers.find((item) => Number(item.id) === Number(this.draggingId));
                        if (!marker) {
                            return;
                        }

                        const distance = Math.hypot(
                            event.clientX - this.dragStartX,
                            event.clientY - this.dragStartY,
                        );

                        if (!this.dragStarted) {
                            if (distance < 6) {
                                return;
                            }

                            this.dragStarted = true;
                            this.suppressNextClick = true;
                        }

                        const rect = this.$refs.stage?.getBoundingClientRect();
                        if (!rect) {
                            return;
                        }

                        const pointerLeft = ((event.clientX - rect.left) / rect.width) * 100;
                        const pointerTop = ((event.clientY - rect.top) / rect.height) * 100;

                        marker.left = this.clamp(pointerLeft - this.dragOffsetX, 0, 100);
                        marker.top = this.clamp(pointerTop - this.dragOffsetY, 0, 100);

                        if (this.dragShellElement) {
                            const size = Math.max(24, Math.round(Number(marker.size) * this.zoom));
                            this.dragShellElement.style.left = `${marker.left}%`;
                            this.dragShellElement.style.top = `${marker.top}%`;
                            this.dragShellElement.style.width = `${size}px`;
                            this.dragShellElement.style.height = `${size}px`;
                        }
                    },
                    async onPointerUp(event) {
                        if (this.dragPointerId === null || event.pointerId !== this.dragPointerId) {
                            return;
                        }

                        const marker = this.markers.find((item) => Number(item.id) === Number(this.draggingId));
                        const wasDragging = this.dragStarted;

                        this.dragPointerId = null;
                        this.draggingId = null;
                        this.dragStarted = false;
                        this.dragShellElement = null;

                        if (wasDragging && marker) {
                            await this.persistMarkerPosition(marker);
                        }

                        window.setTimeout(() => {
                            this.suppressNextClick = false;
                        }, 0);
                    },
                    async persistMarkerPosition(marker, retryOnConflict = true) {
                        if (this.savePending) {
                            return;
                        }

                        this.savePending = true;
                        this.statusMessage = null;

                        try {
                            const response = await fetch(marker.updateUrl, {
                                method: 'PATCH',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-FieldOps-Editor': 'backoffice',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    frame_x: Math.round((Number(marker.left) / 100) * 10000) / 10000,
                                    frame_y: Math.round((Number(marker.top) / 100) * 10000) / 10000,
                                    position_version: Number(marker.positionVersion ?? 1),
                                }),
                            });

                            if (!response.ok) {
                                if (response.status === 409) {
                                    const payload = await response.json().catch(() => ({}));
                                    const currentVersion = Number(payload.current_position_version ?? marker.positionVersion ?? 1);
                                    marker.positionVersion = currentVersion;

                                    if (retryOnConflict && Number.isFinite(currentVersion) && currentVersion > 0) {
                                        return await this.persistMarkerPosition(marker, false);
                                    }

                                    this.statusMessage = payload.message ?? @js(__('fieldops::resource.luminaire_frames.view.save_failed'));
                                    return;
                                }

                                throw new Error(`Unable to save marker position (${response.status})`);
                            }

                            const payload = await response.json().catch(() => ({}));
                            const updated = payload?.data ?? payload ?? null;

                            if (updated) {
                                marker.left = this.clamp(Number(updated.frame_x ?? marker.left), 0, 100);
                                marker.top = this.clamp(Number(updated.frame_y ?? marker.top), 0, 100);
                                marker.positionVersion = Number(updated.position_version ?? marker.positionVersion ?? 1);
                                marker.positionSource = updated.position_source ?? marker.positionSource;
                                marker.positionVerifiedAt = updated.position_verified_at ?? marker.positionVerifiedAt;

                                if (this.selectedId === Number(marker.id)) {
                                    this.selectedId = Number(marker.id);
                                }

                                this.$nextTick(() => this.refreshMarkerSizes());
                            }

                            this.statusMessage = null;
                        } catch (error) {
                            console.error(error);
                            this.statusMessage = @js(__('fieldops::resource.luminaire_frames.view.save_failed'));
                        } finally {
                            this.savePending = false;
                        }
                    },
                    handleMarkerKeydown(event, id) {
                        const step = event.shiftKey ? 5 : 1;
                        const marker = this.markers.find((item) => Number(item.id) === Number(id));

                        if (!marker) {
                            return;
                        }

                        let moved = false;

                        switch (event.key) {
                            case 'ArrowUp':
                                marker.top = this.clamp(Number(marker.top) - step, 0, 100);
                                moved = true;
                                break;
                            case 'ArrowDown':
                                marker.top = this.clamp(Number(marker.top) + step, 0, 100);
                                moved = true;
                                break;
                            case 'ArrowLeft':
                                marker.left = this.clamp(Number(marker.left) - step, 0, 100);
                                moved = true;
                                break;
                            case 'ArrowRight':
                                marker.left = this.clamp(Number(marker.left) + step, 0, 100);
                                moved = true;
                                break;
                            case 'Enter':
                            case ' ':
                                this.selectMarker(id);
                                event.preventDefault();
                                return;
                            default:
                                return;
                        }

                        if (moved) {
                            event.preventDefault();
                            const shell = event.currentTarget?.parentElement ?? null;
                            if (shell) {
                                const size = Math.max(24, Math.round(Number(marker.size) * this.zoom));
                                shell.style.left = `${marker.left}%`;
                                shell.style.top = `${marker.top}%`;
                                shell.style.width = `${size}px`;
                                shell.style.height = `${size}px`;
                            }
                            this.persistMarkerPosition(marker);
                        }
                    },
                    zoomIn() {
                        this.zoom = Math.min(this.maxZoom, Math.round((this.zoom + 0.1) * 10) / 10);
                        this.refreshMarkerSizes();
                    },
                    zoomOut() {
                        this.zoom = Math.max(this.minZoom, Math.round((this.zoom - 0.1) * 10) / 10);
                        this.refreshMarkerSizes();
                    },
                    resetZoom() {
                        this.zoom = 1;
                        this.refreshMarkerSizes();
                    },
                    toggleFullscreen() {
                        const element = this.$root;

                        if (document.fullscreenElement) {
                            document.exitFullscreen?.();
                            return;
                        }

                        element.requestFullscreen?.();
                    },
                    surfaceStyle() {
                        return `--fieldops-frame-zoom: ${this.zoom}; --fieldops-frame-ratio: ${this.frameRatio};`;
                    },
                    markerStyle(marker) {
                        const size = Math.max(24, Math.round(Number(marker.size) * this.zoom));

                        return `left: ${marker.left}%; top: ${marker.top}%; width: ${size}px; height: ${size}px;`;
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
                            {{ __('fieldops::resource.luminaire_frames.view.canvas_label') }}
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
                            x-ref="stage"
                            :style="surfaceStyle()"
                            @pointermove.window="onPointerMove($event)"
                            @pointerup.window="onPointerUp($event)"
                        >
                            <div class="fieldops-luminaire-frame-spatial__surface-stage">
                                @if ($payload['frameImage'])
                                    <img
                                        x-ref="frameImage"
                                        class="fieldops-luminaire-frame-spatial__surface-frame"
                                        src="{{ $payload['frameImage'] }}"
                                        alt="{{ $payload['frameType'] ? $payload['frameType'].' frame background' : 'Luminaire frame background' }}"
                                        @load="measureFrameRatio()"
                                    >
                                @else
                                    <div class="fieldops-luminaire-frame-spatial__surface-frame fieldops-luminaire-frame-spatial__surface-frame--fallback" aria-hidden="true"></div>

                                    <div class="fieldops-luminaire-frame-spatial__surface-placeholder">
                                        <div class="fieldops-luminaire-frame-spatial__surface-placeholder-copy">
                                            <div class="fieldops-luminaire-frame-spatial__surface-placeholder-title">
                                                {{ __('fieldops::resource.luminaire_frames.view.layout_empty_title') }}
                                            </div>
                                            <div class="fieldops-luminaire-frame-spatial__surface-placeholder-text">
                                                {{ __('fieldops::resource.luminaire_frames.view.layout_empty_text') }}
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="fieldops-luminaire-frame-spatial__surface-grid" aria-hidden="true"></div>

                                @foreach ($markers as $marker)
                                    <div
                                        class="fieldops-luminaire-frame-spatial__marker-shell"
                                        data-marker-id="{{ $marker['id'] }}"
                                        :style="markerStyle(@js($marker))"
                                    >
                                        <button
                                            type="button"
                                            class="fieldops-luminaire-frame-spatial__marker {{ $marker['flagged'] ? 'fieldops-luminaire-frame-spatial__marker--flagged' : '' }} {{ $marker['hasImage'] ? 'fieldops-luminaire-frame-spatial__marker--image' : 'fieldops-luminaire-frame-spatial__marker--placeholder' }}"
                                            :class="Number(selectedId) === {{ $marker['id'] }} ? 'fieldops-luminaire-frame-spatial__marker--selected' : ''"
                                            @pointerdown="beginDrag($event, {{ $marker['id'] }})"
                                            @click="handleMarkerClick({{ $marker['id'] }})"
                                            @keydown="handleMarkerKeydown($event, {{ $marker['id'] }})"
                                            :aria-pressed="Number(selectedId) === {{ $marker['id'] }}"
                                            :aria-label="'{{ $marker['title'] }} #{{ $marker['label'] }}'"
                                            title="{{ $marker['title'] }} #{{ $marker['label'] }}"
                                        >
                                            @if ($marker['hasImage'])
                                                <div class="fieldops-luminaire-frame-spatial__marker-media" aria-hidden="true">
                                                    <img
                                                        class="fieldops-luminaire-frame-spatial__marker-image"
                                                        src="{{ $marker['imageUrl'] }}"
                                                        alt=""
                                                        loading="lazy"
                                                        onerror="this.onerror=null;this.src='{{ $placeholderImage }}';"
                                                    >
                                                </div>
                                            @else
                                                <div class="fieldops-luminaire-frame-spatial__marker-fallback" aria-hidden="true">
                                                    <svg class="fieldops-luminaire-frame-spatial__marker-fallback-icon" viewBox="0 0 24 24" fill="none">
                                                        <path d="M12 3v18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                                        <path d="M5 10h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                                        <path d="M7.5 7h9l2 3.5-6.5 6.5-6.5-6.5L7.5 7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                                    </svg>
                                                    <span class="fieldops-luminaire-frame-spatial__marker-fallback-text">
                                                        {{ __('fieldops::resource.luminaire_frames.view.layout_empty_title') }}
                                                    </span>
                                                </div>
                                            @endif

                                            <span class="fieldops-luminaire-frame-spatial__marker-label">{{ $marker['label'] }}</span>
                                            @if ($marker['flagged'])
                                                <span class="fieldops-luminaire-frame-spatial__marker-sub" aria-hidden="true">!</span>
                                            @endif
                                        </button>

                                        <a
                                            href="{{ $marker['url'] }}"
                                            class="fieldops-luminaire-frame-spatial__marker-open"
                                            title="{{ __('fieldops::resource.luminaire_frames.view.open_position_details') }}"
                                            aria-label="{{ __('fieldops::resource.luminaire_frames.view.open_position_details') }}"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M14 5h5v5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M10 14L19 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M19 14v5h-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M5 10v9h9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
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
                <div class="fieldops-luminaire-frame-spatial__drag-hint">
                    <span aria-hidden="true">↕</span>
                    <span>{{ __('fieldops::resource.luminaire_frames.view.drag_hint') }}</span>
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
                            class="fieldops-luminaire-frame-spatial__sidebar-item"
                            :class="Number(selectedId) === {{ $marker['id'] }} ? 'fieldops-luminaire-frame-spatial__sidebar-item--active' : ''"
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
                                class="fieldops-luminaire-frame-spatial__sidebar-item"
                                :class="Number(selectedId) === {{ $item['id'] }} ? 'fieldops-luminaire-frame-spatial__sidebar-item--active' : ''"
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
                <div class="fieldops-luminaire-frame-spatial__selected-title">
                    {{ __('fieldops::resource.luminaire_frames.view.selected_position_label') }}
                </div>

                <template x-if="selectedMarker()">
                    <div>
                        <div class="fieldops-luminaire-frame-spatial__selected-hint">
                            {{ __('fieldops::resource.luminaire_frames.view.selected_hint') }}
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-row">
                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaire_frames.view.selected_type') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value" x-text="selectedMarkerTitle()"></div>
                            </div>

                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaire_frames.view.selected_serial') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value" x-text="selectedMarkerSerial()"></div>
                            </div>

                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaire_frames.view.selected_position') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value" x-text="selectedMarkerPositionLabel()"></div>
                            </div>

                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaire_frames.view.selected_scale') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value" x-text="selectedMarkerScaleLabel()"></div>
                            </div>

                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaire_frames.view.selected_state') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value" x-text="selectedMarkerStateLabel()"></div>
                            </div>

                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaires.fields.position_source') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value" x-text="selectedMarkerSourceLabel()"></div>
                            </div>

                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaires.fields.position_verified_at') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value" x-text="selectedMarkerVerifiedAtLabel()"></div>
                            </div>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-actions">
                            <a class="fieldops-luminaire-frame-spatial__link" :href="selectedMarker()?.url">
                                {{ __('fieldops::resource.luminaire_frames.view.open_position_details') }}
                            </a>
                        </div>

                        <div x-show="statusMessage" class="fieldops-luminaire-frame-spatial__selected-hint" x-text="statusMessage"></div>
                    </div>
                </template>

                @if ($selected)
                    <div x-show="!selectedMarker()" x-cloak>
                        <div class="fieldops-luminaire-frame-spatial__selected-hint">
                            {{ __('fieldops::resource.luminaire_frames.view.selected_hint') }}
                        </div>

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
                                    {{ __('fieldops::resource.luminaires.fields.position_source') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value">
                                    {{ $selected['positionSource'] ? __('fieldops::resource.luminaires.position_sources.'.$selected['positionSource']) : '—' }}
                                </div>
                            </div>

                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaires.fields.position_verified_at') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value">
                                    {{ $selected['positionVerifiedAt'] ? \Illuminate\Support\Carbon::parse($selected['positionVerifiedAt'])->format('d M Y H:i') : '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-actions">
                            <a class="fieldops-luminaire-frame-spatial__link" href="{{ $selected['url'] }}">
                                {{ __('fieldops::resource.luminaire_frames.view.open_position_details') }}
                            </a>
                        </div>
                    </div>
                @else
                    <div class="fieldops-luminaire-frame-spatial__empty-text" x-show="!selectedMarker()" x-cloak>
                        {{ __('fieldops::resource.luminaire_frames.view.selected_empty') }}
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
