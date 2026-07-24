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
        .fieldops-structure-pin-selector {
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1.25rem;
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.08), transparent 35%),
                linear-gradient(180deg, #ffffff 0%, #f7fbfe 100%);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .dark .fieldops-structure-pin-selector {
            border-color: rgba(255, 255, 255, 0.08);
            background:
                radial-gradient(circle at top left, rgba(0, 174, 239, 0.14), transparent 30%),
                linear-gradient(180deg, #171725 0%, #11111a 100%);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.08), 0 24px 60px -18px rgba(0, 0, 0, 0.62);
        }

        .fieldops-structure-pin-selector__search {
            padding: 1rem 1rem 0;
        }

        .fieldops-structure-pin-selector__search-input {
            width: 100%;
            min-height: 2.4rem;
            padding: 0.45rem 0.8rem;
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.8);
            color: #0f172a;
            font-size: 0.85rem;
        }

        .dark .fieldops-structure-pin-selector__search-input {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.04);
            color: #e2e8f0;
        }

        .fieldops-structure-pin-selector__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .fieldops-structure-pin-selector__grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .fieldops-structure-pin-selector__item { position: relative; }

        .fieldops-structure-pin-selector__radio {
            position: absolute;
            inset: 0;
            opacity: 0;
            pointer-events: none;
        }

        .fieldops-structure-pin-selector__card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            padding: 0.75rem 0.5rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 1rem;
            background: rgba(15, 23, 42, 0.9);
            text-align: center;
            transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
        }

        .fieldops-structure-pin-selector__radio:focus-visible + .fieldops-structure-pin-selector__card,
        .fieldops-structure-pin-selector__radio:checked + .fieldops-structure-pin-selector__card {
            border-color: rgba(14, 165, 233, 0.75);
            box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.24), 0 12px 30px rgba(15, 23, 42, 0.14);
            transform: translateY(-1px);
        }

        .fieldops-structure-pin-selector__preview { width: 40px; height: 58px; }
        .fieldops-structure-pin-selector__preview svg { width: 100%; height: 100%; }

        .fieldops-structure-pin-selector__title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }

        .dark .fieldops-structure-pin-selector__title { color: #e2e8f0; }

        .fieldops-structure-pin-selector__empty {
            grid-column: 1 / -1;
            padding: 1rem;
            text-align: center;
            font-size: 0.85rem;
            color: #64748b;
        }
    </style>
@endonce

<div
    class="fieldops-structure-pin-selector"
    x-data="{ search: '' }"
>
    <div class="fieldops-structure-pin-selector__search">
        <input
            type="text"
            x-model="search"
            placeholder="{{ __('fieldops::resource.catalogs.structure_pin_selector.search_placeholder') }}"
            class="fieldops-structure-pin-selector__search-input"
        >
    </div>

    <div class="fieldops-structure-pin-selector__grid" role="radiogroup" aria-label="{{ __('fieldops::resource.catalogs.structure_pin_selector.label') }}">
        {{-- Generic / no icon is an explicit, always-visible option — not just an invisible fallback --}}
        @php $genericLabel = __('fieldops::resource.catalogs.structure_pin_selector.generic_label'); @endphp
        <div class="fieldops-structure-pin-selector__item" x-show="search === '' || @js(mb_strtolower($genericLabel)).includes(search.toLowerCase())">
            @php $genericInputId = $fieldId.'-pin-generic'; @endphp
            <input
                id="{{ $genericInputId }}"
                class="fieldops-structure-pin-selector__radio"
                type="radio"
                name="{{ $statePath }}"
                value=""
                wire:model.live="{{ $statePath }}"
                @checked($selectedCode === '')
            >
            <label for="{{ $genericInputId }}" class="fieldops-structure-pin-selector__card">
                <div class="fieldops-structure-pin-selector__preview">
                    {!! \Modules\FieldOps\Support\StructurePinCatalog::fallbackSvg() !!}
                </div>
                <div class="fieldops-structure-pin-selector__title">{{ $genericLabel }}</div>
            </label>
        </div>

        @foreach ($pins as $pin)
            @php
                $label = __($pin['labelKey']);
                $inputId = $fieldId.'-pin-'.$pin['code'];
            @endphp
            <div class="fieldops-structure-pin-selector__item" x-show="search === '' || @js(mb_strtolower($label)).includes(search.toLowerCase()) || @js($pin['code']).includes(search.toLowerCase())">
                <input
                    id="{{ $inputId }}"
                    class="fieldops-structure-pin-selector__radio"
                    type="radio"
                    name="{{ $statePath }}"
                    value="{{ $pin['code'] }}"
                    wire:model.live="{{ $statePath }}"
                    @checked($selectedCode === $pin['code'])
                >
                <label for="{{ $inputId }}" class="fieldops-structure-pin-selector__card">
                    <div class="fieldops-structure-pin-selector__preview">
                        {!! $pin['svg'] !!}
                    </div>
                    <div class="fieldops-structure-pin-selector__title">{{ $label }}</div>
                </label>
            </div>
        @endforeach
    </div>
</div>
