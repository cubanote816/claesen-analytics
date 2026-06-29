@php
    /** @var \Modules\Cafca\Models\Employee $employee */
    $employee = $getState();
    $insight  = $employee->insight;
    $isNl     = app()->getLocale() === 'nl';

    $burnout = $insight?->burnout_risk_score;
    $burnoutColor = match(true) {
        $burnout === null => '#6c757d',
        $burnout > 70     => '#e6007e',
        $burnout > 40     => '#fcd34d',
        default           => '#a5d610',
    };
    $burnoutBg = match(true) {
        $burnout === null => 'rgba(108,117,125,.1)',
        $burnout > 70     => 'rgba(230,0,126,.1)',
        $burnout > 40     => 'rgba(252,211,77,.1)',
        default           => 'rgba(165,214,16,.1)',
    };

    $trend = $insight?->efficiency_trend;
    $trendMeta = match($trend) {
        'increasing' => ['label' => $isNl ? 'Stijgend' : 'Increasing', 'icon' => '↑', 'color' => '#a5d610', 'bg' => 'rgba(165,214,16,.1)', 'border' => 'rgba(165,214,16,.2)'],
        'decreasing' => ['label' => $isNl ? 'Dalend'   : 'Decreasing', 'icon' => '↓', 'color' => '#e6007e', 'bg' => 'rgba(230,0,126,.1)',  'border' => 'rgba(230,0,126,.2)'],
        default      => ['label' => $isNl ? 'Stabiel'  : 'Stable',     'icon' => '→', 'color' => '#adb5bd', 'bg' => 'rgba(173,181,189,.08)','border' => 'rgba(173,181,189,.15)'],
    };
@endphp

<div class="emp-ai-insights rounded-2xl overflow-hidden"
     style="background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.07)">

    {{-- Header --}}
    <div class="flex items-center gap-2.5 px-5 py-3.5"
         style="border-bottom: 1px solid rgba(255,255,255,.06)">
        <x-heroicon-m-sparkles class="w-4 h-4 shrink-0" style="color: #26c7ff" />
        <h3 class="text-sm font-bold text-white tracking-tight">
            {{ $isNl ? 'AI Profiel &amp; Aanbevelingen' : 'AI Profile &amp; Insights' }}
        </h3>
        @if($insight?->last_audited_at)
            <span class="ml-auto text-[11px]" style="color: #495057">
                {{ $isNl ? 'Analyse' : 'Analyzed' }} {{ $insight->last_audited_at->format('d M Y · H:i') }}
            </span>
        @endif
    </div>

    @if($insight)
        {{-- Metric row --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-px"
             style="background: rgba(255,255,255,.04)">

            {{-- Archetype --}}
            <div class="px-5 py-4" style="background: #14141b">
                <p class="text-[10px] font-bold uppercase tracking-widest mb-2" style="color: #495057">
                    {{ $isNl ? 'Archetype' : 'Archetype' }}
                </p>
                <p class="text-base font-bold leading-tight" style="color: #26c7ff">
                    @if($insight->archetype_icon) {{ $insight->archetype_icon }}&nbsp; @endif
                    {{ $insight->archetype_label ?? '—' }}
                </p>
                @if($trend)
                    <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 rounded-full text-[11px] font-bold"
                          style="background: {{ $trendMeta['bg'] }}; border: 1px solid {{ $trendMeta['border'] }}; color: {{ $trendMeta['color'] }}">
                        {{ $trendMeta['icon'] }} {{ $trendMeta['label'] }}
                    </span>
                @endif
            </div>

            {{-- Burnout Risk --}}
            <div class="px-5 py-4 flex items-center gap-4" style="background: #14141b">
                <div class="flex flex-col items-center justify-center rounded-xl px-5 py-3 shrink-0"
                     style="background: {{ $burnoutBg }}; border: 1px solid {{ $burnoutColor }}22">
                    <span class="text-2xl font-black tabular-nums" style="color: {{ $burnoutColor }}">
                        {{ $burnout ?? '—' }}@if($burnout !== null)%@endif
                    </span>
                    <span class="text-[9px] font-bold uppercase tracking-widest mt-0.5" style="color: #495057">
                        {{ $isNl ? 'Burnout' : 'Burnout' }}
                    </span>
                </div>
            </div>

            {{-- Last audited --}}
            <div class="px-5 py-4" style="background: #14141b">
                <p class="text-[10px] font-bold uppercase tracking-widest mb-2" style="color: #495057">
                    {{ $isNl ? 'Laatste Analyse' : 'Last Analysis' }}
                </p>
                @if($insight->last_audited_at)
                    <div class="flex items-center gap-1.5">
                        <x-heroicon-m-clock class="w-4 h-4 shrink-0" style="color: #495057" />
                        <span class="text-sm font-medium" style="color: #dee2e6">
                            {{ $insight->last_audited_at->format('d M Y') }}
                        </span>
                    </div>
                    <p class="text-xs mt-1" style="color: #495057">
                        {{ $insight->last_audited_at->format('H:i') }} · {{ $insight->last_audited_at->diffForHumans() }}
                    </p>
                @else
                    <p class="text-sm" style="color: #495057">—</p>
                @endif
            </div>
        </div>

        {{-- Manager recommendation --}}
        @if($insight->manager_insight)
            <div class="mx-5 my-4 px-4 py-3.5 rounded-xl"
                 style="background: rgba(0,174,239,.05); border: 1px solid rgba(0,174,239,.15); border-left: 3px solid rgba(0,174,239,.5)">
                <p class="text-[10px] font-bold uppercase tracking-widest mb-2" style="color: #26c7ff">
                    {{ $isNl ? 'Manager Aanbeveling' : 'Manager Recommendation' }}
                </p>
                <p class="text-sm leading-relaxed italic" style="color: #dee2e6">
                    {{ $insight->manager_insight }}
                </p>
            </div>
        @endif

    @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center gap-3 px-8 py-10 text-center">
            <x-heroicon-o-cpu-chip class="w-10 h-10" style="color: #343a40" />
            <div>
                <p class="text-sm font-medium" style="color: #6c757d">
                    {{ $isNl ? 'Nog geen AI-analyse beschikbaar' : 'No AI analysis available yet' }}
                </p>
                <p class="text-xs mt-1" style="color: #495057">
                    {{ $isNl ? 'Klik op "IA Analyse Herberekenen" om te starten.' : 'Click "Recalculate AI Analysis" to start.' }}
                </p>
            </div>
        </div>
    @endif
</div>
