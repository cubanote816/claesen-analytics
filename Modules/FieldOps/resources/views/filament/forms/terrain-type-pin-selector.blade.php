@php
    /**
     * @var array<int, array{code: string, labelKey: string, defaultColor: string, svg: string}> $pins
     */
    $pins = $getViewData()['pins'] ?? [];
    $selectedCode = (string) ($getState() ?? '');
    $statePath = $getStatePath();
    $fieldId = $getId();
@endphp

@once
    <style>
        .fieldops-terrain-pin-selector {
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.25rem;
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.08), transparent 35%),
                linear-gradient(180deg, #ffffff 0%, #f7fbfe 100%);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .dark .fieldops-terrain-pin-selector {
            border-color: rgba(255, 255, 255, 0.08);
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.14), transparent 30%),
                linear-gradient(180deg, #171725 0%, #11111a 100%);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 24px 60px -18px rgba(0, 0, 0, 0.62);
        }

        .fieldops-terrain-pin-selector__search {
            padding: 1rem 1rem 0;
        }

        .fieldops-terrain-pin-selector__search-input {
            width: 100%;
            min-height: 2.4rem;
            padding: 0.45rem 0.8rem;
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.8);
            color: #0f172a;
            font-size: 0.85rem;
        }

        .dark .fieldops-terrain-pin-selector__search-input {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.04);
            color: #e2e8f0;
        }

        .fieldops-terrain-pin-selector__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .fieldops-terrain-pin-selector__grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .fieldops-terrain-pin-selector__grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .fieldops-terrain-pin-selector__item { position: relative; }

        .fieldops-terrain-pin-selector__radio {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .fieldops-terrain-pin-selector__card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            padding: 0.75rem 0.5rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.72);
            text-align: center;
            transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
        }

        .dark .fieldops-terrain-pin-selector__card {
            border-color: rgba(255, 255, 255, 0.08);
            background: rgba(255, 255, 255, 0.03);
        }

        .fieldops-terrain-pin-selector__radio:focus-visible + .fieldops-terrain-pin-selector__card,
        .fieldops-terrain-pin-selector__radio:checked + .fieldops-terrain-pin-selector__card {
            border-color: rgba(14, 165, 233, 0.75);
            box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.24), 0 12px 30px rgba(15, 23, 42, 0.14);
            transform: translateY(-1px);
        }

        .fieldops-terrain-pin-selector__preview { width: 40px; height: 40px; }
        .fieldops-terrain-pin-selector__preview svg { width: 100%; height: 100%; }

        .fieldops-terrain-pin-selector__title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }

        .dark .fieldops-terrain-pin-selector__title { color: #e2e8f0; }

        .fieldops-terrain-pin-selector__empty {
            grid-column: 1 / -1;
            padding: 1rem;
            text-align: center;
            font-size: 0.85rem;
            color: #64748b;
        }
    </style>
@endonce

<div
    class="fieldops-terrain-pin-selector"
    x-data="{ search: '' }"
>
    <div class="fieldops-terrain-pin-selector__search">
        <input
            type="text"
            x-model="search"
            placeholder="{{ __('fieldops::resource.catalogs.pin_selector.search_placeholder') }}"
            class="fieldops-terrain-pin-selector__search-input"
        >
    </div>

    <div class="fieldops-terrain-pin-selector__grid" role="radiogroup" aria-label="{{ __('fieldops::resource.catalogs.pin_selector.label') }}">
        {{-- Generic / no icon is an explicit, always-visible option — not just an invisible fallback --}}
        @php $genericLabel = __('fieldops::resource.catalogs.pin_selector.generic_label'); @endphp
        <div class="fieldops-terrain-pin-selector__item" x-show="search === '' || @js(mb_strtolower($genericLabel)).includes(search.toLowerCase())">
            @php $genericInputId = $fieldId.'-pin-generic'; @endphp
            <input
                id="{{ $genericInputId }}"
                class="fieldops-terrain-pin-selector__radio"
                type="radio"
                name="{{ $statePath }}"
                value=""
                wire:model.live="{{ $statePath }}"
                @checked($selectedCode === '')
            >
            <label for="{{ $genericInputId }}" class="fieldops-terrain-pin-selector__card">
                <div class="fieldops-terrain-pin-selector__preview">
                    <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 4C14.48 4 10 8.48 10 14C10 21.5 20 36 20 36C20 36 30 21.5 30 14C30 8.48 25.52 4 20 4Z"
                              fill="#6b7488" stroke="white" stroke-width="1"/>
                        <line x1="16.5" y1="10" x2="16.5" y2="21" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
                        <path d="M16.5,10 L23,12.7 L16.5,15.4 Z" fill="white"/>
                    </svg>
                </div>
                <div class="fieldops-terrain-pin-selector__title">{{ $genericLabel }}</div>
            </label>
        </div>

        @foreach ($pins as $pin)
            @php
                $label = __($pin['labelKey']);
                $inputId = $fieldId.'-pin-'.$pin['code'];
                $previewSvg = str_replace('${fill}', $pin['defaultColor'], $pin['svg']);
            @endphp
            <div class="fieldops-terrain-pin-selector__item" x-show="search === '' || @js(mb_strtolower($label)).includes(search.toLowerCase()) || @js($pin['code']).includes(search.toLowerCase())">
                <input
                    id="{{ $inputId }}"
                    class="fieldops-terrain-pin-selector__radio"
                    type="radio"
                    name="{{ $statePath }}"
                    value="{{ $pin['code'] }}"
                    wire:model.live="{{ $statePath }}"
                    @checked($selectedCode === $pin['code'])
                >
                <label for="{{ $inputId }}" class="fieldops-terrain-pin-selector__card">
                    <div class="fieldops-terrain-pin-selector__preview">
                        {!! $previewSvg !!}
                    </div>
                    <div class="fieldops-terrain-pin-selector__title">{{ $label }}</div>
                </label>
            </div>
        @endforeach
    </div>
</div>
