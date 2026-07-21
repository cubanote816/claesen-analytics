@php
    /** @var array<int, array<string, mixed>> $types */
    $types = $getViewData()['types'] ?? [];
    $selectedTypeId = (string) ($getState() ?? '');
    $statePath = $getStatePath();
    $fieldId = $getId();
@endphp

@once
    <style>
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
        .fieldops-luminaire-type-picker__meta { margin-top:.35rem; color:#64748b; font-size:.72rem; line-height:1.35; }
        .dark .fieldops-luminaire-type-picker__meta { color:#94a3b8; }
        @media(max-width:1100px){ .fieldops-luminaire-type-picker{grid-template-columns:repeat(2,minmax(0,1fr));} }
        @media(max-width:700px){ .fieldops-luminaire-type-picker{grid-template-columns:1fr;} }
        @media(max-width:520px){ .fieldops-luminaire-type-picker__card{grid-template-columns:5.2rem 1fr;} }
    </style>
@endonce

<div class="fieldops-luminaire-type-picker" role="radiogroup" aria-label="{{ __('fieldops::resource.luminaires.fields.luminaire_type') }}" data-fieldops-luminaire-type-picker>
    @foreach ($types as $type)
        @php $inputId = $fieldId.'-type-'.$type['id']; @endphp
        <div class="fieldops-luminaire-type-picker__item">
            <input
                id="{{ $inputId }}"
                class="fieldops-luminaire-type-picker__radio"
                type="radio"
                name="{{ $statePath }}"
                value="{{ $type['id'] }}"
                wire:model.live="{{ $statePath }}"
                @checked($selectedTypeId === (string) $type['id'])
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
