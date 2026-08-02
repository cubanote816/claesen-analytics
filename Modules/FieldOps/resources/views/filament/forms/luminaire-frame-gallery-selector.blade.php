@php
    /**
     * @var array<int, array{
     *     id: int|string,
     *     title: string,
     *     typeLabel: string,
     *     indicator: string,
     *     previewUrl: ?string,
     *     previewAlt: string,
     *     hasPreview: bool
     * }> $frames
     */
    $frames = $getViewData()['frames'] ?? [];
    $selectedFrameId = (string) ($getState() ?? '');
    $statePath = $getStatePath();
    $fieldId = $getId();
    $createFrameTypeUrl = url('/catalogs/luminaire-frame-types/create').'?'.http_build_query([
        'return_to' => url()->full(),
    ]);
@endphp

@once
    <style>
        .fieldops-luminaire-frame-gallery {
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.25rem;
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.08), transparent 35%),
                linear-gradient(180deg, #ffffff 0%, #f7fbfe 100%);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .dark .fieldops-luminaire-frame-gallery {
            border-color: rgba(255, 255, 255, 0.08);
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.14), transparent 30%),
                linear-gradient(180deg, #171725 0%, #11111a 100%);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 24px 60px -18px rgba(0, 0, 0, 0.62);
        }

        .fieldops-luminaire-frame-gallery__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .fieldops-luminaire-frame-gallery__header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            padding: 1rem 1rem 0.9rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
        }

        .dark .fieldops-luminaire-frame-gallery__header {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .fieldops-luminaire-frame-gallery__header-copy {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .fieldops-luminaire-frame-gallery__header-title {
            color: #f8fafc;
            font-size: 0.94rem;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .fieldops-luminaire-frame-gallery__header-text {
            color: #94a3b8;
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .fieldops-luminaire-frame-gallery__create-link {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            min-height: 2.4rem;
            padding: 0.45rem 0.8rem;
            border: 1px solid rgba(14, 165, 233, 0.3);
            border-radius: 0.85rem;
            background: rgba(14, 165, 233, 0.12);
            color: #7dd3fc;
            font-size: 0.84rem;
            font-weight: 700;
            transition: transform 150ms ease, border-color 150ms ease, background-color 150ms ease;
        }

        .fieldops-luminaire-frame-gallery__create-link:hover {
            transform: translateY(-1px);
            border-color: rgba(56, 189, 248, 0.36);
            background: rgba(14, 165, 233, 0.18);
        }

        .fieldops-luminaire-frame-gallery__create-link:focus-visible {
            outline: 2px solid rgba(14, 165, 233, 0.55);
            outline-offset: 2px;
        }

        @media (min-width: 768px) {
            .fieldops-luminaire-frame-gallery__grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .fieldops-luminaire-frame-gallery__grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .fieldops-luminaire-frame-gallery__empty {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 12rem;
            border: 1px dashed rgba(148, 163, 184, 0.26);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.5);
            color: #64748b;
            font-size: 0.92rem;
        }

        .dark .fieldops-luminaire-frame-gallery__empty {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            color: #94a3b8;
        }

        .fieldops-luminaire-frame-gallery__item {
            position: relative;
        }

        .fieldops-luminaire-frame-gallery__radio {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .fieldops-luminaire-frame-gallery__card {
            position: relative;
            display: flex;
            height: 100%;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.25rem;
            background:
                linear-gradient(180deg, rgba(15, 23, 42, 0.02), rgba(15, 23, 42, 0.04)),
                rgba(255, 255, 255, 0.72);
            transition:
                transform 150ms ease,
                border-color 150ms ease,
                box-shadow 150ms ease,
                background-color 150ms ease;
        }

        .dark .fieldops-luminaire-frame-gallery__card {
            border-color: rgba(255, 255, 255, 0.08);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.04)),
                rgba(255, 255, 255, 0.03);
        }

        .fieldops-luminaire-frame-gallery__radio:focus-visible + .fieldops-luminaire-frame-gallery__card,
        .fieldops-luminaire-frame-gallery__radio:checked + .fieldops-luminaire-frame-gallery__card {
            border-color: rgba(14, 165, 233, 0.75);
            box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.24), 0 18px 40px rgba(15, 23, 42, 0.18);
            transform: translateY(-1px);
        }

        .fieldops-luminaire-frame-gallery__radio:checked + .fieldops-luminaire-frame-gallery__card {
            background:
                radial-gradient(circle at top left, rgba(14, 165, 233, 0.14), transparent 38%),
                rgba(255, 255, 255, 0.92);
        }

        .dark .fieldops-luminaire-frame-gallery__radio:checked + .fieldops-luminaire-frame-gallery__card {
            background:
                radial-gradient(circle at top left, rgba(14, 165, 233, 0.18), transparent 34%),
                rgba(255, 255, 255, 0.05);
        }

        .fieldops-luminaire-frame-gallery__preview {
            position: relative;
            aspect-ratio: 4 / 3;
            overflow: hidden;
            background:
                linear-gradient(135deg, rgba(0, 174, 239, 0.08), rgba(255, 255, 255, 0.5)),
                radial-gradient(circle at center, rgba(148, 163, 184, 0.22), transparent 68%);
        }

        .dark .fieldops-luminaire-frame-gallery__preview {
            background:
                linear-gradient(135deg, rgba(0, 174, 239, 0.18), rgba(255, 255, 255, 0.04)),
                radial-gradient(circle at center, rgba(255, 255, 255, 0.08), transparent 68%);
        }

        .fieldops-luminaire-frame-gallery__preview-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .fieldops-luminaire-frame-gallery__placeholder {
            display: flex;
            height: 100%;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            padding: 1rem;
            text-align: center;
        }

        .fieldops-luminaire-frame-gallery__placeholder-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.65);
            color: #0ea5e9;
        }

        .dark .fieldops-luminaire-frame-gallery__placeholder-icon {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(15, 23, 42, 0.5);
            color: #7dd3fc;
        }

        .fieldops-luminaire-frame-gallery__placeholder-title {
            color: #0f172a;
            font-size: 0.92rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .dark .fieldops-luminaire-frame-gallery__placeholder-title {
            color: #e2e8f0;
        }

        .fieldops-luminaire-frame-gallery__placeholder-subtitle {
            color: #64748b;
            font-size: 0.76rem;
            line-height: 1.35;
        }

        .dark .fieldops-luminaire-frame-gallery__placeholder-subtitle {
            color: #94a3b8;
        }

        .fieldops-luminaire-frame-gallery__meta {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            padding: 0.85rem 0.9rem 0.95rem;
        }

        .fieldops-luminaire-frame-gallery__title {
            color: #0f172a;
            font-size: 0.95rem;
            font-weight: 800;
            line-height: 1.3;
        }

        .dark .fieldops-luminaire-frame-gallery__title {
            color: #f8fafc;
        }

        .fieldops-luminaire-frame-gallery__badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .fieldops-luminaire-frame-gallery__badge {
            display: inline-flex;
            align-items: center;
            min-height: 1.6rem;
            padding: 0.2rem 0.55rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.68);
            color: #475569;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            line-height: 1;
        }

        .dark .fieldops-luminaire-frame-gallery__badge {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.04);
            color: #cbd5e1;
        }

        .fieldops-luminaire-frame-gallery__badge--accent {
            border-color: rgba(14, 165, 233, 0.24);
            background: rgba(14, 165, 233, 0.08);
            color: #0369a1;
        }

        .dark .fieldops-luminaire-frame-gallery__badge--accent {
            border-color: rgba(56, 189, 248, 0.24);
            background: rgba(56, 189, 248, 0.12);
            color: #7dd3fc;
        }

        .fieldops-luminaire-frame-gallery__zoom {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            z-index: 20;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.66);
            color: #f8fafc;
            backdrop-filter: blur(12px);
            transition: transform 150ms ease, background-color 150ms ease;
        }

        .fieldops-luminaire-frame-gallery__zoom:hover {
            transform: scale(1.04);
            background: rgba(15, 23, 42, 0.82);
        }

        .fieldops-luminaire-frame-gallery__zoom:focus-visible {
            outline: 2px solid rgba(14, 165, 233, 0.55);
            outline-offset: 2px;
        }

        .fieldops-luminaire-frame-gallery__modal {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .fieldops-luminaire-frame-gallery__modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(2, 6, 23, 0.72);
            backdrop-filter: blur(8px);
        }

        .fieldops-luminaire-frame-gallery__modal-panel {
            position: relative;
            z-index: 1;
            width: min(52rem, 100%);
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.35rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbfe 100%);
            box-shadow: 0 28px 90px rgba(2, 6, 23, 0.55);
        }

        .dark .fieldops-luminaire-frame-gallery__modal-panel {
            border-color: rgba(255, 255, 255, 0.08);
            background: linear-gradient(180deg, #111827 0%, #0b1120 100%);
        }

        .fieldops-luminaire-frame-gallery__modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.15rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.16);
        }

        .dark .fieldops-luminaire-frame-gallery__modal-header {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .fieldops-luminaire-frame-gallery__modal-title {
            color: #0f172a;
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.3;
        }

        .dark .fieldops-luminaire-frame-gallery__modal-title {
            color: #f8fafc;
        }

        .fieldops-luminaire-frame-gallery__modal-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.7);
            color: #334155;
        }

        .dark .fieldops-luminaire-frame-gallery__modal-close {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.06);
            color: #e2e8f0;
        }

        .fieldops-luminaire-frame-gallery__modal-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1rem;
            padding: 1rem 1.15rem 1.15rem;
        }

        .fieldops-luminaire-frame-gallery__modal-image {
            width: 100%;
            max-height: min(70vh, 42rem);
            object-fit: contain;
            border-radius: 1rem;
            background: #020617;
        }

        .fieldops-luminaire-frame-gallery__modal-caption {
            color: #64748b;
            font-size: 0.84rem;
            line-height: 1.45;
        }

        .dark .fieldops-luminaire-frame-gallery__modal-caption {
            color: #94a3b8;
        }
    </style>
