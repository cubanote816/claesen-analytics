@php
    /** @var array<int, array<string, mixed>> $types */
    $types = $getViewData()['types'] ?? [];
    $selectedTypeId = (string) ($getState() ?? '');
    $statePath = $getStatePath();
    $fieldId = $getId();
    $subgroups = collect($types)->pluck('subgroup')->filter()->unique()->values();
    $hasError = $errors->has($statePath);
    $locked = $getViewData()['locked'] ?? false;
    $replaceUrl = $getViewData()['replaceUrl'] ?? null;
    $lockedType = $locked ? collect($types)->first(fn ($t) => (string) $t['id'] === $selectedTypeId) : null;
@endphp

@once
    <style>
        [x-cloak] { display: none !important; }
        .fieldops-luminaire-type-picker { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; }
        .fieldops-luminaire-type-picker__item { position:relative; min-width:0; }
        .fieldops-luminaire-type-picker__radio { position:absolute; opacity:0; pointer-events:none; }
        .fieldops-luminaire-type-picker__card { display:grid; grid-template-columns:6.5rem minmax(0,1fr); gap:.9rem; min-height:8rem; overflow:hidden; cursor:pointer; border:1px solid rgba(148,163,184,.2); border-radius:1rem; background:#fff; transition:border-color .15s ease,transform .15s ease,box-shadow .15s ease; }
        .dark .fieldops-luminaire-type-picker__card { border-color:rgba(255,255,255,.08); background:#171725; }
        .fieldops-luminaire-type-picker__card:hover { transform:translateY(-1px); border-color:rgba(14,165,233,.42); box-shadow:0 12px 24px rgba(15,23,42,.08); }
        .fieldops-luminaire-type-picker__radio:checked + .fieldops-luminaire-type-picker__card { border-color:#0ea5e9; box-shadow:0 0 0 3px rgba(14,165,233,.16),0 14px 28px rgba(15,23,42,.1); }
        .fieldops-luminaire-type-picker__image { display:grid; place-items:center; padding:.65rem; background:rgba(15,23,42,.035); border-right:1px solid rgba(148,163,184,.12); }
        .dark .fieldops-luminaire-type-picker__image { background:rgba(2,6,23,.25); border-right-color:rgba(255,255,255,.06); }
        .fieldops-luminaire-type-picker__image img { width:100%; height:6.4rem; object-fit:contain; }
        .fieldops-luminaire-type-picker__copy { display:flex; flex-direction:column; justify-content:center; min-width:0; padding:.8rem .8rem .8rem 0; }
        .fieldops-luminaire-type-picker__family { color:#0f172a; font-size:.9rem; font-weight:850; line-height:1.25; }
        .dark .fieldops-luminaire-type-picker__family { color:#f1f5f9; }
        .fieldops-luminaire-type-picker__reference { margin-top:.2rem; color:#0ea5e9; font-size:.78rem; font-weight:800; }
        .dark .fieldops-luminaire-type-picker__reference { color:#7dd3fc; }
        .fieldops-luminaire-type-picker__meta { margin-top:.35rem; color:#64748b; font-size:.72rem; line-height:1.35; }
        .dark .fieldops-luminaire-type-picker__meta { color:#94a3b8; }
        @media(max-width:1100px){ .fieldops-luminaire-type-picker{grid-template-columns:repeat(2,minmax(0,1fr));} }
        @media(max-width:700px){ .fieldops-luminaire-type-picker{grid-template-columns:1fr;} }
        @media(max-width:520px){ .fieldops-luminaire-type-picker__card{grid-template-columns:5.2rem 1fr;} }

        .fieldops-luminaire-type-picker__toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.6rem; margin-bottom:.85rem; }
        .fieldops-luminaire-type-picker__search { flex:1 1 16rem; min-width:12rem; padding:.55rem .8rem; border:1px solid rgba(148,163,184,.32); border-radius:.65rem; font-size:.85rem; background:#fff; color:#0f172a; }
        .dark .fieldops-luminaire-type-picker__search { background:#171725; border-color:rgba(255,255,255,.1); color:#f1f5f9; }
        .fieldops-luminaire-type-picker__chips { display:flex; flex-wrap:wrap; gap:.4rem; }
        .fieldops-luminaire-type-picker__chip { padding:.35rem .75rem; border-radius:999px; border:1px solid rgba(148,163,184,.32); background:transparent; color:#475569; font-size:.75rem; font-weight:600; cursor:pointer; transition:background-color .15s ease,border-color .15s ease,color .15s ease; }
        .dark .fieldops-luminaire-type-picker__chip { border-color:rgba(255,255,255,.12); color:#94a3b8; }
        .fieldops-luminaire-type-picker__chip.is-active { background:#0ea5e9; border-color:#0ea5e9; color:#fff; }
        .fieldops-luminaire-type-picker__empty { padding:1.5rem; text-align:center; color:#64748b; font-size:.85rem; }
        .dark .fieldops-luminaire-type-picker__empty { color:#94a3b8; }

        .fieldops-luminaire-type-picker__selected { display:grid; grid-template-columns:6.5rem minmax(0,1fr) auto; align-items:center; gap:1rem; padding:.9rem; border:1px solid rgba(14,165,233,.42); border-radius:1rem; background:rgba(14,165,233,.06); }
        .dark .fieldops-luminaire-type-picker__selected { background:rgba(14,165,233,.1); }
        .fieldops-luminaire-type-picker__selected-image { display:grid; place-items:center; }
        .fieldops-luminaire-type-picker__selected-image img { width:100%; height:5rem; object-fit:contain; }
        .fieldops-luminaire-type-picker__selected-copy { display:flex; flex-direction:column; min-width:0; }
        .fieldops-luminaire-type-picker__change-btn { padding:.5rem 1rem; border-radius:.6rem; border:1px solid rgba(14,165,233,.5); background:#fff; color:#0ea5e9; font-size:.78rem; font-weight:700; cursor:pointer; white-space:nowrap; transition:background-color .15s ease; }
        .dark .fieldops-luminaire-type-picker__change-btn { background:#171725; }
        .fieldops-luminaire-type-picker__change-btn:hover { background:rgba(14,165,233,.12); }

        .fieldops-luminaire-type-picker-wrapper--error .fieldops-luminaire-type-picker,
        .fieldops-luminaire-type-picker-wrapper--error .fieldops-luminaire-type-picker__selected { outline:2px solid #f43f5e; outline-offset:4px; border-radius:1rem; }
        .fieldops-luminaire-type-picker__error-message { margin-top:.6rem; color:#f43f5e; font-size:.8rem; font-weight:600; }
        .dark .fieldops-luminaire-type-picker__error-message { color:#fda4af; }

        .fieldops-luminaire-type-picker__selected--locked { opacity:.9; }
        .fieldops-luminaire-type-picker__locked-hint { grid-column:1 / -1; margin-top:.5rem; color:#0ea5e9; font-size:.78rem; font-weight:700; text-decoration:none; }
        .dark .fieldops-luminaire-type-picker__locked-hint { color:#7dd3fc; }
        .fieldops-luminaire-type-picker__locked-hint:hover { text-decoration:underline; }
    </style>
@endonce

@if ($locked)
    <div class="fieldops-luminaire-type-picker-wrapper">
        <div class="fieldops-luminaire-type-picker__selected fieldops-luminaire-type-picker__selected--locked">
            <span class="fieldops-luminaire-type-picker__selected-image">
                <img src="{{ $lockedType['imageUrl'] ?? asset('assets/luminaire-subgroups/image_placeholder.png') }}" alt="{{ $lockedType['family'] ?? '' }}">
            </span>
            <span class="fieldops-luminaire-type-picker__selected-copy">
                <span class="fieldops-luminaire-type-picker__family">{{ $lockedType['family'] ?? '—' }}</span>
                @if ($lockedType['reference'] ?? null)<span class="fieldops-luminaire-type-picker__reference">{{ $lockedType['reference'] }}</span>@endif
                @if ($lockedType['subgroup'] ?? null)<span class="fieldops-luminaire-type-picker__meta">{{ $lockedType['subgroup'] }}</span>@endif
            </span>
            @if ($replaceUrl)
                <a {{ \Filament\Support\generate_href_html($replaceUrl) }} class="fieldops-luminaire-type-picker__locked-hint">
                    {{ __('fieldops::resource.luminaires.type_picker.locked_hint') }}
                </a>
            @endif
        </div>
    </div>
@else
<div
    class="fieldops-luminaire-type-picker-wrapper @if ($hasError) fieldops-luminaire-type-picker-wrapper--error @endif"
    x-data="{
        types: @js($types),
        selectedId: '{{ $selectedTypeId }}',
        browsing: {{ $selectedTypeId !== '' ? 'false' : 'true' }},
        search: '',
        subgroupFilter: null,
        get filtered() {
            const term = this.search.trim().toLowerCase();

            return this.types.filter((t) => {
                const haystack = [t.family, t.reference, t.subgroup, t.application].filter(Boolean).join(' ').toLowerCase();
                const matchesSearch = term === '' || haystack.includes(term);
                const matchesSubgroup = this.subgroupFilter === null || t.subgroup === this.subgroupFilter;

                return matchesSearch && matchesSubgroup;
            });
        },
        get selectedType() {
            return this.types.find((t) => String(t.id) === this.selectedId) ?? null;
        },
        isVisible(id) {
            return this.filtered.some((t) => String(t.id) === String(id));
        },
    }"
>
    <template x-if="selectedType && !browsing">
        <div class="fieldops-luminaire-type-picker__selected" x-cloak>
            <span class="fieldops-luminaire-type-picker__selected-image">
                <img :src="selectedType.imageUrl" :alt="selectedType.family">
            </span>
            <span class="fieldops-luminaire-type-picker__selected-copy">
                <span class="fieldops-luminaire-type-picker__family" x-text="selectedType.family"></span>
                <span class="fieldops-luminaire-type-picker__reference" x-show="selectedType.reference" x-text="selectedType.reference"></span>
                <span class="fieldops-luminaire-type-picker__meta" x-show="selectedType.subgroup" x-text="selectedType.subgroup"></span>
            </span>
            <button type="button" class="fieldops-luminaire-type-picker__change-btn" @click="browsing = true">
                {{ __('fieldops::resource.luminaires.actions.change_type') }}
            </button>
        </div>
    </template>

    <div x-show="browsing || !selectedType" x-cloak>
        <div class="fieldops-luminaire-type-picker__toolbar">
            <input
                type="search"
                x-model="search"
                class="fieldops-luminaire-type-picker__search"
                placeholder="{{ __('fieldops::resource.luminaires.type_picker.search_placeholder') }}"
            >
            @if ($subgroups->isNotEmpty())
                <div class="fieldops-luminaire-type-picker__chips">
                    <button
                        type="button"
                        class="fieldops-luminaire-type-picker__chip"
                        :class="{ 'is-active': subgroupFilter === null }"
                        @click="subgroupFilter = null"
                    >{{ __('fieldops::resource.luminaires.type_picker.filter_all') }}</button>
                    @foreach ($subgroups as $subgroupLabel)
                        <button
                            type="button"
                            class="fieldops-luminaire-type-picker__chip"
                            :class="{ 'is-active': subgroupFilter === {{ \Illuminate\Support\Js::from($subgroupLabel) }} }"
                            @click="subgroupFilter = (subgroupFilter === {{ \Illuminate\Support\Js::from($subgroupLabel) }}) ? null : {{ \Illuminate\Support\Js::from($subgroupLabel) }}"
                        >{{ $subgroupLabel }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="fieldops-luminaire-type-picker" role="radiogroup" aria-label="{{ __('fieldops::resource.luminaires.fields.luminaire_type') }}" data-fieldops-luminaire-type-picker>
            @foreach ($types as $type)
                @php $inputId = $fieldId.'-type-'.$type['id']; @endphp
                <div class="fieldops-luminaire-type-picker__item" x-show="isVisible({{ (int) $type['id'] }})">
                    <input
                        id="{{ $inputId }}"
                        class="fieldops-luminaire-type-picker__radio"
                        type="radio"
                        name="{{ $statePath }}"
                        value="{{ $type['id'] }}"
                        wire:model.live="{{ $statePath }}"
                        @checked($selectedTypeId === (string) $type['id'])
                        @change="selectedId = '{{ $type['id'] }}'; browsing = false"
                    >
                    <label for="{{ $inputId }}" class="fieldops-luminaire-type-picker__card">
                        <span class="fieldops-luminaire-type-picker__image"><img src="{{ $type['imageUrl'] }}" alt="{{ $type['family'] }}"></span>
                        <span class="fieldops-luminaire-type-picker__copy">
                            <span class="fieldops-luminaire-type-picker__family">{{ $type['family'] }}</span>
                            @if ($type['reference'])<span class="fieldops-luminaire-type-picker__reference">{{ $type['reference'] }}</span>@endif
                            @if ($type['subgroup'])<span class="fieldops-luminaire-type-picker__meta">{{ $type['subgroup'] }}</span>@endif
                            @if ($type['application'])<span class="fieldops-luminaire-type-picker__meta">{{ \Illuminate\Support\Str::limit($type['application'], 100) }}</span>@endif
                        </span>
                    </label>
                </div>
            @endforeach
        </div>

        <p class="fieldops-luminaire-type-picker__empty" x-show="filtered.length === 0">
            {{ __('fieldops::resource.luminaires.type_picker.no_results') }}
        </p>
    </div>

    @if ($hasError)
        <p class="fieldops-luminaire-type-picker__error-message">{{ $errors->first($statePath) }}</p>
    @endif
</div>
@endif
