@php
    /** @var array<string, mixed> $overview */
    $overview = $getState();
    $hasOpenIssues = ($overview['openIssues'] ?? 0) > 0;
    $isCurrentInstallation = (bool) ($overview['isCurrent'] ?? true);
@endphp

@once
    <style>
        .fieldops-luminaire-overview { display:grid; gap:1rem; }
        .fieldops-luminaire-overview__hero,
        .fieldops-luminaire-overview__card { border:1px solid rgba(148,163,184,.18); border-radius:1.2rem; background:#fff; box-shadow:0 18px 44px rgba(15,23,42,.06); }
        .dark .fieldops-luminaire-overview__hero,
        .dark .fieldops-luminaire-overview__card { border-color:rgba(255,255,255,.08); background:linear-gradient(155deg,#171725,#11111a); box-shadow:0 18px 48px rgba(0,0,0,.22); }
        .fieldops-luminaire-overview__hero { overflow:hidden; display:grid; grid-template-columns:minmax(11rem,15rem) 1fr; background:radial-gradient(circle at top left,rgba(14,165,233,.13),transparent 38%),#fff; }
        .dark .fieldops-luminaire-overview__hero { background:radial-gradient(circle at top left,rgba(14,165,233,.18),transparent 38%),linear-gradient(145deg,#132337,#171725 58%); }
        .fieldops-luminaire-overview__media { min-height:15rem; display:grid; place-items:center; padding:1.4rem; background:rgba(15,23,42,.035); border-right:1px solid rgba(148,163,184,.14); }
        .dark .fieldops-luminaire-overview__media { background:rgba(2,6,23,.2); border-right-color:rgba(255,255,255,.08); }
        .fieldops-luminaire-overview__media img { width:100%; height:12rem; object-fit:contain; filter:drop-shadow(0 18px 24px rgba(2,6,23,.2)); }
        .fieldops-luminaire-overview__hero-body { display:flex; flex-direction:column; justify-content:center; gap:.9rem; padding:1.6rem; }
        .fieldops-luminaire-overview__eyebrow { color:#0ea5e9; font-size:.72rem; font-weight:800; letter-spacing:.16em; text-transform:uppercase; }
        .fieldops-luminaire-overview__title { color:#0f172a; font-size:clamp(1.65rem,3vw,2.45rem); font-weight:850; line-height:1.05; letter-spacing:-.035em; }
        .dark .fieldops-luminaire-overview__title { color:#f8fafc; }
        .fieldops-luminaire-overview__subtitle { color:#64748b; font-size:.95rem; }
        .dark .fieldops-luminaire-overview__subtitle { color:#a8b4c7; }
        .fieldops-luminaire-overview__status { display:inline-flex; align-items:center; gap:.5rem; width:max-content; padding:.48rem .75rem; border:1px solid rgba(34,197,94,.24); border-radius:999px; background:rgba(34,197,94,.1); color:#15803d; font-size:.82rem; font-weight:800; }
        .dark .fieldops-luminaire-overview__status { color:#86efac; }
        .fieldops-luminaire-overview__status--warning { border-color:rgba(245,158,11,.3); background:rgba(245,158,11,.12); color:#b45309; }
        .fieldops-luminaire-overview__status--retired { border-color:rgba(100,116,139,.3); background:rgba(100,116,139,.12); color:#475569; }
        .dark .fieldops-luminaire-overview__status--warning { color:#fcd34d; }
        .fieldops-luminaire-overview__status-dot { width:.5rem; height:.5rem; border-radius:999px; background:currentColor; box-shadow:0 0 0 4px color-mix(in srgb,currentColor 15%,transparent); }
        .fieldops-luminaire-overview__metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.65rem; }
        .fieldops-luminaire-overview__metric { padding:.75rem .85rem; border:1px solid rgba(148,163,184,.16); border-radius:.85rem; background:rgba(248,250,252,.72); }
        .dark .fieldops-luminaire-overview__metric { border-color:rgba(255,255,255,.07); background:rgba(255,255,255,.035); }
        .fieldops-luminaire-overview__metric-label { color:#64748b; font-size:.68rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .fieldops-luminaire-overview__metric-value { margin-top:.25rem; color:#0f172a; font-size:1rem; font-weight:800; overflow-wrap:anywhere; }
        .dark .fieldops-luminaire-overview__metric-value { color:#f1f5f9; }
        .fieldops-luminaire-overview__actions { display:flex; flex-wrap:wrap; gap:.6rem; }
        .fieldops-luminaire-overview__button { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; min-height:2.45rem; padding:.55rem .8rem; border:1px solid rgba(14,165,233,.3); border-radius:.8rem; color:#0369a1; background:rgba(14,165,233,.08); font-size:.82rem; font-weight:800; transition:transform .15s ease,background .15s ease; }
        .dark .fieldops-luminaire-overview__button { color:#7dd3fc; background:rgba(14,165,233,.1); }
        .fieldops-luminaire-overview__button:hover { transform:translateY(-1px); background:rgba(14,165,233,.15); }
        .fieldops-luminaire-overview__button--primary { color:#fff; border-color:#0284c7; background:#0284c7; }
        .dark .fieldops-luminaire-overview__button--primary { color:#fff; background:#0284c7; }
        .fieldops-luminaire-overview__grid { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(20rem,.85fr); gap:1rem; align-items:start; }
        .fieldops-luminaire-overview__card { overflow:hidden; }
        .fieldops-luminaire-overview__card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:1.1rem 1.2rem; border-bottom:1px solid rgba(148,163,184,.15); }
        .dark .fieldops-luminaire-overview__card-header { border-bottom-color:rgba(255,255,255,.07); }
        .fieldops-luminaire-overview__card-title { color:#0f172a; font-size:1rem; font-weight:850; }
        .dark .fieldops-luminaire-overview__card-title { color:#f8fafc; }
        .fieldops-luminaire-overview__card-copy { margin-top:.2rem; color:#64748b; font-size:.8rem; line-height:1.45; }
        .dark .fieldops-luminaire-overview__card-copy { color:#94a3b8; }
        .fieldops-luminaire-overview__maintenance { display:grid; }
        .fieldops-luminaire-overview__maintenance-row { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:.8rem; padding:.9rem 1.2rem; border-bottom:1px solid rgba(148,163,184,.12); }
        .dark .fieldops-luminaire-overview__maintenance-row { border-bottom-color:rgba(255,255,255,.06); }
        .fieldops-luminaire-overview__maintenance-row:last-child { border-bottom:0; }
        .fieldops-luminaire-overview__maintenance-row:hover { background:rgba(14,165,233,.045); }
        .fieldops-luminaire-overview__maintenance-icon { width:2.1rem; height:2.1rem; display:grid; place-items:center; border-radius:.7rem; color:#16a34a; background:rgba(34,197,94,.1); font-weight:900; }
        .fieldops-luminaire-overview__maintenance-icon--open { color:#d97706; background:rgba(245,158,11,.12); }
        .fieldops-luminaire-overview__maintenance-name { color:#0f172a; font-size:.88rem; font-weight:800; }
        .dark .fieldops-luminaire-overview__maintenance-name { color:#e2e8f0; }
        .fieldops-luminaire-overview__maintenance-meta { margin-top:.15rem; color:#64748b; font-size:.75rem; }
        .fieldops-luminaire-overview__pill { padding:.3rem .55rem; border-radius:999px; color:#15803d; background:rgba(34,197,94,.1); font-size:.68rem; font-weight:800; white-space:nowrap; }
        .fieldops-luminaire-overview__pill--open { color:#b45309; background:rgba(245,158,11,.13); }
        .dark .fieldops-luminaire-overview__pill { color:#86efac; }
        .dark .fieldops-luminaire-overview__pill--open { color:#fcd34d; }
        .fieldops-luminaire-overview__empty { padding:2rem 1.2rem; text-align:center; color:#64748b; font-size:.86rem; }
        .fieldops-luminaire-overview__frame-body { padding:1rem; }
        .fieldops-luminaire-overview__stage { position:relative; min-height:18rem; overflow:hidden; border:1px dashed rgba(148,163,184,.25); border-radius:1rem; background:#f1f5f9 center/contain no-repeat; }
        .dark .fieldops-luminaire-overview__stage { background-color:#111827; border-color:rgba(255,255,255,.12); }
        .fieldops-luminaire-overview__marker { position:absolute; transform:translate(-50%,-50%); display:grid; place-items:center; overflow:hidden; border:3px solid transparent; border-radius:.75rem; background:#0f172a; box-shadow:0 10px 25px rgba(2,6,23,.28); }
        .fieldops-luminaire-overview__marker--selected { border-color:#0ea5e9; box-shadow:0 0 0 4px rgba(14,165,233,.2),0 14px 30px rgba(2,6,23,.35); }
        .fieldops-luminaire-overview__marker img { width:100%; height:100%; object-fit:contain; }
        .fieldops-luminaire-overview__marker-label { position:absolute; top:2px; left:2px; padding:.1rem .3rem; border-radius:.3rem; color:#fff; background:rgba(15,23,42,.86); font:700 .63rem ui-monospace,monospace; }
        .fieldops-luminaire-overview__details { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; padding:1.1rem 1.2rem; }
        .fieldops-luminaire-overview__detail { min-width:0; padding:.85rem; border:1px solid rgba(148,163,184,.14); border-radius:.8rem; }
        .dark .fieldops-luminaire-overview__detail { border-color:rgba(255,255,255,.07); }
        .fieldops-luminaire-overview__detail-label { color:#64748b; font-size:.67rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .fieldops-luminaire-overview__detail-value { margin-top:.3rem; color:#0f172a; font-size:.86rem; font-weight:750; line-height:1.4; overflow-wrap:anywhere; }
        .dark .fieldops-luminaire-overview__detail-value { color:#e2e8f0; }
        .fieldops-luminaire-overview__technical summary { cursor:pointer; list-style:none; }
        .fieldops-luminaire-overview__technical summary::-webkit-details-marker { display:none; }
        .fieldops-luminaire-overview__timeline { display:grid; gap:.7rem; padding:1rem 1.2rem; }
        .fieldops-luminaire-overview__installation { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:.8rem; padding:.75rem .85rem; border:1px solid rgba(148,163,184,.14); border-radius:.8rem; }
        .fieldops-luminaire-overview__installation-index { display:grid; place-items:center; width:2rem; height:2rem; border-radius:.65rem; color:#0369a1; background:rgba(14,165,233,.1); font-size:.75rem; font-weight:900; }
        @media (max-width:1024px) { .fieldops-luminaire-overview__grid { grid-template-columns:1fr; } }
        @media (max-width:720px) { .fieldops-luminaire-overview__hero { grid-template-columns:1fr; } .fieldops-luminaire-overview__media { min-height:11rem; border-right:0; border-bottom:1px solid rgba(148,163,184,.14); } .fieldops-luminaire-overview__media img { height:9rem; } .fieldops-luminaire-overview__metrics,.fieldops-luminaire-overview__details { grid-template-columns:1fr; } }
    </style>
@endonce

<div class="fieldops-luminaire-overview" data-fieldops-luminaire-overview>
    <section class="fieldops-luminaire-overview__hero">
        <div class="fieldops-luminaire-overview__media">
            <img src="{{ $overview['imageUrl'] }}" alt="{{ $overview['productFamily'] }}">
        </div>
        <div class="fieldops-luminaire-overview__hero-body">
            <div>
                <div class="fieldops-luminaire-overview__eyebrow">{{ __('fieldops::resource.luminaires.detail.eyebrow') }}</div>
                <h2 class="fieldops-luminaire-overview__title">{{ $overview['productFamily'] }}</h2>
                <div class="fieldops-luminaire-overview__subtitle">
                    {{ collect([$overview['modelReference'], $overview['serial']])->filter()->join(' · ') }}
                </div>
            </div>

            <div class="fieldops-luminaire-overview__status {{ ! $isCurrentInstallation ? 'fieldops-luminaire-overview__status--retired' : ($hasOpenIssues ? 'fieldops-luminaire-overview__status--warning' : '') }}">
                <span class="fieldops-luminaire-overview__status-dot"></span>
                {{ ! $isCurrentInstallation
                    ? __('fieldops::resource.luminaires.replacement.retired')
                    : ($hasOpenIssues
                    ? trans_choice('fieldops::resource.luminaires.detail.open_issues', $overview['openIssues'], ['count' => $overview['openIssues']])
                    : __('fieldops::resource.luminaire_frames.view.no_open_issues')) }}
            </div>

            <div class="fieldops-luminaire-overview__metrics">
                <div class="fieldops-luminaire-overview__metric">
                    <div class="fieldops-luminaire-overview__metric-label">{{ __('fieldops::resource.luminaires.fields.frame') }}</div>
                    <div class="fieldops-luminaire-overview__metric-value">{{ $overview['frameTitle'] ?? '—' }}</div>
                </div>
                <div class="fieldops-luminaire-overview__metric">
                    <div class="fieldops-luminaire-overview__metric-label">{{ __('fieldops::resource.luminaires.fields.frame_position') }}</div>
                    <div class="fieldops-luminaire-overview__metric-value">{{ $overview['framePosition'] ? '#'.$overview['framePosition'] : '—' }}</div>
                </div>
                <div class="fieldops-luminaire-overview__metric">
                    <div class="fieldops-luminaire-overview__metric-label">{{ __('fieldops::resource.luminaires.detail.maintenance_total') }}</div>
                    <div class="fieldops-luminaire-overview__metric-value">{{ $overview['maintenanceTotal'] }}</div>
                </div>
            </div>

        </div>
    </section>

    <div class="fieldops-luminaire-overview__grid">
        <section class="fieldops-luminaire-overview__card">
            <div class="fieldops-luminaire-overview__card-header">
                <div>
                    <div class="fieldops-luminaire-overview__card-title">{{ __('fieldops::resource.luminaires.detail.maintenance_title') }}</div>
                    <div class="fieldops-luminaire-overview__card-copy">{{ __('fieldops::resource.luminaires.detail.maintenance_copy') }}</div>
                </div>
                <a href="{{ $overview['maintenanceIndexUrl'] }}" class="fieldops-luminaire-overview__button">
                    {{ __('fieldops::resource.luminaires.actions.view_history') }}
                </a>
            </div>

            @if (count($overview['maintenance']) > 0)
                <div class="fieldops-luminaire-overview__maintenance">
                    @foreach ($overview['maintenance'] as $maintenance)
                        <a href="{{ $maintenance['url'] }}" class="fieldops-luminaire-overview__maintenance-row">
                            <span class="fieldops-luminaire-overview__maintenance-icon {{ $maintenance['status'] === 'open' ? 'fieldops-luminaire-overview__maintenance-icon--open' : '' }}">
                                {{ $maintenance['status'] === 'open' ? '!' : '✓' }}
                            </span>
                            <span class="min-w-0">
                                <span class="fieldops-luminaire-overview__maintenance-name">{{ $maintenance['type'] ?: __('fieldops::resource.maintenance_records.model_label') }}</span>
                                <span class="fieldops-luminaire-overview__maintenance-meta block">{{ $maintenance['date'] ?: '—' }}@if($maintenance['summary']) · {{ \Illuminate\Support\Str::limit($maintenance['summary'], 80) }}@endif</span>
                            </span>
                            <span class="fieldops-luminaire-overview__pill {{ $maintenance['status'] === 'open' ? 'fieldops-luminaire-overview__pill--open' : '' }}">
                                {{ $maintenance['status'] === 'open' ? __('fieldops::resource.maintenance_records.status.in_progress') : __('fieldops::resource.maintenance_records.status.resolved') }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="fieldops-luminaire-overview__empty">
                    <p>{{ __('fieldops::resource.luminaires.no_maintenance') }}</p>
                    <a href="{{ $overview['maintenanceCreateUrl'] }}" class="fieldops-luminaire-overview__button mt-4">
                        {{ __('fieldops::resource.luminaires.actions.add_first_maintenance') }}
                    </a>
                </div>
            @endif
        </section>

        <section class="fieldops-luminaire-overview__card">
            <div class="fieldops-luminaire-overview__card-header">
                <div>
                    <div class="fieldops-luminaire-overview__card-title">{{ __('fieldops::resource.luminaires.where_it_sits') }}</div>
                    <div class="fieldops-luminaire-overview__card-copy">{{ __('fieldops::resource.luminaires.detail.frame_copy') }}</div>
                </div>
            </div>
            <div class="fieldops-luminaire-overview__frame-body">
                @if (count($overview['markers']) > 0)
                    <div class="fieldops-luminaire-overview__stage" @if($overview['frameImageUrl']) style="background-image:url('{{ $overview['frameImageUrl'] }}')" @endif>
                        @foreach ($overview['markers'] as $marker)
                            <a
                                href="{{ $marker['url'] }}"
                                class="fieldops-luminaire-overview__marker {{ $marker['selected'] ? 'fieldops-luminaire-overview__marker--selected' : '' }}"
                                style="left:{{ $marker['left'] }}%;top:{{ $marker['top'] }}%;width:{{ $marker['size'] }}px;height:{{ $marker['size'] }}px"
                                title="{{ $marker['serial'] ?: $marker['label'] }}"
                            >
                                <img src="{{ $marker['imageUrl'] }}" alt="">
                                <span class="fieldops-luminaire-overview__marker-label">{{ $marker['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="fieldops-luminaire-overview__empty">{{ __('fieldops::resource.luminaire_frames.no_luminaires') }}</div>
                @endif
                @if ($overview['frameUrl'])
                    <a href="{{ $overview['frameUrl'] }}" class="fieldops-luminaire-overview__button mt-4 w-full">
                        {{ __('fieldops::resource.luminaires.actions.open_technical_layout') }}
                    </a>
                @endif
            </div>
        </section>
    </div>

    @if (count($overview['installations'] ?? []) > 0)
        <section class="fieldops-luminaire-overview__card">
            <div class="fieldops-luminaire-overview__card-header">
                <div>
                    <div class="fieldops-luminaire-overview__card-title">{{ __('fieldops::resource.luminaires.replacement.history_title') }}</div>
                    <div class="fieldops-luminaire-overview__card-copy">{{ __('fieldops::resource.luminaires.replacement.history_copy') }}</div>
                </div>
            </div>
            <div class="fieldops-luminaire-overview__timeline">
                @foreach ($overview['installations'] as $index => $installation)
                    <div class="fieldops-luminaire-overview__installation">
                        <span class="fieldops-luminaire-overview__installation-index">{{ $index + 1 }}</span>
                        <span class="min-w-0">
                            <span class="fieldops-luminaire-overview__maintenance-name block">{{ collect([$installation['product'], $installation['serial']])->filter()->join(' · ') }}</span>
                            <span class="fieldops-luminaire-overview__maintenance-meta block">
                                {{ $installation['installedAt'] ?: '—' }} → {{ $installation['removedAt'] ?: __('fieldops::resource.luminaires.replacement.present') }}
                            </span>
                        </span>
                        <span class="fieldops-luminaire-overview__pill {{ $installation['current'] ? '' : 'fieldops-luminaire-overview__pill--open' }}">
                            {{ $installation['current'] ? __('fieldops::resource.luminaires.replacement.current') : __('fieldops::resource.luminaires.replacement.retired') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="fieldops-luminaire-overview__card">
        <div class="fieldops-luminaire-overview__card-header">
            <div>
                <div class="fieldops-luminaire-overview__card-title">{{ __('fieldops::resource.luminaires.detail.product_title') }}</div>
                <div class="fieldops-luminaire-overview__card-copy">{{ __('fieldops::resource.luminaires.detail.product_copy') }}</div>
            </div>
        </div>
        <div class="fieldops-luminaire-overview__details">
            <div class="fieldops-luminaire-overview__detail"><div class="fieldops-luminaire-overview__detail-label">{{ __('fieldops::resource.catalogs.fields.product_family') }}</div><div class="fieldops-luminaire-overview__detail-value">{{ $overview['productFamily'] ?: '—' }}</div></div>
            <div class="fieldops-luminaire-overview__detail"><div class="fieldops-luminaire-overview__detail-label">{{ __('fieldops::resource.catalogs.fields.model_reference') }}</div><div class="fieldops-luminaire-overview__detail-value">{{ $overview['modelReference'] ?: '—' }}</div></div>
            <div class="fieldops-luminaire-overview__detail"><div class="fieldops-luminaire-overview__detail-label">{{ __('fieldops::resource.luminaires.fields.subgroup') }}</div><div class="fieldops-luminaire-overview__detail-value">{{ $overview['subgroup'] ?: '—' }}</div></div>
            <div class="fieldops-luminaire-overview__detail"><div class="fieldops-luminaire-overview__detail-label">{{ __('fieldops::resource.catalogs.fields.typical_application') }}</div><div class="fieldops-luminaire-overview__detail-value">{{ $overview['typicalApplication'] ?: '—' }}</div></div>
            @if ($overview['info'])
                <div class="fieldops-luminaire-overview__detail" style="grid-column:1/-1"><div class="fieldops-luminaire-overview__detail-label">{{ __('fieldops::resource.luminaires.fields.info') }}</div><div class="fieldops-luminaire-overview__detail-value">{{ $overview['info'] }}</div></div>
            @endif
        </div>
    </section>

    <details class="fieldops-luminaire-overview__card fieldops-luminaire-overview__technical">
        <summary class="fieldops-luminaire-overview__card-header">
            <div>
                <div class="fieldops-luminaire-overview__card-title">{{ __('fieldops::resource.luminaires.sections.technical_placement') }}</div>
                <div class="fieldops-luminaire-overview__card-copy">{{ __('fieldops::resource.luminaires.detail.technical_copy') }}</div>
            </div>
            <span class="fieldops-luminaire-overview__button">{{ __('fieldops::resource.luminaires.detail.show_technical') }}</span>
        </summary>
        <div class="fieldops-luminaire-overview__details">
            <div class="fieldops-luminaire-overview__detail"><div class="fieldops-luminaire-overview__detail-label">{{ __('fieldops::resource.luminaires.detail.coordinates') }}</div><div class="fieldops-luminaire-overview__detail-value">{{ $overview['frameX'] !== null && $overview['frameY'] !== null ? 'X '.number_format($overview['frameX'], 4).' / Y '.number_format($overview['frameY'], 4) : '—' }}</div></div>
            <div class="fieldops-luminaire-overview__detail"><div class="fieldops-luminaire-overview__detail-label">{{ __('fieldops::resource.luminaires.detail.scale') }}</div><div class="fieldops-luminaire-overview__detail-value">X {{ $overview['scaleX'] ?? '—' }} / Y {{ $overview['scaleY'] ?? '—' }}</div></div>
            <div class="fieldops-luminaire-overview__detail"><div class="fieldops-luminaire-overview__detail-label">{{ __('fieldops::resource.luminaires.fields.position_source') }}</div><div class="fieldops-luminaire-overview__detail-value">{{ $overview['positionSource'] }}</div></div>
            <div class="fieldops-luminaire-overview__detail"><div class="fieldops-luminaire-overview__detail-label">{{ __('fieldops::resource.luminaires.fields.position_verified_at') }}</div><div class="fieldops-luminaire-overview__detail-value">{{ $overview['positionVerifiedAt'] ?: '—' }}</div></div>
        </div>
    </details>
</div>
