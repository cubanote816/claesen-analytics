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
     *     frameId: int,
     *     createUrl: string,
     *     luminaireTypes: array<int, array{id: int, name: string, subgroupId: int, subgroupLabel: string, imageUrl: string, hasImage: bool}>,
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
        'frameId' => $data['frameId'] ?? null,
        'createUrl' => $data['createUrl'] ?? '',
        'luminaireTypes' => $data['luminaireTypes'] ?? [],
    ];

    $markers = $payload['markers'];
    $unpositioned = $payload['unpositioned'];
    $summary = $payload['summary'];
    $bounds = $payload['bounds'];
    $selected = $payload['selectedMarker'];
    $placeholderImage = asset('assets/luminaire-subgroups/image_placeholder.png');
@endphp

{{--
    @script/@assets (Livewire), not @push('scripts')/@once (plain Blade): Filament's
    panel navigation uses wire:navigate by default, which swaps the <body> via DOM
    morphing — a <script> tag inserted that way never executes (browsers only run
    <script> elements that are part of the initial parse). window.fieldopsLuminaireFrameLayout
    would never (re-)register on a soft navigation, leaving x-data="fieldopsLuminaireFrameLayout(...)"
    unresolved and this whole component silently inert (found in QA: works on a hard
    reload, breaks when reached by clicking through the panel — same bug already fixed
    in luminaire-frame-type-image-editor.blade.php, CLA-278). @script is Livewire's
    supported mechanism for JS that must run on every render regardless of morph/navigate.
--}}
    @assets
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
                grid-template-columns: minmax(0, 1fr);
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
            .fieldops-luminaire-frame-spatial__toolbar,
            .fieldops-luminaire-frame-spatial__mode-switcher {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .fieldops-luminaire-frame-spatial__chips {
                margin-top: 0.95rem;
            }

            .fieldops-luminaire-frame-spatial__header > .fieldops-luminaire-frame-spatial__chips {
                grid-column: 1 / -1;
                margin-top: 0;
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
                padding: 0 0.6rem;
                place-items: center;
                border-radius: 999px;
                background: rgba(0, 174, 239, 0.1);
                color: #008fc8;
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 0.76rem;
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

            .fieldops-luminaire-frame-spatial__mode-switcher {
                width: fit-content;
                margin-top: 1rem;
                padding: 0.25rem;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 0.9rem;
                background: rgba(148, 163, 184, 0.08);
            }

            .fieldops-luminaire-frame-spatial__mode-button {
                min-height: 2.15rem;
                padding: 0.4rem 0.8rem;
                border-radius: 0.7rem;
                color: #64748b;
                font-size: 0.84rem;
                font-weight: 800;
                transition: color 150ms ease, background-color 150ms ease, box-shadow 150ms ease;
            }

            .fieldops-luminaire-frame-spatial__mode-button--active {
                background: #ffffff;
                color: #075985;
                box-shadow: 0 5px 14px rgba(15, 23, 42, 0.09);
            }

            .dark .fieldops-luminaire-frame-spatial__mode-switcher {
                border-color: rgba(255, 255, 255, 0.08);
                background: rgba(255, 255, 255, 0.035);
            }

            .dark .fieldops-luminaire-frame-spatial__mode-button {
                color: #94a3b8;
            }

            .dark .fieldops-luminaire-frame-spatial__mode-button--active {
                background: rgba(56, 189, 248, 0.13);
                color: #7dd3fc;
                box-shadow: inset 0 0 0 1px rgba(56, 189, 248, 0.16);
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

            .fieldops-luminaire-frame-spatial__button:disabled {
                cursor: not-allowed;
                opacity: 0.5;
                transform: none;
            }

            .fieldops-luminaire-frame-spatial__modal {
                position: fixed;
                z-index: 100;
                inset: 0;
                display: grid;
                padding: 1rem;
                place-items: center;
            }

            .fieldops-luminaire-frame-spatial__modal-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(2, 6, 23, 0.72);
                backdrop-filter: blur(4px);
            }

            .fieldops-luminaire-frame-spatial__modal-panel {
                position: relative;
                width: min(32rem, 100%);
                overflow: hidden;
                border: 1px solid rgba(148, 163, 184, 0.24);
                border-radius: 1.2rem;
                background: #ffffff;
                box-shadow: 0 28px 70px rgba(15, 23, 42, 0.28);
            }

            .dark .fieldops-luminaire-frame-spatial__modal-panel {
                border-color: rgba(255, 255, 255, 0.1);
                background: #171725;
                box-shadow: 0 32px 80px rgba(0, 0, 0, 0.62);
            }

            .fieldops-luminaire-frame-spatial__modal-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
                padding: 1.1rem 1.15rem;
                border-bottom: 1px solid rgba(148, 163, 184, 0.16);
            }

            .dark .fieldops-luminaire-frame-spatial__modal-header {
                border-bottom-color: rgba(255, 255, 255, 0.08);
            }

            .fieldops-luminaire-frame-spatial__modal-title {
                color: #0f172a;
                font-size: 1.05rem;
                font-weight: 900;
            }

            .dark .fieldops-luminaire-frame-spatial__modal-title {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__modal-copy {
                margin-top: 0.2rem;
                color: #64748b;
                font-size: 0.82rem;
                line-height: 1.45;
            }

            .dark .fieldops-luminaire-frame-spatial__modal-copy {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-spatial__modal-close {
                display: inline-grid;
                width: 2rem;
                height: 2rem;
                flex: 0 0 auto;
                place-items: center;
                border-radius: 999px;
                color: #64748b;
                font-size: 1.15rem;
            }

            .fieldops-luminaire-frame-spatial__modal-close:hover {
                background: rgba(148, 163, 184, 0.12);
                color: #0f172a;
            }

            .dark .fieldops-luminaire-frame-spatial__modal-close:hover {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__modal-body {
                display: grid;
                gap: 1rem;
                padding: 1.15rem;
            }

            .fieldops-luminaire-frame-spatial__type-preview {
                display: grid;
                grid-template-columns: 5.5rem minmax(0, 1fr);
                align-items: center;
                gap: 0.9rem;
                padding: 0.75rem;
                border: 1px solid rgba(14, 165, 233, 0.2);
                border-radius: 0.9rem;
                background: rgba(14, 165, 233, 0.06);
            }

            .dark .fieldops-luminaire-frame-spatial__type-preview {
                border-color: rgba(56, 189, 248, 0.2);
                background: rgba(14, 165, 233, 0.08);
            }

            .fieldops-luminaire-frame-spatial__type-preview-media {
                display: grid;
                width: 5.5rem;
                aspect-ratio: 1;
                overflow: hidden;
                place-items: center;
                border-radius: 0.75rem;
                background: #ffffff;
            }

            .dark .fieldops-luminaire-frame-spatial__type-preview-media {
                background: rgba(255, 255, 255, 0.06);
            }

            .fieldops-luminaire-frame-spatial__type-preview-image {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .fieldops-luminaire-frame-spatial__type-preview-name {
                color: #0f172a;
                font-size: 0.94rem;
                font-weight: 900;
                line-height: 1.25;
            }

            .dark .fieldops-luminaire-frame-spatial__type-preview-name {
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__type-preview-meta {
                margin-top: 0.25rem;
                color: #64748b;
                font-size: 0.76rem;
                line-height: 1.35;
            }

            .dark .fieldops-luminaire-frame-spatial__type-preview-meta {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-spatial__field {
                display: grid;
                gap: 0.4rem;
            }

            .fieldops-luminaire-frame-spatial__field-label {
                color: #334155;
                font-size: 0.78rem;
                font-weight: 800;
            }

            .dark .fieldops-luminaire-frame-spatial__field-label {
                color: #dbeafe;
            }

            .fieldops-luminaire-frame-spatial__field-control {
                width: 100%;
                min-height: 2.65rem;
                padding: 0.58rem 0.72rem;
                border: 1px solid rgba(148, 163, 184, 0.32);
                border-radius: 0.8rem;
                background: #ffffff;
                color: #0f172a;
                font-size: 0.88rem;
                outline: none;
            }

            .fieldops-luminaire-frame-spatial__field-control:focus {
                border-color: rgba(14, 165, 233, 0.72);
                box-shadow: 0 0 0 0.2rem rgba(14, 165, 233, 0.12);
            }

            .dark .fieldops-luminaire-frame-spatial__field-control {
                border-color: rgba(255, 255, 255, 0.12);
                background: rgba(255, 255, 255, 0.045);
                color: #f8fafc;
            }

            .fieldops-luminaire-frame-spatial__field-hint,
            .fieldops-luminaire-frame-spatial__modal-error {
                font-size: 0.76rem;
                line-height: 1.45;
            }

            .fieldops-luminaire-frame-spatial__field-hint {
                color: #64748b;
            }

            .dark .fieldops-luminaire-frame-spatial__field-hint {
                color: #94a3b8;
            }

            .fieldops-luminaire-frame-spatial__modal-error {
                padding: 0.7rem 0.8rem;
                border: 1px solid rgba(239, 68, 68, 0.22);
                border-radius: 0.75rem;
                background: rgba(239, 68, 68, 0.08);
                color: #b91c1c;
                font-weight: 700;
            }

            .dark .fieldops-luminaire-frame-spatial__modal-error {
                color: #fca5a5;
            }

            .fieldops-luminaire-frame-spatial__modal-actions {
                display: flex;
                justify-content: flex-end;
                gap: 0.55rem;
                padding: 0 1.15rem 1.15rem;
            }

            .fieldops-luminaire-frame-spatial__body {
                display: grid;
                grid-template-columns: minmax(0, 1.75fr) minmax(18rem, 0.82fr);
                gap: 1rem;
                padding: 1rem 1.2rem 1.2rem;
            }

            .fieldops-luminaire-frame-spatial__body--details-hidden {
                grid-template-columns: minmax(0, 1fr);
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

            .fieldops-luminaire-frame-spatial__board-actions {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 0.55rem;
            }

            .fieldops-luminaire-frame-spatial__board-actions .fieldops-luminaire-frame-spatial__toolbar {
                justify-content: flex-end;
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

            .fieldops-luminaire-frame-spatial__marker--readonly {
                cursor: pointer;
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
                pointer-events: none;
                user-select: none;
                -webkit-user-drag: none;
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
                right: -0.5rem;
                top: -0.5rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 1.1rem;
                height: 1.1rem;
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

            .fieldops-luminaire-frame-spatial__status-banner {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                margin-top: 0.9rem;
                padding: 0.75rem 0.85rem;
                border: 1px solid rgba(34, 197, 94, 0.2);
                border-radius: 0.9rem;
                background: rgba(34, 197, 94, 0.08);
                color: #166534;
                font-size: 0.85rem;
                font-weight: 800;
            }

            .fieldops-luminaire-frame-spatial__status-banner--warning {
                border-color: rgba(245, 158, 11, 0.25);
                background: rgba(245, 158, 11, 0.1);
                color: #92400e;
            }

            .dark .fieldops-luminaire-frame-spatial__status-banner {
                border-color: rgba(74, 222, 128, 0.18);
                background: rgba(34, 197, 94, 0.09);
                color: #86efac;
            }

            .dark .fieldops-luminaire-frame-spatial__status-banner--warning {
                border-color: rgba(251, 191, 36, 0.2);
                background: rgba(245, 158, 11, 0.1);
                color: #fcd34d;
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

            .fieldops-luminaire-frame-spatial__scale-tools {
                position: absolute;
                z-index: 12;
                left: 50%;
                top: calc(100% + 0.45rem);
                transform: translateX(-50%);
            }

            .fieldops-luminaire-frame-spatial__scale-tools--above {
                top: auto;
                bottom: calc(100% + 0.45rem);
            }

            .fieldops-luminaire-frame-spatial__scale-trigger {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                padding: 0;
                border: 1px solid rgba(56, 189, 248, 0.42);
                border-radius: 999px;
                background: rgba(8, 47, 73, 0.94);
                color: #bae6fd;
                box-shadow: 0 8px 20px rgba(2, 6, 23, 0.3);
                backdrop-filter: blur(10px);
            }

            .fieldops-luminaire-frame-spatial__scale-trigger:hover,
            .fieldops-luminaire-frame-spatial__scale-trigger--active {
                border-color: rgba(56, 189, 248, 0.88);
                background: rgba(3, 105, 161, 0.96);
                color: #ffffff;
            }

            .fieldops-luminaire-frame-spatial__scale-trigger svg {
                width: 1rem;
                height: 1rem;
            }

            .fieldops-luminaire-frame-spatial__scale-popover {
                position: absolute;
                top: calc(100% + 0.45rem);
                left: 50%;
                width: 16rem;
                max-width: calc(100vw - 2rem);
                padding: 0.9rem;
                border: 1px solid rgba(148, 163, 184, 0.16);
                border-radius: 0.95rem;
                background: rgba(248, 250, 252, 0.98);
                box-shadow: 0 18px 40px rgba(2, 6, 23, 0.28);
                transform: translateX(-50%);
                backdrop-filter: blur(16px);
            }

            .dark .fieldops-luminaire-frame-spatial__scale-popover {
                border-color: rgba(255, 255, 255, 0.12);
                background: rgba(15, 23, 42, 0.98);
            }

            .fieldops-luminaire-frame-spatial__scale-tools--above .fieldops-luminaire-frame-spatial__scale-popover {
                top: auto;
                bottom: calc(100% + 0.45rem);
            }

            .fieldops-luminaire-frame-spatial__scale-tools--align-left .fieldops-luminaire-frame-spatial__scale-popover {
                left: -0.25rem;
                transform: none;
            }

            .fieldops-luminaire-frame-spatial__scale-tools--align-right .fieldops-luminaire-frame-spatial__scale-popover {
                right: -0.25rem;
                left: auto;
                transform: none;
            }

            .fieldops-luminaire-frame-spatial__scale-header,
            .fieldops-luminaire-frame-spatial__scale-presets {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.65rem;
                flex-wrap: wrap;
            }

            .fieldops-luminaire-frame-spatial__scale-value {
                color: #0284c7;
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 0.84rem;
                font-weight: 900;
            }

            .dark .fieldops-luminaire-frame-spatial__scale-value {
                color: #7dd3fc;
            }

            .fieldops-luminaire-frame-spatial__scale-slider {
                width: 100%;
                margin: 0.85rem 0 0.75rem;
                accent-color: #0ea5e9;
                cursor: pointer;
            }

            .fieldops-luminaire-frame-spatial__scale-slider:disabled {
                cursor: wait;
                opacity: 0.58;
            }

            .fieldops-luminaire-frame-spatial__scale-preset {
                min-height: 2rem;
                padding: 0.35rem 0.6rem;
                border: 1px solid rgba(148, 163, 184, 0.2);
                border-radius: 0.7rem;
                background: rgba(255, 255, 255, 0.58);
                color: #334155;
                font-size: 0.75rem;
                font-weight: 800;
            }

            .dark .fieldops-luminaire-frame-spatial__scale-preset {
                border-color: rgba(255, 255, 255, 0.1);
                background: rgba(255, 255, 255, 0.05);
                color: #e2e8f0;
            }

            .fieldops-luminaire-frame-spatial__scale-preset:hover,
            .fieldops-luminaire-frame-spatial__scale-preset--active {
                border-color: rgba(14, 165, 233, 0.5);
                background: rgba(14, 165, 233, 0.12);
                color: #0369a1;
            }

            .dark .fieldops-luminaire-frame-spatial__scale-preset:hover,
            .dark .fieldops-luminaire-frame-spatial__scale-preset--active {
                color: #7dd3fc;
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

            .fieldops-luminaire-frame-spatial:fullscreen {
                width: 100vw;
                height: 100vh;
                border: 0;
                border-radius: 0;
            }

            .fieldops-luminaire-frame-spatial:fullscreen > .fieldops-luminaire-frame-spatial__header {
                display: none;
            }

            .fieldops-luminaire-frame-spatial:fullscreen > .fieldops-luminaire-frame-spatial__body {
                height: 100vh;
                padding: 0.75rem;
            }

            .fieldops-luminaire-frame-spatial:fullscreen .fieldops-luminaire-frame-spatial__main,
            .fieldops-luminaire-frame-spatial:fullscreen .fieldops-luminaire-frame-spatial__board-shell {
                min-height: 0;
                height: 100%;
            }

            .fieldops-luminaire-frame-spatial:fullscreen .fieldops-luminaire-frame-spatial__board-shell {
                display: flex;
                flex-direction: column;
            }

            .fieldops-luminaire-frame-spatial:fullscreen .fieldops-luminaire-frame-spatial__viewport {
                flex: 1;
                min-height: 0;
            }

            .fieldops-luminaire-frame-spatial:fullscreen .fieldops-luminaire-frame-spatial__rail {
                max-height: calc(100vh - 1.5rem);
                overflow-y: auto;
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

                .fieldops-luminaire-frame-spatial__board-header {
                    flex-direction: column;
                }

                .fieldops-luminaire-frame-spatial__board-actions {
                    width: 100%;
                    align-items: flex-start;
                }

                .fieldops-luminaire-frame-spatial__board-meta {
                    justify-content: flex-start;
                }

                .fieldops-luminaire-frame-spatial__selected-row {
                    grid-template-columns: minmax(0, 1fr);
                }
            }
        </style>
    @endassets

    @script
        <script>
            Alpine.data('fieldopsLuminaireFrameLayout', function (payload) {
                return {
                    viewMode: 'overview',
                    overviewDetailsVisible: true,
                    technicalDetailsVisible: true,
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
                        scaleX: Number(item.scaleX ?? 1),
                        scaleY: Number(item.scaleY ?? 1),
                        persistedScaleX: Number(item.scaleX ?? 1),
                        persistedScaleY: Number(item.scaleY ?? 1),
                    })),
                    unpositioned: payload.unpositioned ?? [],
                    summary: payload.summary ?? [],
                    bounds: payload.bounds ?? null,
                    frameType: payload.frameType ?? null,
                    frameImage: payload.frameImage ?? null,
                    frameId: Number(payload.frameId),
                    createUrl: payload.createUrl ?? '',
                    luminaireTypes: payload.luminaireTypes ?? [],
                    createModalOpen: false,
                    createPending: false,
                    createError: null,
                    newLuminaireTypeId: '',
                    newLuminaireSerial: '',
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
                    saveStatusTimer: null,
                    scaleSaveTimers: {},
                    scaleSavePending: false,
                    scaleEditorId: null,
                    dragShellElement: null,
                    resizeObserver: null,
                    init() {
                        const requestedLayout = new URL(window.location.href).searchParams.get('layout');
                        this.viewMode = requestedLayout === 'technical' ? 'technical' : 'overview';

                        if (this.selectedId === null) {
                            const first = this.markers[0] ?? this.unpositioned[0] ?? null;
                            this.selectedId = first ? Number(first.id) : null;
                        }

                        if (this.frameImage) {
                            this.$nextTick(() => this.measureFrameRatio());
                        }

                        this.$nextTick(() => {
                            if (this.$refs.stage && window.ResizeObserver) {
                                this.resizeObserver = new ResizeObserver(() => this.refreshMarkerSizes());
                                this.resizeObserver.observe(this.$refs.stage);
                            }

                            this.refreshMarkerSizes();
                        });

                        document.addEventListener('fullscreenchange', () => {
                            this.isFullscreen = Boolean(document.fullscreenElement);
                        });
                    },
                    destroy() {
                        this.resizeObserver?.disconnect();
                        Object.values(this.scaleSaveTimers).forEach((timer) => window.clearTimeout(timer));
                    },
                    isFullscreen: false,
                    clamp(value, min, max) {
                        return Math.min(max, Math.max(min, value));
                    },
                    requestHeaders() {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                        return {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-FieldOps-Editor': 'backoffice',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        };
                    },
                    setViewMode(mode) {
                        this.viewMode = mode === 'technical' ? 'technical' : 'overview';
                        if (this.viewMode !== 'technical') {
                            this.closeScaleEditor();
                        }

                        const destination = new URL(window.location.href);
                        if (this.viewMode === 'technical') {
                            destination.searchParams.set('layout', 'technical');
                        } else {
                            destination.searchParams.delete('layout');
                        }

                        window.history.replaceState({}, '', destination.toString());
                        this.$nextTick(() => this.refreshMarkerSizes());
                    },
                    openCreateModal() {
                        if (this.luminaireTypes.length === 0) {
                            this.statusMessage = @js(__('fieldops::resource.luminaire_frames.view.no_luminaire_types'));
                            return;
                        }

                        this.createError = null;
                        this.newLuminaireSerial = '';
                        this.newLuminaireTypeId = String(this.luminaireTypes[0].id);
                        this.createModalOpen = true;
                        this.$nextTick(() => document.getElementById('fieldops-new-luminaire-type')?.focus());
                    },
                    closeCreateModal() {
                        if (this.createPending) {
                            return;
                        }

                        this.createModalOpen = false;
                        this.createError = null;
                    },
                    selectedCreateType() {
                        return this.luminaireTypes.find((type) => Number(type.id) === Number(this.newLuminaireTypeId)) ?? null;
                    },
                    async createLuminaire() {
                        const type = this.selectedCreateType();

                        if (!type) {
                            this.createError = @js(__('fieldops::resource.luminaire_frames.view.create_type_required'));
                            return;
                        }

                        this.createPending = true;
                        this.createError = null;

                        const requestBody = {
                            luminaire_frame_id: this.frameId,
                            luminaire_type_id: Number(type.id),
                            luminaire_subgroup_id: Number(type.subgroupId),
                            frame_x: 0.5,
                            frame_y: 0.5,
                            scale_x: 1,
                            scale_y: 1,
                        };
                        const serial = this.newLuminaireSerial.trim();
                        if (serial !== '') {
                            requestBody.serial_number = serial;
                        }

                        try {
                            const response = await fetch(this.createUrl, {
                                method: 'POST',
                                headers: this.requestHeaders(),
                                credentials: 'same-origin',
                                body: JSON.stringify(requestBody),
                            });
                            const responsePayload = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                const validationMessage = Object.values(responsePayload.errors ?? {}).flat()[0];
                                throw new Error(validationMessage ?? responsePayload.message ?? @js(__('fieldops::resource.luminaire_frames.view.create_failed')));
                            }

                            const createdId = Number(responsePayload?.data?.id);
                            if (!Number.isFinite(createdId) || createdId <= 0) {
                                throw new Error(@js(__('fieldops::resource.luminaire_frames.view.create_failed')));
                            }

                            const destination = new URL(window.location.href);
                            destination.searchParams.delete('selected');
                            destination.searchParams.set('luminaire', String(createdId));
                            destination.searchParams.set('layout', 'technical');
                            window.location.assign(destination.toString());
                        } catch (error) {
                            console.error(error);
                            this.createError = error?.message ?? @js(__('fieldops::resource.luminaire_frames.view.create_failed'));
                            this.createPending = false;
                        }
                    },
                    normalizeCoordinate(value) {
                        const numeric = Number(value);
                        if (!Number.isFinite(numeric)) {
                            return null;
                        }

                        return numeric > 1 ? numeric / 100 : numeric;
                    },
                    toCanvasPercentage(value, fallback = 0) {
                        const normalized = this.normalizeCoordinate(value);

                        return normalized === null
                            ? this.clamp(Number(fallback), 0, 100)
                            : this.clamp(normalized * 100, 0, 100);
                    },
                    coordinateLabel(value) {
                        const normalized = this.normalizeCoordinate(value);
                        if (normalized === null) {
                            return '—';
                        }

                        return new Intl.NumberFormat(undefined, { maximumFractionDigits: 4 }).format(normalized);
                    },
                    syncMarkerElement(marker) {
                        const shell = this.$refs.stage?.querySelector(`[data-marker-id="${marker.id}"]`);
                        if (!shell) {
                            return;
                        }

                        const dimensions = this.markerDimensions(marker);
                        shell.style.left = `${marker.left}%`;
                        shell.style.top = `${marker.top}%`;
                        shell.style.width = `${dimensions.width}px`;
                        shell.style.height = `${dimensions.height}px`;
                    },
                    applyPersistedPosition(marker, updated) {
                        marker.frameX = updated.frame_x ?? marker.frameX;
                        marker.frameY = updated.frame_y ?? marker.frameY;
                        marker.left = this.toCanvasPercentage(marker.frameX, marker.left);
                        marker.top = this.toCanvasPercentage(marker.frameY, marker.top);
                        marker.positionVersion = Number(updated.position_version ?? marker.positionVersion ?? 1);
                        marker.positionSource = updated.position_source ?? marker.positionSource;

                        if (Object.prototype.hasOwnProperty.call(updated, 'position_verified_at')) {
                            marker.positionVerifiedAt = updated.position_verified_at;
                        }

                        marker.positionLabel = `X ${this.coordinateLabel(marker.frameX)} · Y ${this.coordinateLabel(marker.frameY)}`;
                        this.$nextTick(() => this.syncMarkerElement(marker));
                    },
                    applyPersistedScale(marker, updated) {
                        marker.scaleX = Number(updated.scale_x ?? marker.scaleX ?? 1);
                        marker.scaleY = Number(updated.scale_y ?? marker.scaleY ?? 1);
                        marker.persistedScaleX = marker.scaleX;
                        marker.persistedScaleY = marker.scaleY;
                        this.$nextTick(() => this.syncMarkerElement(marker));
                    },
                    async refreshMarkerFromServer(marker) {
                        const response = await fetch(marker.updateUrl, {
                            method: 'GET',
                            headers: this.requestHeaders(),
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            return;
                        }

                        const responsePayload = await response.json().catch(() => ({}));
                        const current = responsePayload?.data ?? null;
                        if (current) {
                            this.applyPersistedPosition(marker, current);
                            this.applyPersistedScale(marker, current);
                        }
                    },
                    flashStatus(message) {
                        window.clearTimeout(this.saveStatusTimer);
                        this.statusMessage = message;
                        this.saveStatusTimer = window.setTimeout(() => {
                            this.statusMessage = null;
                        }, 2400);
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
                    markerBaseSize() {
                        const stageWidth = Number(this.$refs.stage?.getBoundingClientRect().width ?? 0);
                        const logicalWidth = stageWidth > 0 ? stageWidth / Math.max(this.zoom, 0.01) : 1000;

                        return this.clamp(logicalWidth * 0.045, 40, 64);
                    },
                    markerDimensions(marker) {
                        const baseSize = this.markerBaseSize();
                        const scaleX = this.clamp(Number(marker.scaleX ?? 1), 0.01, 10);
                        const scaleY = this.clamp(Number(marker.scaleY ?? 1), 0.01, 10);

                        return {
                            width: Math.round(this.clamp(baseSize * scaleX, 24, 160) * this.zoom),
                            height: Math.round(this.clamp(baseSize * scaleY, 24, 160) * this.zoom),
                        };
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

                            const dimensions = this.markerDimensions(marker);
                            shell.style.width = `${dimensions.width}px`;
                            shell.style.height = `${dimensions.height}px`;
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

                        const scaleX = Number(marker.scaleX ?? 1);
                        const scaleY = Number(marker.scaleY ?? 1);

                        return `X ${scaleX.toFixed(2)} / Y ${scaleY.toFixed(2)}`;
                    },
                    selectedMarkerUniformScale() {
                        const marker = this.selectedMarker();

                        return marker ? Number(marker.scaleX ?? 1) : 1;
                    },
                    isScaleEditorOpen(id) {
                        return Number(this.scaleEditorId) === Number(id);
                    },
                    toggleScaleEditor(id) {
                        const markerId = Number(id);
                        this.selectedId = markerId;
                        this.scaleEditorId = this.isScaleEditorOpen(markerId) ? null : markerId;
                    },
                    closeScaleEditor() {
                        this.scaleEditorId = null;
                    },
                    scaleEditorClasses(id) {
                        const marker = this.getMarker(id);
                        if (!marker) {
                            return '';
                        }

                        const stageRect = this.$refs.stage?.getBoundingClientRect();
                        const stageWidth = Number(stageRect?.width ?? 0);
                        const stageHeight = Number(stageRect?.height ?? 0);
                        const anchorX = stageWidth * (Number(marker.left) / 100);
                        const remainingBelow = stageHeight * (1 - (Number(marker.top) / 100));

                        return {
                            'fieldops-luminaire-frame-spatial__scale-tools--above': stageHeight > 0
                                ? remainingBelow < 230 && Number(marker.top) > 42
                                : Number(marker.top) > 55,
                            'fieldops-luminaire-frame-spatial__scale-tools--align-left': stageWidth > 0
                                ? anchorX < 136
                                : Number(marker.left) < 22,
                            'fieldops-luminaire-frame-spatial__scale-tools--align-right': stageWidth > 0
                                ? (stageWidth - anchorX) < 136
                                : Number(marker.left) > 78,
                        };
                    },
                    isSelectedMarkerScale(scale) {
                        return Math.abs(this.selectedMarkerUniformScale() - Number(scale)) < 0.001;
                    },
                    previewSelectedMarkerScale(value) {
                        const marker = this.selectedMarker();
                        const scale = this.clamp(Number(value), 0.25, 3);

                        if (!marker || !Number.isFinite(scale)) {
                            return;
                        }

                        marker.scaleX = Math.round(scale * 100) / 100;
                        marker.scaleY = marker.scaleX;
                        this.syncMarkerElement(marker);

                        window.clearTimeout(this.scaleSaveTimers[marker.id]);
                        this.scaleSaveTimers[marker.id] = window.setTimeout(() => this.persistMarkerScale(marker), 600);
                    },
                    async persistMarkerScale(marker) {
                        if (this.scaleSavePending) {
                            this.scaleSaveTimers[marker.id] = window.setTimeout(() => this.persistMarkerScale(marker), 200);
                            return;
                        }

                        delete this.scaleSaveTimers[marker.id];
                        this.scaleSavePending = true;
                        this.statusMessage = null;

                        try {
                            const response = await fetch(marker.updateUrl, {
                                method: 'PATCH',
                                headers: this.requestHeaders(),
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    scale_x: Number(marker.scaleX),
                                    scale_y: Number(marker.scaleY),
                                }),
                            });

                            if (!response.ok) {
                                throw new Error(`Unable to save marker scale (${response.status})`);
                            }

                            const responsePayload = await response.json().catch(() => ({}));
                            const updated = responsePayload?.data ?? responsePayload ?? null;

                            if (updated) {
                                this.applyPersistedScale(marker, updated);
                            }

                            this.flashStatus(@js(__('fieldops::resource.luminaire_frames.view.scale_saved')));
                        } catch (error) {
                            console.error(error);
                            marker.scaleX = Number(marker.persistedScaleX ?? 1);
                            marker.scaleY = Number(marker.persistedScaleY ?? 1);
                            this.syncMarkerElement(marker);
                            this.statusMessage = @js(__('fieldops::resource.luminaire_frames.view.scale_save_failed'));
                        } finally {
                            this.scaleSavePending = false;
                        }
                    },
                    selectedMarkerStateLabel() {
                        const marker = this.selectedMarker();

                        return marker
                            ? (marker.flagged
                                ? @js(__('fieldops::resource.luminaire_frames.view.open'))
                                : @js(__('fieldops::resource.luminaire_frames.view.no_open_issues')))
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

                        if (Number(this.selectedId) !== Number(id)) {
                            this.closeScaleEditor();
                        }
                        this.selectedId = Number(id);
                    },
                    handleMarkerClick(id) {
                        if (this.viewMode === 'overview') {
                            this.openMarker(id);

                            return;
                        }

                        this.selectMarker(id);
                    },
                    openMarker(id) {
                        const marker = this.markers.find((item) => Number(item.id) === Number(id));
                        if (!marker?.url) {
                            return;
                        }

                        if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                            window.Livewire.navigate(marker.url);

                            return;
                        }

                        window.location.assign(marker.url);
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
                            const dimensions = this.markerDimensions(marker);
                            this.dragShellElement.style.left = `${marker.left}%`;
                            this.dragShellElement.style.top = `${marker.top}%`;
                            this.dragShellElement.style.width = `${dimensions.width}px`;
                            this.dragShellElement.style.height = `${dimensions.height}px`;
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
                    async persistMarkerPosition(marker) {
                        if (this.savePending) {
                            return;
                        }

                        this.savePending = true;
                        this.statusMessage = null;

                        try {
                            const response = await fetch(marker.updateUrl, {
                                method: 'PATCH',
                                headers: this.requestHeaders(),
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
                                    await this.refreshMarkerFromServer(marker);
                                    this.statusMessage = @js(__('fieldops::resource.luminaire_frames.view.position_conflict'));
                                    return;
                                }

                                throw new Error(`Unable to save marker position (${response.status})`);
                            }

                            const payload = await response.json().catch(() => ({}));
                            const updated = payload?.data ?? payload ?? null;

                            if (updated) {
                                this.applyPersistedPosition(marker, updated);

                                if (this.selectedId === Number(marker.id)) {
                                    this.selectedId = Number(marker.id);
                                }
                            }

                            this.flashStatus(@js(__('fieldops::resource.luminaire_frames.view.position_saved')));
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

                        if (this.viewMode !== 'technical' && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
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
                                const dimensions = this.markerDimensions(marker);
                                shell.style.left = `${marker.left}%`;
                                shell.style.top = `${marker.top}%`;
                                shell.style.width = `${dimensions.width}px`;
                                shell.style.height = `${dimensions.height}px`;
                            }
                            this.persistMarkerPosition(marker);
                        }
                    },
                    zoomIn() {
                        this.zoom = Math.min(this.maxZoom, Math.round((this.zoom + 0.1) * 10) / 10);
                        this.$nextTick(() => this.refreshMarkerSizes());
                    },
                    zoomOut() {
                        this.zoom = Math.max(this.minZoom, Math.round((this.zoom - 0.1) * 10) / 10);
                        this.$nextTick(() => this.refreshMarkerSizes());
                    },
                    resetZoom() {
                        this.zoom = 1;
                        this.$nextTick(() => this.refreshMarkerSizes());
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
                        const dimensions = this.markerDimensions(marker);

                        return `left: ${marker.left}%; top: ${marker.top}%; width: ${dimensions.width}px; height: ${dimensions.height}px;`;
                    },
                };
            });
        </script>
    @endscript

<div
    class="fieldops-luminaire-frame-spatial"
    x-data="fieldopsLuminaireFrameLayout(@js($payload))"
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

            <div class="fieldops-luminaire-frame-spatial__mode-switcher" role="tablist" aria-label="{{ __('fieldops::resource.luminaire_frames.view.eyebrow') }}">
                <button
                    type="button"
                    role="tab"
                    class="fieldops-luminaire-frame-spatial__mode-button"
                    :class="viewMode === 'overview' ? 'fieldops-luminaire-frame-spatial__mode-button--active' : ''"
                    :aria-selected="viewMode === 'overview'"
                    @click="setViewMode('overview')"
                >
                    {{ __('fieldops::resource.luminaire_frames.view.overview_tab') }}
                </button>
                <button
                    type="button"
                    role="tab"
                    class="fieldops-luminaire-frame-spatial__mode-button"
                    :class="viewMode === 'technical' ? 'fieldops-luminaire-frame-spatial__mode-button--active' : ''"
                    :aria-selected="viewMode === 'technical'"
                    @click="setViewMode('technical')"
                >
                    {{ __('fieldops::resource.luminaire_frames.view.technical_tab') }}
                </button>
            </div>

        </div>

        <div class="fieldops-luminaire-frame-spatial__chips">
            @foreach ($payload['summary'] as $item)
                <span class="fieldops-luminaire-frame-spatial__summary-pill">
                    <span>{{ $item['label'] }}</span>
                    <span class="fieldops-luminaire-frame-spatial__summary-value">{{ $item['value'] }}</span>
                </span>
            @endforeach
        </div>
    </div>

    <div
        class="fieldops-luminaire-frame-spatial__body"
        :class="(
            (viewMode === 'overview' && !overviewDetailsVisible)
            || (viewMode === 'technical' && !technicalDetailsVisible)
        ) ? 'fieldops-luminaire-frame-spatial__body--details-hidden' : ''"
    >
        <div class="fieldops-luminaire-frame-spatial__main">
            <div class="fieldops-luminaire-frame-spatial__board-shell">
                <div class="fieldops-luminaire-frame-spatial__board-header">
                    <div>
                        <div class="fieldops-luminaire-frame-spatial__board-title">
                            <span x-show="viewMode === 'overview'">{{ __('fieldops::resource.luminaire_frames.view.canvas_label') }}</span>
                            <span x-show="viewMode === 'technical'" x-cloak>{{ __('fieldops::resource.luminaire_frames.view.technical_canvas_label') }}</span>
                        </div>
                        <div class="fieldops-luminaire-frame-spatial__board-subtitle">
                            <span x-show="viewMode === 'overview'">{{ __('fieldops::resource.luminaire_frames.view.overview_hint') }}</span>
                            <span x-show="viewMode === 'technical'" x-cloak>{{ __('fieldops::resource.luminaire_frames.view.layout_hint') }}</span>
                        </div>
                    </div>

                    <div class="fieldops-luminaire-frame-spatial__board-actions">
                        <div x-show="viewMode === 'technical'" x-cloak class="fieldops-luminaire-frame-spatial__board-meta">
                            @if ($bounds)
                                <span class="fieldops-luminaire-frame-spatial__board-pill">X {{ $bounds['minX'] }} - {{ $bounds['maxX'] }}</span>
                                <span class="fieldops-luminaire-frame-spatial__board-pill">Y {{ $bounds['minY'] }} - {{ $bounds['maxY'] }}</span>
                            @endif
                            <span class="fieldops-luminaire-frame-spatial__board-pill">
                                {{ count($markers) }} {{ __('fieldops::resource.luminaire_frames.view.summary_positioned') }}
                            </span>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__toolbar">
                            <button
                                x-show="viewMode === 'technical'"
                                x-cloak
                                type="button"
                                class="fieldops-luminaire-frame-spatial__button fieldops-luminaire-frame-spatial__button--primary"
                                :disabled="luminaireTypes.length === 0"
                                @click="openCreateModal()"
                            >
                                <span aria-hidden="true">+</span>
                                <span>{{ __('fieldops::resource.luminaire_frames.view.add_luminaire') }}</span>
                            </button>
                            <button
                                x-show="viewMode === 'overview'"
                                type="button"
                                class="fieldops-luminaire-frame-spatial__button"
                                aria-controls="fieldops-luminaire-frame-overview-details"
                                :aria-expanded="overviewDetailsVisible"
                                @click="overviewDetailsVisible = !overviewDetailsVisible"
                            >
                                <span x-show="overviewDetailsVisible">{{ __('fieldops::resource.luminaire_frames.view.hide_details') }}</span>
                                <span x-show="!overviewDetailsVisible" x-cloak>{{ __('fieldops::resource.luminaire_frames.view.show_details') }}</span>
                            </button>
                            <button
                                x-show="viewMode === 'technical'"
                                x-cloak
                                type="button"
                                class="fieldops-luminaire-frame-spatial__button"
                                data-fieldops-technical-details-toggle
                                aria-controls="fieldops-luminaire-frame-overview-details"
                                :aria-expanded="technicalDetailsVisible"
                                @click="technicalDetailsVisible = !technicalDetailsVisible"
                            >
                                <span x-show="technicalDetailsVisible">{{ __('fieldops::resource.luminaire_frames.view.hide_details') }}</span>
                                <span x-show="!technicalDetailsVisible" x-cloak>{{ __('fieldops::resource.luminaire_frames.view.show_details') }}</span>
                            </button>
                            <button x-show="viewMode === 'technical'" x-cloak type="button" class="fieldops-luminaire-frame-spatial__button" @click="zoomOut()">
                                {{ __('fieldops::resource.luminaire_frames.view.zoom_out') }}
                            </button>
                            <button x-show="viewMode === 'technical'" x-cloak type="button" class="fieldops-luminaire-frame-spatial__button" @click="zoomIn()">
                                {{ __('fieldops::resource.luminaire_frames.view.zoom_in') }}
                            </button>
                            <button x-show="viewMode === 'technical'" x-cloak type="button" class="fieldops-luminaire-frame-spatial__button" @click="resetZoom()">
                                {{ __('fieldops::resource.luminaire_frames.view.reset_zoom') }}
                            </button>
                            <button
                                type="button"
                                class="fieldops-luminaire-frame-spatial__button"
                                :class="viewMode === 'overview' ? 'fieldops-luminaire-frame-spatial__button--primary' : ''"
                                @click="toggleFullscreen()"
                            >
                                <span x-show="!isFullscreen">{{ __('fieldops::resource.luminaire_frames.view.fullscreen') }}</span>
                                <span x-show="isFullscreen" x-cloak>{{ __('fieldops::resource.luminaire_frames.view.exit_fullscreen') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="fieldops-luminaire-frame-spatial__viewport">
                    {{-- Show the canvas (frame background image + grid) whenever the frame type
                         has a reference image, even with zero luminaires placed yet — otherwise
                         a brand-new frame with no luminaires ever shows the generic text-only
                         empty state below, with no way to see the frame you're about to place
                         luminaires on. The @foreach below is simply empty when $markers is []. --}}
                    @if ($payload['frameImage'] || count($markers) > 0)
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
                                        alt="{{ __('fieldops::resource.luminaire_frames.view.frame_image_alt', ['frame' => $payload['frameType'] ?? __('fieldops::resource.luminaire_frames.model_label')]) }}"
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

                                <div x-show="viewMode === 'technical'" x-cloak class="fieldops-luminaire-frame-spatial__surface-grid" aria-hidden="true"></div>

                                @foreach ($markers as $marker)
                                    <div
                                        class="fieldops-luminaire-frame-spatial__marker-shell"
                                        data-marker-id="{{ $marker['id'] }}"
                                        :style="markerStyle(@js($marker))"
                                    >
                                        <button
                                            type="button"
                                            class="fieldops-luminaire-frame-spatial__marker {{ $marker['flagged'] ? 'fieldops-luminaire-frame-spatial__marker--flagged' : '' }} {{ $marker['hasImage'] ? 'fieldops-luminaire-frame-spatial__marker--image' : 'fieldops-luminaire-frame-spatial__marker--placeholder' }}"
                                            :class="{
                                                'fieldops-luminaire-frame-spatial__marker--selected': Number(selectedId) === {{ $marker['id'] }},
                                                'fieldops-luminaire-frame-spatial__marker--readonly': viewMode !== 'technical',
                                            }"
                                            @pointerdown="viewMode === 'technical' && beginDrag($event, {{ $marker['id'] }})"
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
                                                        draggable="false"
                                                        @dragstart.prevent
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
                                            x-show="viewMode === 'technical'"
                                            x-cloak
                                            wire:navigate
                                            @pointerdown.stop
                                            @click.stop
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

                                        <div
                                            x-show="viewMode === 'technical' && Number(selectedId) === {{ $marker['id'] }}"
                                            x-cloak
                                            class="fieldops-luminaire-frame-spatial__scale-tools"
                                            :class="scaleEditorClasses({{ $marker['id'] }})"
                                            @pointerdown.stop
                                            @click.stop
                                            @click.outside="closeScaleEditor()"
                                            @keydown.escape.window="closeScaleEditor()"
                                        >
                                            <button
                                                type="button"
                                                class="fieldops-luminaire-frame-spatial__scale-trigger"
                                                :class="isScaleEditorOpen({{ $marker['id'] }}) ? 'fieldops-luminaire-frame-spatial__scale-trigger--active' : ''"
                                                :aria-expanded="isScaleEditorOpen({{ $marker['id'] }})"
                                                aria-controls="fieldops-marker-scale-editor-{{ $marker['id'] }}"
                                                aria-label="{{ __('fieldops::resource.luminaire_frames.view.resize_marker') }}"
                                                title="{{ __('fieldops::resource.luminaire_frames.view.resize_marker') }}"
                                                @click="toggleScaleEditor({{ $marker['id'] }})"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                    <path d="m3 3 6 6m12-6-6 6M3 21l6-6m12 6-6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                                </svg>
                                            </button>

                                            <div
                                                id="fieldops-marker-scale-editor-{{ $marker['id'] }}"
                                                x-show="isScaleEditorOpen({{ $marker['id'] }})"
                                                x-cloak
                                                x-transition.opacity.duration.120ms
                                                class="fieldops-luminaire-frame-spatial__scale-popover"
                                                data-fieldops-marker-scale-control
                                                role="dialog"
                                                aria-label="{{ __('fieldops::resource.luminaire_frames.view.marker_size') }}"
                                            >
                                                <div class="fieldops-luminaire-frame-spatial__scale-header">
                                                    <div>
                                                        <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                                            {{ __('fieldops::resource.luminaire_frames.view.marker_size') }}
                                                        </div>
                                                        <div class="fieldops-luminaire-frame-spatial__selected-hint">
                                                            {{ __('fieldops::resource.luminaire_frames.view.marker_size_hint') }}
                                                        </div>
                                                    </div>
                                                    <output
                                                        class="fieldops-luminaire-frame-spatial__scale-value"
                                                        x-text="selectedMarkerUniformScale().toFixed(2) + '×'"
                                                    ></output>
                                                </div>

                                                <input
                                                    type="range"
                                                    class="fieldops-luminaire-frame-spatial__scale-slider"
                                                    min="0.25"
                                                    max="3"
                                                    step="0.05"
                                                    :value="selectedMarkerUniformScale()"
                                                    :disabled="scaleSavePending"
                                                    aria-label="{{ __('fieldops::resource.luminaire_frames.view.marker_size') }}"
                                                    @input="previewSelectedMarkerScale($event.target.value)"
                                                >

                                                <div class="fieldops-luminaire-frame-spatial__scale-presets">
                                                    <div class="flex flex-wrap gap-2">
                                                        @foreach ([0.5, 1, 1.5, 2] as $scalePreset)
                                                            <button
                                                                type="button"
                                                                class="fieldops-luminaire-frame-spatial__scale-preset"
                                                                :class="isSelectedMarkerScale({{ $scalePreset }}) ? 'fieldops-luminaire-frame-spatial__scale-preset--active' : ''"
                                                                :disabled="scaleSavePending"
                                                                @click="previewSelectedMarkerScale({{ $scalePreset }})"
                                                            >
                                                                {{ $scalePreset }}×
                                                            </button>
                                                        @endforeach
                                                    </div>

                                                    <span class="fieldops-luminaire-frame-spatial__selected-hint" x-show="scaleSavePending" x-cloak>
                                                        {{ __('fieldops::resource.luminaire_frames.view.saving_scale') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
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

                <div x-show="viewMode === 'technical'" x-cloak class="fieldops-luminaire-frame-spatial__drag-hint">
                    <span aria-hidden="true">↕</span>
                    <span>{{ __('fieldops::resource.luminaire_frames.view.drag_hint') }}</span>
                </div>
            </div>
        </div>

        <aside
            id="fieldops-luminaire-frame-overview-details"
            class="fieldops-luminaire-frame-spatial__rail"
            x-show="(viewMode === 'technical' && technicalDetailsVisible) || (viewMode === 'overview' && overviewDetailsVisible)"
            x-transition.opacity.duration.150ms
        >
            <div x-show="viewMode === 'overview'" class="fieldops-luminaire-frame-spatial__panel fieldops-luminaire-frame-spatial__selected-card">
                <div class="fieldops-luminaire-frame-spatial__selected-title">
                    {{ __('fieldops::resource.luminaire_frames.view.selected_position_label') }}
                </div>

                <template x-if="selectedMarker()">
                    <div>
                        <div class="fieldops-luminaire-frame-spatial__selected-hint">
                            {{ __('fieldops::resource.luminaire_frames.view.selected_overview_hint') }}
                        </div>

                        <div
                            class="fieldops-luminaire-frame-spatial__status-banner"
                            :class="selectedMarker()?.flagged ? 'fieldops-luminaire-frame-spatial__status-banner--warning' : ''"
                        >
                            <span x-text="selectedMarkerTitle()"></span>
                            <span x-text="selectedMarkerStateLabel()"></span>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-row">
                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaires.fields.frame_position') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value" x-text="'#' + selectedMarkerLabel()"></div>
                            </div>

                            <div class="fieldops-luminaire-frame-spatial__selected-stat">
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-label">
                                    {{ __('fieldops::resource.luminaire_frames.view.selected_serial') }}
                                </div>
                                <div class="fieldops-luminaire-frame-spatial__selected-stat-value" x-text="selectedMarkerSerial()"></div>
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
                            <a class="fieldops-luminaire-frame-spatial__link" :href="selectedMarker()?.maintenanceCreateUrl">
                                {{ __('fieldops::resource.luminaires.actions.schedule_maintenance') }}
                            </a>
                            <a class="fieldops-luminaire-frame-spatial__link" :href="selectedMarker()?.maintenanceIndexUrl">
                                {{ __('fieldops::resource.luminaires.actions.view_history') }}
                            </a>
                        </div>
                    </div>
                </template>

                <div class="fieldops-luminaire-frame-spatial__empty-text" x-show="!selectedMarker()" x-cloak>
                    {{ __('fieldops::resource.luminaire_frames.view.selected_empty') }}
                </div>
            </div>

            <div x-show="viewMode === 'technical'" x-cloak class="fieldops-luminaire-frame-spatial__panel">
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
                                    #{{ $marker['label'] }}{{ $marker['serial'] ? ' · '.$marker['serial'] : '' }}
                                </span>
                            </span>

                            <span class="fieldops-luminaire-frame-spatial__mini-pill">
                                {{ $marker['flagged'] ? __('fieldops::resource.luminaire_frames.view.open') : __('fieldops::resource.luminaire_frames.view.no_open_issues') }}
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
                                    {{ $item['flagged'] ? __('fieldops::resource.luminaire_frames.view.open') : __('fieldops::resource.luminaire_frames.view.no_open_issues') }}
                                </span>
                            </button>
                        @endforeach
                    @endif
                </div>
            </div>

            <div x-show="viewMode === 'technical'" x-cloak class="fieldops-luminaire-frame-spatial__panel fieldops-luminaire-frame-spatial__selected-card">
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
                            <a class="fieldops-luminaire-frame-spatial__link" :href="selectedMarker()?.maintenanceCreateUrl">
                                {{ __('fieldops::resource.luminaires.actions.schedule_maintenance') }}
                            </a>
                            <a class="fieldops-luminaire-frame-spatial__link" :href="selectedMarker()?.maintenanceIndexUrl">
                                {{ __('fieldops::resource.luminaires.actions.view_history') }}
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
                                    {{ $selected['flagged'] ? __('fieldops::resource.luminaire_frames.view.open') : __('fieldops::resource.luminaire_frames.view.no_open_issues') }}
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
                                    {{ $selected['positionVerifiedAt'] ? \Illuminate\Support\Carbon::parse($selected['positionVerifiedAt'])->locale(app()->getLocale())->translatedFormat('d M Y H:i') : '—' }}
                                </div>
                            </div>
                        </div>

                        <div class="fieldops-luminaire-frame-spatial__selected-actions">
                            <a class="fieldops-luminaire-frame-spatial__link" href="{{ $selected['url'] }}" wire:navigate>
                                {{ __('fieldops::resource.luminaire_frames.view.open_position_details') }}
                            </a>
                            <a class="fieldops-luminaire-frame-spatial__link" href="{{ $selected['maintenanceCreateUrl'] }}" wire:navigate>
                                {{ __('fieldops::resource.luminaires.actions.schedule_maintenance') }}
                            </a>
                            <a class="fieldops-luminaire-frame-spatial__link" href="{{ $selected['maintenanceIndexUrl'] }}" wire:navigate>
                                {{ __('fieldops::resource.luminaires.actions.view_history') }}
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

    <template x-teleport="body">
        <div
            x-show="createModalOpen"
            x-cloak
            x-transition.opacity.duration.150ms
            class="fieldops-luminaire-frame-spatial__modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="fieldops-add-luminaire-title"
            @keydown.escape.window="closeCreateModal()"
        >
            <div class="fieldops-luminaire-frame-spatial__modal-backdrop" @click="closeCreateModal()"></div>

            <form
                class="fieldops-luminaire-frame-spatial__modal-panel"
                :aria-busy="createPending"
                @submit.prevent="createLuminaire()"
            >
                <div class="fieldops-luminaire-frame-spatial__modal-header">
                    <div>
                        <div id="fieldops-add-luminaire-title" class="fieldops-luminaire-frame-spatial__modal-title">
                            {{ __('fieldops::resource.luminaire_frames.view.add_luminaire') }}
                        </div>
                        <div class="fieldops-luminaire-frame-spatial__modal-copy">
                            {{ __('fieldops::resource.luminaire_frames.view.add_luminaire_copy') }}
                        </div>
                    </div>

                    <button
                        type="button"
                        class="fieldops-luminaire-frame-spatial__modal-close"
                        :disabled="createPending"
                        aria-label="{{ __('fieldops::resource.luminaire_frames.view.cancel') }}"
                        @click="closeCreateModal()"
                    >
                        <span aria-hidden="true">×</span>
                    </button>
                </div>

                <div class="fieldops-luminaire-frame-spatial__modal-body">
                    <label class="fieldops-luminaire-frame-spatial__field" for="fieldops-new-luminaire-type">
                        <span class="fieldops-luminaire-frame-spatial__field-label">
                            {{ __('fieldops::resource.luminaires.fields.luminaire_type') }}
                        </span>
                        <select
                            id="fieldops-new-luminaire-type"
                            class="fieldops-luminaire-frame-spatial__field-control"
                            x-model="newLuminaireTypeId"
                            :disabled="createPending"
                            required
                        >
                            @foreach ($payload['luminaireTypes'] as $type)
                                <option value="{{ $type['id'] }}">
                                    {{ $type['productFamily'] ?: $type['name'] }} — {{ $type['modelReference'] ?: $type['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <template x-if="selectedCreateType()">
                        <div class="fieldops-luminaire-frame-spatial__type-preview" data-fieldops-luminaire-type-preview>
                            <div class="fieldops-luminaire-frame-spatial__type-preview-media">
                                <img
                                    class="fieldops-luminaire-frame-spatial__type-preview-image"
                                    :src="selectedCreateType().imageUrl"
                                    :alt="selectedCreateType().name"
                                    x-on:error="$event.target.src = @js($placeholderImage)"
                                >
                            </div>
                            <div>
                                <div class="fieldops-luminaire-frame-spatial__type-preview-name" x-text="selectedCreateType().productFamily || selectedCreateType().name"></div>
                                <div
                                    class="fieldops-luminaire-frame-spatial__type-preview-meta"
                                    x-show="selectedCreateType().modelReference"
                                    x-text="selectedCreateType().modelReference"
                                ></div>
                                <div class="fieldops-luminaire-frame-spatial__type-preview-meta" x-text="selectedCreateType().subgroupLabel"></div>
                                <div
                                    class="fieldops-luminaire-frame-spatial__type-preview-meta"
                                    x-show="selectedCreateType().typicalApplication"
                                    x-text="selectedCreateType().typicalApplication"
                                ></div>
                            </div>
                        </div>
                    </template>

                    <label class="fieldops-luminaire-frame-spatial__field" for="fieldops-new-luminaire-serial">
                        <span class="fieldops-luminaire-frame-spatial__field-label">
                            {{ __('fieldops::resource.luminaires.fields.serial_number') }}
                        </span>
                        <input
                            id="fieldops-new-luminaire-serial"
                            type="text"
                            class="fieldops-luminaire-frame-spatial__field-control"
                            x-model="newLuminaireSerial"
                            :disabled="createPending"
                            maxlength="50"
                            autocomplete="off"
                        >
                        <span class="fieldops-luminaire-frame-spatial__field-hint">
                            {{ __('fieldops::resource.luminaire_frames.view.serial_optional_hint') }}
                        </span>
                    </label>

                    <div class="fieldops-luminaire-frame-spatial__field-hint">
                        {{ __('fieldops::resource.luminaire_frames.view.initial_position_hint') }}
                    </div>

                    <div x-show="createError" x-cloak class="fieldops-luminaire-frame-spatial__modal-error" x-text="createError"></div>
                </div>

                <div class="fieldops-luminaire-frame-spatial__modal-actions">
                    <button
                        type="button"
                        class="fieldops-luminaire-frame-spatial__button"
                        :disabled="createPending"
                        @click="closeCreateModal()"
                    >
                        {{ __('fieldops::resource.luminaire_frames.view.cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="fieldops-luminaire-frame-spatial__button fieldops-luminaire-frame-spatial__button--primary"
                        :disabled="createPending || !newLuminaireTypeId"
                    >
                        <span x-show="!createPending">{{ __('fieldops::resource.luminaire_frames.view.add_luminaire') }}</span>
                        <span x-show="createPending" x-cloak>{{ __('fieldops::resource.luminaire_frames.view.creating_luminaire') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </template>
</div>
