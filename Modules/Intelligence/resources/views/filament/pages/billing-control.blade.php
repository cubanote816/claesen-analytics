<x-filament-panels::page>
    @php
        $isNl = app()->getLocale() === 'nl';

        // Section data (loaded once — all sections use the same project context)
        $billingAlerts = $this->getBillingAlerts();
        $overdueAlerts = $this->getOverdueAlerts();
        $closedAlerts  = $this->getClosedBalanceAlerts();
        $creditAlerts  = $this->getCreditNoteAlerts();
        $maand         = $this->getMaandafsluitingData();
        $periodLabel   = $this->getPeriodLabel();

        $ctx        = $this->getProjectContext();
        $projects   = $ctx['projects'];
        $relations  = $ctx['relations'];
        $insightSet = $ctx['insightSet'];

        $severityColors = [
            'critical' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            'high'     => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
            'medium'   => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
            'low'      => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        ];
        $statusColors = [
            'open'      => 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',
            'in_review' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
            'confirmed' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400',
            'dismissed' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
            'resolved'  => 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
        ];
        $statusLabels = [
            'open'      => $isNl ? 'Open'           : 'Open',
            'in_review' => $isNl ? 'In behandeling' : 'In review',
            'confirmed' => $isNl ? 'Bevestigd'      : 'Confirmed',
            'dismissed' => $isNl ? 'Afgewezen'      : 'Dismissed',
            'resolved'  => $isNl ? 'Opgelost'       : 'Resolved',
        ];
        $statusTooltips = [
            'open'      => $isNl ? 'Nog niet bekeken — klik Review om de melding te behandelen.'
                                 : 'Not yet reviewed — click Review to start.',
            'in_review' => $isNl ? 'Iemand bekijkt deze melding.'
                                 : 'Someone is reviewing this alert.',
            'confirmed' => $isNl ? 'Probleem bevestigd — actie vereist in CAFCA. Klik Oplossen zodra de actie is uitgevoerd.'
                                 : 'Problem confirmed — action required in CAFCA. Click Resolve once done.',
            'dismissed' => $isNl ? 'Afgewezen (vals alarm of al behandeld). Gebruik Heropenen als dit onjuist is.'
                                 : 'Dismissed (false positive or already handled). Use Reopen if incorrect.',
            'resolved'  => $isNl ? 'Afgesloten. Telt niet meer mee voor de maandafsluiting.'
                                 : 'Closed. No longer counted for monthly close.',
        ];
        $amountLabels = [
            'missing_customer_invoice' => $isNl ? 'Gedetecteerde kost' : 'Detected cost',
            'project_billing_gap'      => $isNl ? 'Gedetecteerde kost' : 'Detected cost',
            'overdue_receivable'       => $isNl ? 'Open saldo'         : 'Open balance',
            'unbilled_followup_cost'   => $isNl ? 'Niet-gefact. kost'  : 'Unbilled cost',
            'closed_with_balance'      => $isNl ? 'Open saldo'         : 'Open balance',
            'credit_note'              => $isNl ? 'Creditbedrag'       : 'Credit amount',
        ];
        $typeLabels = [
            'missing_customer_invoice' => $isNl ? 'Ontbrekende factuur'     : 'Missing invoice',
            'overdue_receivable'       => $isNl ? 'Vervallen vordering'     : 'Overdue receivable',
            'unbilled_followup_cost'   => $isNl ? 'Niet-gefactureerde kost' : 'Unbilled cost',
            'project_billing_gap'      => $isNl ? 'Factuurkloof'            : 'Billing gap',
            'closed_with_balance'      => $isNl ? 'Gesloten met saldo'      : 'Closed with balance',
            'credit_note'              => $isNl ? 'Creditnota'              : 'Credit note',
            'monthly_close_blocker'    => $isNl ? 'Maandafsluiting'         : 'Month-close blocker',
        ];

        // Overdue summary stats (computed from collection — no extra query)
        $overdueCount    = $overdueAlerts->count();
        $overdueCritical = $overdueAlerts->where('severity', 'critical')->count();
        $overdueHigh     = $overdueAlerts->where('severity', 'high')->count();
        $overdueTotal    = $overdueAlerts->sum('amount_open');
        $overdueMaxDays  = $overdueAlerts->max(fn($a) => ($a->evidence_json['days_overdue'] ?? 0));

        $canClose = !$maand['blocker']
                    && $maand['critical_open'] === 0
                    && $maand['high_open'] === 0
                    && $maand['confirmed_open'] === 0;
    @endphp

    {{-- ─── Period selector ─────────────────────────────────────────────── --}}
    <div class="flex items-center gap-3 mb-5">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $isNl ? 'Periode:' : 'Period:' }}
        </label>
        <input
            type="month"
            value="{{ $this->period }}"
            wire:change="setPeriod($event.target.value)"
            class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm px-3 py-1.5 shadow-sm focus:ring-primary-500 focus:border-primary-500"
        />
        <span class="text-sm text-gray-500 dark:text-gray-400">— {{ $periodLabel }}</span>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         1. MAANDSTATUS — executive answer: can I close the month?
         Replaces the separate blocker banner. Sits at the very top.
    ═══════════════════════════════════════════════════════════════════ --}}
    <div id="section-maand"
         class="mb-6 rounded-xl border p-5 {{ $canClose
             ? 'border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/10'
             : 'border-orange-200 dark:border-orange-700 bg-orange-50 dark:bg-orange-900/10' }}">

        <div class="flex items-start gap-3 mb-4">
            @if($canClose)
                <x-heroicon-o-check-badge class="w-6 h-6 text-green-500 flex-shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-semibold text-green-700 dark:text-green-400">
                        {{ $isNl ? "De maand {$periodLabel} kan worden afgesloten." : "Month {$periodLabel} can be closed." }}
                    </p>
                    <p class="text-xs text-green-600 dark:text-green-500 mt-0.5">
                        {{ $isNl ? 'Alle kritieke en hoge meldingen zijn afgehandeld.' : 'All critical and high alerts have been handled.' }}
                    </p>
                </div>
            @else
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-orange-500 flex-shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-semibold text-orange-700 dark:text-orange-400">
                        {{ $isNl ? "De maand {$periodLabel} kan nog niet worden afgesloten." : "Month {$periodLabel} cannot be closed yet." }}
                    </p>
                    <p class="text-xs text-orange-600 dark:text-orange-500 mt-0.5">
                        {{ $isNl ? 'Los de openstaande meldingen op voor u de maand afsluit in CAFCA.' : 'Resolve outstanding alerts before closing the month in CAFCA.' }}
                    </p>
                </div>
            @endif
        </div>

        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="text-center bg-white/60 dark:bg-gray-900/40 rounded-lg py-3">
                <p class="text-xl font-bold {{ $maand['critical_open'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-200' }}">
                    {{ $maand['critical_open'] }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Kritiek open' : 'Critical open' }}</p>
            </div>
            <div class="text-center bg-white/60 dark:bg-gray-900/40 rounded-lg py-3">
                <p class="text-xl font-bold {{ $maand['high_open'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-800 dark:text-gray-200' }}">
                    {{ $maand['high_open'] }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Hoog open' : 'High open' }}</p>
            </div>
            <div class="text-center bg-white/60 dark:bg-gray-900/40 rounded-lg py-3">
                <p class="text-xl font-bold {{ $maand['confirmed_open'] > 0 ? 'text-purple-600 dark:text-purple-400' : 'text-gray-800 dark:text-gray-200' }}">
                    {{ $maand['confirmed_open'] }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Bevestigd (actie)' : 'Confirmed (action)' }}</p>
            </div>
            <div class="text-center bg-white/60 dark:bg-gray-900/40 rounded-lg py-3">
                <p class="text-xl font-bold text-green-600 dark:text-green-400">
                    {{ $maand['resolved_period'] }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Opgelost' : 'Resolved' }}</p>
            </div>
        </dl>
    </div>

    {{-- ─── Quick navigation ──────────────────────────────────────────────── --}}
    <nav class="flex flex-wrap gap-x-4 gap-y-1 mb-6 text-xs font-medium text-gray-500 dark:text-gray-400">
        <a href="#section-billing" class="hover:text-primary-600 dark:hover:text-primary-400 hover:underline">
            &#9654; {{ $isNl ? 'Te factureren' : 'To invoice' }}
            @if($billingAlerts->isNotEmpty())
                <span class="ml-1 inline-flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs text-gray-700 dark:text-gray-300">{{ $billingAlerts->count() }}</span>
            @endif
        </a>
        <a href="#section-overdue" class="hover:text-primary-600 dark:hover:text-primary-400 hover:underline">
            &#9654; {{ $isNl ? 'Vervallen' : 'Overdue' }}
            @if($overdueCount > 0)
                <span class="ml-1 inline-flex items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 px-1.5 py-0.5 text-xs text-red-700 dark:text-red-400">{{ $overdueCount }}</span>
            @endif
        </a>
        <a href="#section-closed" class="hover:text-primary-600 dark:hover:text-primary-400 hover:underline">
            &#9654; {{ $isNl ? 'Afgesloten' : 'Closed' }}
            @if($closedAlerts->isNotEmpty())
                <span class="ml-1 inline-flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs text-gray-700 dark:text-gray-300">{{ $closedAlerts->count() }}</span>
            @endif
        </a>
        <a href="#section-credits" class="hover:text-primary-600 dark:hover:text-primary-400 hover:underline">
            &#9654; {{ $isNl ? "Creditnota's" : 'Credit notes' }}
            @if($creditAlerts->isNotEmpty())
                <span class="ml-1 inline-flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs text-gray-700 dark:text-gray-300">{{ $creditAlerts->count() }}</span>
            @endif
        </a>
    </nav>

    {{-- ═══════════════════════════════════════════════════════════════════
         2. NOG TE FACTUREREN — first operational section
    ═══════════════════════════════════════════════════════════════════ --}}
    <div id="section-billing" class="mb-8 scroll-mt-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $isNl ? "Nog te factureren voor {$periodLabel}" : "Still to invoice for {$periodLabel}" }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $isNl
                        ? 'Projecten met beweging in de periode waarvoor nog geen factuur is aangemaakt.'
                        : 'Projects with activity in the period where no invoice has been created yet.' }}
                </p>
            </div>
            @if($billingAlerts->isNotEmpty())
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $billingAlerts->count() }} {{ $isNl ? 'melding(en)' : 'alert(s)' }}
                </span>
            @endif
        </div>

        @if($billingAlerts->isEmpty())
            <div class="flex items-center gap-2 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 text-sm">
                <x-heroicon-o-check-circle class="w-4 h-4 flex-shrink-0" />
                {{ $isNl ? "Geen openstaande facturatiemeldingen voor {$periodLabel}." : "No outstanding billing alerts for {$periodLabel}." }}
            </div>
        @else
            @include('intelligence::filament.pages.billing-control-table', [
                'alerts'         => $billingAlerts,
                'projects'       => $projects,
                'relations'      => $relations,
                'insightSet'     => $insightSet,
                'severityColors' => $severityColors,
                'statusColors'   => $statusColors,
                'statusLabels'   => $statusLabels,
                'statusTooltips' => $statusTooltips,
                'amountLabels'   => $amountLabels,
                'typeLabels'     => $typeLabels,
                'isNl'           => $isNl,
                'showType'       => true,
            ])
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         3. VERVALLEN FACTUREN — all active, not period-filtered.
         Summary card + top 10 + "Toon alle" toggle.
    ═══════════════════════════════════════════════════════════════════ --}}
    <div id="section-overdue" class="mb-8 scroll-mt-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $isNl ? 'Vervallen facturen' : 'Overdue invoices' }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $isNl
                        ? 'Openstaande vervallen bedragen — niet beperkt tot de geselecteerde periode.'
                        : 'Outstanding overdue amounts — not limited to the selected period.' }}
                </p>
            </div>
            @if($overdueCount > 0)
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $overdueCount }} {{ $isNl ? 'melding(en)' : 'alert(s)' }}
                </span>
            @endif
        </div>

        @if($overdueCount === 0)
            <div class="flex items-center gap-2 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 text-sm">
                <x-heroicon-o-check-circle class="w-4 h-4 flex-shrink-0" />
                {{ $isNl ? 'Geen vervallen vorderingen.' : 'No overdue receivables.' }}
            </div>
        @else
            {{-- Summary stats bar --}}
            <div class="mb-3 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-lg bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 px-4 py-3 text-center">
                    <p class="text-lg font-bold text-red-700 dark:text-red-400">{{ $overdueCritical }}</p>
                    <p class="text-xs text-red-600 dark:text-red-500 mt-0.5">{{ $isNl ? 'Kritiek' : 'Critical' }}</p>
                </div>
                <div class="rounded-lg bg-orange-50 dark:bg-orange-900/10 border border-orange-200 dark:border-orange-800 px-4 py-3 text-center">
                    <p class="text-lg font-bold text-orange-700 dark:text-orange-400">{{ $overdueHigh }}</p>
                    <p class="text-xs text-orange-600 dark:text-orange-500 mt-0.5">{{ $isNl ? 'Hoog' : 'High' }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-3 text-center">
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-200 tabular-nums">{{ $overdueMaxDays }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Max. achterstand (dagen)' : 'Max. days overdue' }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-3 text-center">
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-200 tabular-nums">
                        €{{ number_format((float) $overdueTotal, 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $isNl ? 'Totaal open' : 'Total open' }}</p>
                </div>
            </div>

            <div class="mb-3 flex items-start gap-2 rounded-lg bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 px-4 py-2.5">
                <x-heroicon-o-information-circle class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
                <p class="text-xs text-blue-700 dark:text-blue-400">
                    {{ $isNl
                        ? 'Deze sectie is niet beperkt tot de gekozen maand. Ze toont alle openstaande vervallen bedragen die vandaag aandacht vragen.'
                        : 'This section is not limited to the selected month. It shows all outstanding overdue amounts requiring attention today.' }}
                </p>
            </div>

            @include('intelligence::filament.pages.billing-control-table', [
                'alerts'         => $overdueAlerts,
                'projects'       => $projects,
                'relations'      => $relations,
                'insightSet'     => $insightSet,
                'severityColors' => $severityColors,
                'statusColors'   => $statusColors,
                'statusLabels'   => $statusLabels,
                'statusTooltips' => $statusTooltips,
                'amountLabels'   => $amountLabels,
                'typeLabels'     => $typeLabels,
                'isNl'           => $isNl,
                'showType'       => false,
                'showLimit'      => 10,
            ])
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         4. AFGESLOTEN PROJECTEN MET OPEN SALDO — compact empty state
    ═══════════════════════════════════════════════════════════════════ --}}
    <div id="section-closed" class="mb-8 scroll-mt-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $isNl ? 'Afgesloten projecten met open saldo' : 'Closed projects with open balance' }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $isNl
                        ? 'Projecten gesloten in CAFCA maar met een openstaand saldo.'
                        : 'Projects closed in CAFCA that still carry an open balance.' }}
                </p>
            </div>
            @if($closedAlerts->isNotEmpty())
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $closedAlerts->count() }} {{ $isNl ? 'melding(en)' : 'alert(s)' }}
                </span>
            @endif
        </div>

        @if($closedAlerts->isEmpty())
            <div class="flex items-center gap-2 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 text-sm">
                <x-heroicon-o-check-circle class="w-4 h-4 flex-shrink-0" />
                {{ $isNl ? 'Geen afgesloten projecten met open saldo.' : 'No closed projects with open balance.' }}
            </div>
        @else
            @include('intelligence::filament.pages.billing-control-table', [
                'alerts'         => $closedAlerts,
                'projects'       => $projects,
                'relations'      => $relations,
                'insightSet'     => $insightSet,
                'severityColors' => $severityColors,
                'statusColors'   => $statusColors,
                'statusLabels'   => $statusLabels,
                'statusTooltips' => $statusTooltips,
                'amountLabels'   => $amountLabels,
                'typeLabels'     => $typeLabels,
                'isNl'           => $isNl,
                'showType'       => false,
            ])
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         5. CREDITNOTA'S — compact empty state
    ═══════════════════════════════════════════════════════════════════ --}}
    <div id="section-credits" class="mb-8 scroll-mt-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $isNl ? "Creditnota's in {$periodLabel}" : "Credit notes in {$periodLabel}" }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $isNl
                        ? "Creditnota's aangemaakt in de geselecteerde periode. Ter informatie."
                        : 'Credit notes created in the selected period. Informational.' }}
                </p>
            </div>
            @if($creditAlerts->isNotEmpty())
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $creditAlerts->count() }} {{ $isNl ? 'melding(en)' : 'alert(s)' }}
                </span>
            @endif
        </div>

        @if($creditAlerts->isEmpty())
            <div class="flex items-center gap-2 px-4 py-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 text-sm">
                <x-heroicon-o-minus-circle class="w-4 h-4 flex-shrink-0" />
                {{ $isNl ? "Geen creditnota's in {$periodLabel}." : "No credit notes in {$periodLabel}." }}
            </div>
        @else
            @include('intelligence::filament.pages.billing-control-table', [
                'alerts'         => $creditAlerts,
                'projects'       => $projects,
                'relations'      => $relations,
                'insightSet'     => $insightSet,
                'severityColors' => $severityColors,
                'statusColors'   => $statusColors,
                'statusLabels'   => $statusLabels,
                'statusTooltips' => $statusTooltips,
                'amountLabels'   => $amountLabels,
                'typeLabels'     => $typeLabels,
                'isNl'           => $isNl,
                'showType'       => false,
            ])
        @endif
    </div>

    {{-- Detail modal — unchanged --}}
    @if($this->selectedAlertId !== null)
        @php
            $md  = $this->getModalData();
            $ma  = $md['alert']      ?? null;
            $mp  = $md['project']    ?? null;
            $mr  = $md['relation']   ?? null;
            $mi  = $md['invoice']    ?? null;
            $mhl = $md['hasInsight'] ?? false;
        @endphp
        @if($ma)
        <div class="fixed inset-0 z-50 flex items-start sm:items-center justify-center p-4 pt-10 sm:pt-4"
             x-data
             @keydown.escape.window="$wire.closeModal()">
            <div class="fixed inset-0 bg-gray-900/60 dark:bg-gray-950/70"
                 wire:click="closeModal"></div>
            <div class="relative z-10 w-full max-w-2xl bg-white dark:bg-gray-900 rounded-xl shadow-2xl ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-900 dark:text-white text-sm">
                            {{ $typeLabels[$ma->alert_type] ?? $ma->alert_type }}
                        </span>
                        <span @class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', $severityColors[$ma->severity] ?? ''])>
                            {{ ucfirst($ma->severity) }}
                        </span>
                        <span @class(['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium cursor-help', $statusColors[$ma->status] ?? ''])
                              title="{{ $statusTooltips[$ma->status] ?? '' }}">
                            {{ $statusLabels[$ma->status] ?? $ma->status }}
                            @if($ma->status === 'confirmed')<span>&#9888;</span>@elseif($ma->status === 'resolved')<span>&#10003;</span>@endif
                        </span>
                    </div>
                    <button wire:click="closeModal"
                            class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 text-xl leading-none"
                            aria-label="{{ $isNl ? 'Sluiten' : 'Close' }}">&#10005;</button>
                </div>
                <div class="overflow-y-auto max-h-[72vh] px-6 py-5 space-y-5 text-sm">
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ $isNl ? 'Periode' : 'Period' }}:
                        {{ \Carbon\Carbon::create($ma->period_year, $ma->period_month, 1)->translatedFormat('F Y') }}
                    </p>
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">
                            {{ $isNl ? 'Project & klant' : 'Project & client' }}
                        </h3>
                        <dl class="space-y-2">
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Referentie' : 'Reference' }}</dt>
                                <dd class="text-xs font-mono text-gray-900 dark:text-white">
                                    {{ $ma->project_id ?? $ma->invoice_id ?? ($isNl ? 'Niet beschikbaar' : 'N/A') }}
                                </dd>
                            </div>
                            @if($mp?->name)
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Projectnaam' : 'Project name' }}</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300">{{ $mp->name }}</dd>
                            </div>
                            @endif
                            @if($mr?->name)
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Klant' : 'Client' }}</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300">{{ $mr->name }}</dd>
                            </div>
                            @endif
                        </dl>
                    </section>
                    @if($mi)
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">
                            {{ $isNl ? 'Factuur' : 'Invoice' }}
                        </h3>
                        <dl class="space-y-2">
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Factuurnr.' : 'Invoice no.' }}</dt>
                                <dd class="text-xs font-mono text-gray-900 dark:text-white">{{ $mi->id }}</dd>
                            </div>
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Totaal (incl. BTW)' : 'Total (incl. VAT)' }}</dt>
                                <dd class="text-xs tabular-nums text-gray-700 dark:text-gray-300">€{{ number_format((float)$mi->total_price, 2, ',', '.') }}</dd>
                            </div>
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Betaald' : 'Paid' }}</dt>
                                <dd class="text-xs tabular-nums text-gray-700 dark:text-gray-300">€{{ number_format((float)$mi->total_paid, 2, ',', '.') }}</dd>
                            </div>
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Open saldo' : 'Open balance' }}</dt>
                                <dd class="text-xs tabular-nums font-semibold text-gray-900 dark:text-white">
                                    €{{ number_format(max(0.0, (float)$mi->total_price - (float)$mi->total_paid), 2, ',', '.') }}
                                </dd>
                            </div>
                            @if($mi->date_expiration)
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Vervaldatum' : 'Due date' }}</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300">
                                    {{ $mi->date_expiration->format('d M Y') }}
                                    @if(!$mi->fl_paid && $mi->date_expiration->isPast())
                                        <span class="ml-1 text-xs text-red-600 dark:text-red-400 font-medium">
                                            ({{ (int)$mi->date_expiration->diffInDays(now()) }} {{ $isNl ? 'dagen achterstallig' : 'days overdue' }})
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            @endif
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Betaalstatus' : 'Payment status' }}</dt>
                                <dd class="text-xs">
                                    @if($mi->fl_paid)
                                        <span class="text-green-600 dark:text-green-400 font-medium">&#10003; {{ $isNl ? 'Betaald (CAFCA)' : 'Paid (CAFCA)' }}</span>
                                    @else
                                        <span class="text-red-600 dark:text-red-400 font-medium">&#9888; {{ $isNl ? 'Niet betaald (CAFCA)' : 'Not paid (CAFCA)' }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </section>
                    @endif
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">
                            {{ $isNl ? 'Bedrag' : 'Amount' }}
                        </h3>
                        @php
                            $modalAmountLabel = $amountLabels[$ma->alert_type] ?? ($isNl ? 'Bedrag' : 'Amount');
                            $modalAmountValue = $ma->amount_open ?? $ma->amount_activity_cost;
                            $isInvoicingType  = in_array($ma->alert_type, ['missing_customer_invoice', 'project_billing_gap', 'unbilled_followup_cost']);
                        @endphp
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">{{ $modalAmountLabel }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white tabular-nums">
                                {{ $modalAmountValue !== null ? '€' . number_format((float)$modalAmountValue, 2, ',', '.') : '—' }}
                            </p>
                            @if($ma->amount_estimated !== null)
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    {{ $isNl ? 'Contractprijs:' : 'Contract price:' }}
                                    €{{ number_format((float)$ma->amount_estimated, 2, ',', '.') }}
                                </p>
                            @endif
                        </div>
                        @if($isInvoicingType)
                            <p class="mt-2 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded px-3 py-2">
                                &#9888; {{ $isNl
                                    ? 'Gedetecteerde kost is geen automatisch factuurbedrag. Het te factureren bedrag bepaalt u zelf in CAFCA.'
                                    : 'Detected cost is not an automatic invoice amount. The billable amount is determined by you in CAFCA.' }}
                            </p>
                        @endif
                    </section>
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">
                            {{ $isNl ? 'Aanbeveling' : 'Recommendation' }}
                        </h3>
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            {{ $ma->recommendation ?? ($isNl ? 'Niet beschikbaar' : 'N/A') }}
                        </p>
                    </section>
                    @if(!empty($ma->evidence_json))
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">
                            {{ $isNl ? 'Bewijs (uit CAFCA)' : 'Evidence (from CAFCA)' }}
                        </h3>
                        @php
                            $evidenceLabels = [
                                'costs_in_month'          => $isNl ? 'Kosten in periode'    : 'Costs in period',
                                'hours_in_month'          => $isNl ? 'Uren in periode'      : 'Hours in period',
                                'workdocs_in_month'       => $isNl ? 'Werkdocumenten'       : 'Work documents',
                                'last_invoice_date'       => $isNl ? 'Laatste factuur'      : 'Last invoice',
                                'days_since_last_invoice' => $isNl ? 'Dagen zonder factuur' : 'Days without invoice',
                                'count_items'             => $isNl ? 'Kostenlijnen'         : 'Cost line items',
                                'total_amount'            => $isNl ? 'Totaal niet-gefact.'  : 'Total unbilled',
                                'cost_types'              => $isNl ? 'Kostentypes'          : 'Cost types',
                            ];
                            $evidenceMonetary = ['costs_in_month', 'total_amount'];
                            $evidenceHours    = ['hours_in_month'];
                            $ev               = $ma->evidence_json ?? [];
                        @endphp
                        <dl class="space-y-2">
                            @foreach($ev as $evKey => $evVal)
                                @if($evVal !== null && $evVal !== '' && $evVal !== [])
                                <div class="flex gap-3">
                                    <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $evidenceLabels[$evKey] ?? $evKey }}
                                    </dt>
                                    <dd class="text-xs text-gray-700 dark:text-gray-300">
                                        @if(is_array($evVal))
                                            {{ implode(', ', array_map('strval', $evVal)) }}
                                        @elseif(in_array($evKey, $evidenceMonetary))
                                            <span class="tabular-nums">€{{ number_format((float)$evVal, 2, ',', '.') }}</span>
                                        @elseif(in_array($evKey, $evidenceHours))
                                            <span class="tabular-nums">{{ number_format((float)$evVal, 2, ',', '.') }} h</span>
                                        @else
                                            {{ $evVal }}
                                        @endif
                                    </dd>
                                </div>
                                @endif
                            @endforeach
                        </dl>
                    </section>
                    @endif
                    @if($ma->project_id)
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Links</h3>
                        <div class="flex flex-col gap-2">
                            <a href="{{ \Modules\Intelligence\Filament\Pages\ProjectIntelligenceDetail::getProjectUrl(trim($ma->project_id)) }}"
                               target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                &#8599; {{ $isNl ? 'Projectdetails openen' : 'Open project details' }}
                            </a>
                            @if($mhl)
                            <a href="{{ \Modules\Performance\Filament\Resources\ProjectInsightResource::getUrl('view', ['record' => trim($ma->project_id)]) }}"
                               target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                &#8599; {{ $isNl ? 'Projectinzichten bekijken' : 'View project insights' }}
                            </a>
                            @endif
                        </div>
                    </section>
                    @endif
                    <section class="border-t border-gray-100 dark:border-gray-800 pt-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Audit</h3>
                        <dl class="space-y-2">
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Aangemaakt' : 'Created' }}</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300">{{ $ma->created_at?->format('d M Y H:i') ?? '—' }}</dd>
                            </div>
                            @if($ma->reviewed_at)
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Beoordeeld op' : 'Reviewed at' }}</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300">{{ $ma->reviewed_at->format('d M Y H:i') }}</dd>
                            </div>
                            @endif
                            @if($ma->resolved_at)
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Opgelost op' : 'Resolved at' }}</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300">{{ $ma->resolved_at->format('d M Y H:i') }}</dd>
                            </div>
                            @endif
                            @if($ma->resolution_notes)
                            <div class="flex gap-3">
                                <dt class="w-40 flex-shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Notities' : 'Notes' }}</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ $ma->resolution_notes }}</dd>
                            </div>
                            @endif
                        </dl>
                    </section>
                </div>
            </div>
        </div>
        @endif
    @endif

</x-filament-panels::page>