@endonce

<div
    class="fieldops-luminaire-frame-gallery"
    data-fieldops-frame-gallery-selector
    x-data="{ previewOpen: false, previewUrl: '', previewTitle: '' }"
>
    <div class="fieldops-luminaire-frame-gallery__header">
        <div class="fieldops-luminaire-frame-gallery__header-copy">
            <div class="fieldops-luminaire-frame-gallery__header-title">
                {{ __('fieldops::resource.luminaire_frames.gallery.title') }}
            </div>
            <div class="fieldops-luminaire-frame-gallery__header-text">
                {{ __('fieldops::resource.luminaire_frames.gallery.helper') }}
            </div>
        </div>

        <a
            {{ \Filament\Support\generate_href_html($createFrameTypeUrl) }}
            class="fieldops-luminaire-frame-gallery__create-link"
        >
            <x-heroicon-m-plus class="h-4 w-4" />
            <span>{{ __('fieldops::resource.luminaire_frames.gallery.create_type') }}</span>
        </a>
    </div>

    @if (count($frames) > 0)
        <div class="fieldops-luminaire-frame-gallery__grid" role="radiogroup" aria-label="{{ __('fieldops::resource.luminaires.fields.frame') }}">
            @foreach ($frames as $frame)
                @php
                    $inputId = $fieldId.'-frame-'.$frame['id'];
                    $isSelected = $selectedFrameId !== '' && $selectedFrameId === (string) $frame['id'];
                @endphp
                <div class="fieldops-luminaire-frame-gallery__item">
                    <input
                        id="{{ $inputId }}"
                        class="fieldops-luminaire-frame-gallery__radio"
                        type="radio"
                        name="{{ $statePath }}"
                        value="{{ $frame['id'] }}"
                        wire:model.live="{{ $statePath }}"
                        @checked($isSelected)
                    >

                    <label for="{{ $inputId }}" class="fieldops-luminaire-frame-gallery__card">
                        <div class="fieldops-luminaire-frame-gallery__preview">
                            @if ($frame['hasPreview'])
                                <img
                                    src="{{ $frame['previewUrl'] }}"
                                    alt="{{ $frame['previewAlt'] }}"
                                    class="fieldops-luminaire-frame-gallery__preview-image"
                                >
                            @else
                                <div class="fieldops-luminaire-frame-gallery__placeholder">
                                    <div class="fieldops-luminaire-frame-gallery__placeholder-icon">
                                        <x-heroicon-o-squares-2x2 class="h-5 w-5" />
                                    </div>
                                    <div class="fieldops-luminaire-frame-gallery__placeholder-title">
                                        {{ $frame['indicator'] }}
                                    </div>
                                    <div class="fieldops-luminaire-frame-gallery__placeholder-subtitle">
                                        {{ __('fieldops::resource.luminaire_frames.gallery.no_preview') }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="fieldops-luminaire-frame-gallery__meta">
                            <div class="fieldops-luminaire-frame-gallery__title">
                                {{ $frame['title'] }}
                            </div>

                            <div class="fieldops-luminaire-frame-gallery__badges">
                                <span class="fieldops-luminaire-frame-gallery__badge fieldops-luminaire-frame-gallery__badge--accent">
                                    {{ $frame['indicator'] }}
                                </span>
                                <span class="fieldops-luminaire-frame-gallery__badge">
                                    {{ $frame['typeLabel'] }}
                                </span>
                                @if ($isSelected)
                                    <span class="fieldops-luminaire-frame-gallery__badge">
                                        {{ __('fieldops::resource.luminaire_frames.gallery.selected') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </label>

                    @if ($frame['hasPreview'])
                        <button
                            type="button"
                            class="fieldops-luminaire-frame-gallery__zoom"
                            aria-label="{{ __('fieldops::resource.luminaire_frames.gallery.open_preview') }}"
                            title="{{ __('fieldops::resource.luminaire_frames.gallery.open_preview') }}"
                            x-on:click.stop.prevent="previewOpen = true; previewUrl = @js($frame['previewUrl']); previewTitle = @js($frame['title']);"
                        >
                            <x-heroicon-m-magnifying-glass-plus class="h-4 w-4" />
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="fieldops-luminaire-frame-gallery__empty">
            <div>
                <div>{{ __('fieldops::resource.luminaire_frames.gallery.empty') }}</div>
                <div class="mt-2 text-xs text-sky-400/80">
                    {{ __('fieldops::resource.luminaire_frames.gallery.empty_hint') }}
                </div>
            </div>
        </div>
    @endif

    <div
        x-cloak
        x-show="previewOpen"
        x-transition.opacity.duration.150ms
        class="fieldops-luminaire-frame-gallery__modal"
        style="display: none;"
        role="dialog"
        aria-modal="true"
        :aria-label="previewTitle"
        x-on:keydown.escape.window="previewOpen = false"
    >
        <div class="fieldops-luminaire-frame-gallery__modal-backdrop" x-on:click="previewOpen = false"></div>

        <div class="fieldops-luminaire-frame-gallery__modal-panel">
            <div class="fieldops-luminaire-frame-gallery__modal-header">
                <div class="fieldops-luminaire-frame-gallery__modal-title" x-text="previewTitle"></div>

                <button
                    type="button"
                    class="fieldops-luminaire-frame-gallery__modal-close"
                    aria-label="{{ __('fieldops::resource.luminaire_frames.gallery.close_preview') }}"
                    title="{{ __('fieldops::resource.luminaire_frames.gallery.close_preview') }}"
                    x-on:click="previewOpen = false"
                >
                    <x-heroicon-m-x-mark class="h-4 w-4" />
                </button>
            </div>

            <div class="fieldops-luminaire-frame-gallery__modal-body">
                <img
                    :src="previewUrl"
                    :alt="previewTitle"
                    class="fieldops-luminaire-frame-gallery__modal-image"
                >
                <p class="fieldops-luminaire-frame-gallery__modal-caption">
                    {{ __('fieldops::resource.luminaire_frames.gallery.preview_hint') }}
                </p>
            </div>
        </div>
    </div>
</div>
