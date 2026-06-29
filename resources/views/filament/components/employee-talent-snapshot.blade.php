@php
    /** @var \Modules\Cafca\Models\Employee $employee */
    $employee  = $getState();
    $insight   = $employee->insight;
    $isNl      = app()->getLocale() === 'nl';

    // Current assignment (last 15 days)
    try {
        $ids = \Modules\Performance\Models\Mirror\MirrorLabor::where('employee_id', $employee->id)
            ->where('date', '>=', now()->subDays(15))
            ->pluck('project_id')
            ->unique()
            ->filter();

        $assignment = $ids->isNotEmpty()
            ? (\Modules\Performance\Models\Mirror\MirrorProject::whereIn('id', $ids)->pluck('name')->implode(', ') ?: 'Standby')
            : 'Standby';
    } catch (\Throwable $e) {
        $assignment = 'Standby';
    }
    if (mb_strlen($assignment) > 36) {
        $assignment = mb_substr($assignment, 0, 33) . '…';
    }

    // Trend data
    try {
        $trendData = app(\Modules\Performance\Services\EmployeePerformanceService::class)->getShortTrend($employee);
    } catch (\Throwable $e) {
        $trendData = ['values' => [], 'momentum' => 0, 'period_label' => ''];
    }
    $values      = $trendData['values'] ?? [];
    $momentum    = $trendData['momentum'] ?? 0;
    $periodLabel = $trendData['period_label'] ?? '';

    // Burnout color
    $burnout = $insight?->burnout_risk_score;
    $burnoutColor = match(true) {
        $burnout === null  => '#6c757d',
        $burnout > 70      => '#e6007e',
        $burnout > 40      => '#fcd34d',
        default            => '#a5d610',
    };

    // Sparkline points
    $svgPts = [];
    if (count($values) > 1) {
        $max = max($values) ?: 1; $min = min($values); $range = ($max - $min) ?: 1;
        $w = 72; $h = 26; $p = 3;
        foreach ($values as $i => $v) {
            $x = ($i / (count($values) - 1)) * ($w - 2 * $p) + $p;
            $y = $h - (($v - $min) / $range) * ($h - 2 * $p) - $p;
            $svgPts[] = "$x,$y";
        }
    }
    $trendLineColor = $momentum >= 0 ? '#10b981' : '#f43f5e';

    $cardStyle = "background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07); border-radius: 12px;";
@endphp

<div class="emp-talent-snapshot grid grid-cols-2 lg:grid-cols-4 gap-3 mt-3">

    {{-- Talent Profile --}}
    <div class="px-4 py-4" style="{{ $cardStyle }}">
        <p class="text-[10px] font-bold uppercase tracking-widest mb-2" style="color: #495057">
            {{ $isNl ? 'Talent Profiel' : 'Talent Profile' }}
        </p>
        <p class="text-sm font-bold leading-snug" style="color: #26c7ff">
            @if($insight?->archetype_icon)
                {{ $insight->archetype_icon }}&nbsp;
            @endif
            {{ $insight?->archetype_label ?? ($isNl ? 'Wachten op AI…' : 'Pending AI…') }}
        </p>
    </div>

    {{-- Burnout Risk --}}
    <div class="px-4 py-4" style="{{ $cardStyle }}">
        <p class="text-[10px] font-bold uppercase tracking-widest mb-2" style="color: #495057">
            {{ $isNl ? 'Burnout Risico' : 'Burnout Risk' }}
        </p>
        <p class="text-sm font-bold tabular-nums" style="color: {{ $burnoutColor }}">
            {{ $burnout !== null ? $burnout . '%' : '—' }}
        </p>
    </div>

    {{-- Current Assignment --}}
    <div class="px-4 py-4" style="{{ $cardStyle }}">
        <p class="text-[10px] font-bold uppercase tracking-widest mb-2" style="color: #495057">
            {{ $isNl ? 'Huidige Opdracht' : 'Current Assignment' }}
        </p>
        <div class="flex items-start gap-1.5">
            <x-heroicon-m-briefcase class="w-3.5 h-3.5 mt-px shrink-0" style="color: #495057" />
            <p class="text-sm font-medium leading-snug" style="color: #dee2e6">{{ $assignment }}</p>
        </div>
    </div>

    {{-- Trend --}}
    <div class="px-4 py-4" style="{{ $cardStyle }}">
        <p class="text-[10px] font-bold uppercase tracking-widest mb-2" style="color: #495057">
            {{ $isNl ? 'Trend (Vorig p.)' : 'Trend (Prev. Period)' }}
        </p>
        @if(count($svgPts) > 1)
            <div class="flex items-center gap-2.5">
                <svg viewBox="0 0 72 26" style="width:72px;height:26px;overflow:visible;flex-shrink:0">
                    <polyline
                        points="{{ implode(' ', $svgPts) }}"
                        fill="none"
                        stroke="{{ $trendLineColor }}"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round" />
                    @php $last = explode(',', end($svgPts)); @endphp
                    <circle cx="{{ $last[0] }}" cy="{{ $last[1] }}" r="2.5"
                            fill="{{ $trendLineColor }}" stroke="#14141b" stroke-width="1.5" />
                </svg>
                <span class="text-xs font-bold tabular-nums" style="color: {{ $trendLineColor }}">
                    {{ $momentum >= 0 ? '+' : '' }}{{ number_format(abs($momentum), 0) }}%
                </span>
            </div>
        @else
            <p class="text-sm" style="color: #495057">—</p>
        @endif
    </div>

</div>
