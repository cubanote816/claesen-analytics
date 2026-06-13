<x-filament-panels::page>
    {{-- Period selector --}}
    <div class="flex items-center gap-3 mb-6">
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ app()->getLocale() === 'nl' ? 'Periode:' : 'Period:' }}
        </label>
        <input
            type="month"
            value="{{ $this->period }}"
            wire:change="setPeriod($event.target.value)"
            class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm px-3 py-1.5 shadow-sm focus:ring-primary-500 focus:border-primary-500"
        />
        <span class="text-sm text-gray-500 dark:text-gray-400">— {{ $this->getPeriodLabel() }}</span>
    </div>

    @php
        $kpis   = $this->getKpis();
        $counts = $this->getTabCounts();
        $alerts = $this->getAlerts();
        $isNl   = app()->getLocale() === 'nl';

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

        // BI-2B-UX-01: NL status labels with workflow explanation tooltips
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

        // BI-2B-UX-01: contextual amount labels — what the number actually represents per alert type
        $amountLabels = [
            'missing_customer_invoice' => $isNl ? 'Gedetecteerde kost' : 'Detected cost',
            'project_billing_gap'      => $isNl ? 'Gedetecteerde kost' : 'Detected cost',
            'overdue_receivable'       => $isNl ? 'Open saldo'         : 'Open balance',
            'partial_payment'          => $isNl ? 'Open saldo'         : 'Open balance',
            'unbilled_followup_cost'   => $isNl ? 'Niet-gefact. kost'  : 'Unbilled cost',
            'closed_with_balance'      => $isNl ? 'Open saldo'         : 'Open balance',
            'credit_note'              => $isNl ? 'Creditbedrag'       : 'Credit amount',
        ];

        $typeLabels = [
            'missing_customer_invoice' => $isNl ? 'Ontbrekende factuur'    : 'Missing invoice',
            'overdue_receivable'       => $isNl ? 'Vervallen vordering'    : 'Overdue receivable',
            'partial_payment'          => $isNl ? 'Gedeeltelijke betaling' : 'Partial payment',
            'unbilled_followup_cost'   => $isNl ? 'Niet-gefactureerde kost': 'Unbilled cost',
            'project_billing_gap'      => $isNl ? 'Factuurkloof'           : 'Billing gap',
            'closed_with_balance'      => $isNl ? 'Gesloten met saldo'     : 'Closed with balance',
            'credit_note'              => $isNl ? 'Creditnota'             : 'Credit note',
            'monthly_close_blocker'    => $isNl ? 'Maandafsluiting'        : 'Month-close blocker',
        ];
    @endphp

    {{-- Monthly close blocker banner — BI-2B-UX-04: add direct link to receivables tab --}}
    @if($kpis['blocker'])
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 flex items-center gap-3">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-600 flex-shrink-0" />
            <span class="text-sm font-medium text-red-700 dark:text-red-400">
                {{ $isNl
                    ? 'Maandafsluiting geblokkeerd — er zijn nog kritieke of hoge facturatieafwijkingen onopgelost.'
                    : 'Monthly close blocked — critical or high billing anomalies remain unresolved.' }}
            </span>
            <button wire:click="setTab('receivables')"
                    class="ml-auto text-xs font-semibold text-red-700 dark:text-red-400 underline hover:no-underline whitespace-nowrap flex-shrink-0">
                {{ $isNl ? 'Bekijk kritieke meldingen →' : 'View critical alerts →' }}
            </button>
        </div>
    @endif

    {{-- KPI cards — BI-2B-UX-05: add sublabel and tooltip per card --}}
    @php
        $kpiCards = [
            [
                'label'   => $isNl ? 'Totaal'         : 'Total',
                'value'   => $kpis['total'],
                'sub'     => $isNl ? 'alle meldingen'     : 'all alerts',
                'tooltip' => $isNl ? 'Totaal aantal meldingen voor deze periode.'
                                   : 'Total alerts generated for this period.',
            ],
            [
                'label'   => 'Open',
                'value'   => $kpis['open'],
                'sub'     => $isNl ? 'nog niet bekeken'   : 'not yet reviewed',
                'tooltip' => $isNl ? 'Meldingen die nog door niemand zijn bekeken.'
                                   : 'Alerts not yet reviewed by anyone.',
            ],
            [
                'label'   => $isNl ? 'In behandeling' : 'In review',
                'value'   => $kpis['in_review'],
                'sub'     => $isNl ? 'wordt bekeken'      : 'being reviewed',
                'tooltip' => $isNl ? 'Meldingen die iemand actief bekijkt.'
                                   : 'Alerts being actively reviewed.',
            ],
            [
                'label'   => $isNl ? 'Bevestigd'      : 'Confirmed',
                'value'   => $kpis['confirmed'],
                'sub'     => $isNl ? 'actie vereist in CAFCA' : 'action required in CAFCA',
                'tooltip' => $isNl ? 'Bevestigde problemen — actie vereist in CAFCA, daarna klikken op Oplossen.'
                                   : 'Confirmed problems — action required in CAFCA, then click Resolve.',
            ],
            [
                'label'   => 'Kritiek',
                'value'   => $kpis['critical'],
                'sub'     => $isNl ? 'open / in behandeling' : 'open / in review',
                'tooltip' => $isNl ? 'Kritieke meldingen die nog niet zijn afgehandeld. Blokkeren de maandafsluiting.'
                                   : 'Critical alerts not yet handled. Block the monthly close.',
            ],
            [
                'label'   => $isNl ? 'Hoog'           : 'High',
                'value'   => $kpis['high'],
                'sub'     => $isNl ? 'open / in behandeling' : 'open / in review',
                'tooltip' => $isNl ? 'Hoge meldingen die nog niet zijn afgehandeld. Blokkeren de maandafsluiting.'
                                   : 'High alerts not yet handled. Block the monthly close.',
            ],
        ];
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @foreach($kpiCards as $kpi)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-center shadow-sm cursor-help"
                 title="{{ $kpi['tooltip'] }}">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $kpi['value'] }}</p>
                <p class="text-xs font-medium text-gray-600 dark:text-gray-300 mt-1">{{ $kpi['label'] }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 leading-tight">{{ $kpi['sub'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Tabs — BI-2B-UX-04: rename "System" → "Maandafsluiting" --}}
    @php
        $tabs = [
            'all'        => [$isNl ? 'Alle'            : 'All',         $counts['all']],
            'invoicing'  => [$isNl ? 'Facturatie'      : 'Invoicing',   $counts['invoicing']],
            'receivables'=> [$isNl ? 'Vorderingen'     : 'Receivables', $counts['receivables']],
            'costs'      => [$isNl ? 'Kosten'          : 'Costs',       $counts['costs']],
            'credits'    => [$isNl ? 'Creditnotas'     : 'Credits',     $counts['credits']],
            'system'     => [$isNl ? 'Maandafsluiting' : 'Month close', $counts['system']],
        ];
    @endphp

    <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
        <nav class="-mb-px flex gap-1 overflow-x-auto">
            @foreach($tabs as $key => [$label, $count])
                <button
                    wire:click="setTab('{{ $key }}')"
                    @class([
                        'px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors',
                        'border-primary-500 text-primary-600 dark:text-primary-400' => $this->activeTab === $key,
                        'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300' => $this->activeTab !== $key,
                    ])
                >
                    {{ $label }}
                    @if($count > 0)
                        <span class="ml-1.5 inline-flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ $count }}
                        </span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Alert table --}}
    @if($alerts->isEmpty())
        <div class="text-center py-12 text-gray-400 dark:text-gray-500">
            <x-heroicon-o-check-circle class="mx-auto w-10 h-10 mb-2" />
            <p>{{ $isNl ? 'Geen facturatieafwijkingen voor deze periode.' : 'No billing anomalies for this period.' }}</p>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Type'        : 'Type' }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Project'     : 'Project' }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Ernst'       : 'Severity' }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Status'      : 'Status' }}</th>
                        {{-- BI-2B-UX-01: "Bedrag ?" signals that the type varies per alert --}}
                        <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300 cursor-help"
                            title="{{ $isNl
                                ? 'Het bedrag varieert per meldingstype: gedetecteerde kost (Facturatie), open saldo (Vorderingen), of creditbedrag. Zie de label boven het bedrag.'
                                : 'Amount type varies per alert: detected cost (Invoicing), open balance (Receivables), or credit amount. See label above the figure.' }}">
                            {{ $isNl ? 'Bedrag ?' : 'Amount ?' }}
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Aanbeveling' : 'Recommendation' }}</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Acties'     : 'Actions' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($alerts as $alert)
                        <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-gray-600 dark:text-gray-400">
                                    {{ $typeLabels[$alert->alert_type] ?? $alert->alert_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-900 dark:text-white text-xs">
                                    {{ $alert->project_id ?? $alert->invoice_id ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span @class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', $severityColors[$alert->severity] ?? ''])>
                                    {{ ucfirst($alert->severity) }}
                                </span>
                            </td>
                            {{-- BI-2B-UX-01: NL label + workflow tooltip on every status badge --}}
                            <td class="px-4 py-3">
                                <span @class(['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium cursor-help', $statusColors[$alert->status] ?? ''])
                                      title="{{ $statusTooltips[$alert->status] ?? '' }}">
                                    {{ $statusLabels[$alert->status] ?? $alert->status }}
                                    @if($alert->status === 'confirmed')
                                        <span aria-hidden="true">&#9888;</span>
                                    @elseif($alert->status === 'resolved')
                                        <span aria-hidden="true">&#10003;</span>
                                    @endif
                                </span>
                            </td>
                            {{-- BI-2B-UX-01: contextual amount label above the figure --}}
                            <td class="px-4 py-3 text-right tabular-nums">
                                @if(isset($amountLabels[$alert->alert_type]))
                                    <span class="block text-xs text-gray-400 dark:text-gray-500 mb-0.5">
                                        {{ $amountLabels[$alert->alert_type] }}
                                    </span>
                                @endif
                                <span class="text-gray-700 dark:text-gray-300">
                                    @if($alert->amount_open !== null)
                                        €{{ number_format((float) $alert->amount_open, 2, ',', '.') }}
                                    @elseif($alert->amount_activity_cost !== null)
                                        €{{ number_format((float) $alert->amount_activity_cost, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </td>
                            {{-- BI-2B-UX-04: expandable recommendation — no more truncate --}}
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-sm">
                                @if(strlen($alert->recommendation ?? '') > 90)
                                    <div x-data="{ open: false }">
                                        <div :class="open ? '' : 'line-clamp-2 overflow-hidden'">{{ $alert->recommendation }}</div>
                                        <button @click.stop="open = !open"
                                                class="text-xs text-primary-600 dark:text-primary-400 hover:underline mt-0.5">
                                            <span x-show="!open">&#8595; {{ $isNl ? 'meer' : 'more' }}</span>
                                            <span x-show="open" style="display:none">&#8593; {{ $isNl ? 'minder' : 'less' }}</span>
                                        </button>
                                    </div>
                                @else
                                    {{ $alert->recommendation ?? '—' }}
                                @endif
                            </td>
                            {{-- BI-059 Workflow actions --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($alert->status === 'open')
                                    <button
                                        wire:click="markInReview({{ $alert->id }})"
                                        class="text-xs px-2 py-1 rounded bg-yellow-100 hover:bg-yellow-200 text-yellow-800 dark:bg-yellow-900/30 dark:hover:bg-yellow-900/50 dark:text-yellow-400 transition-colors"
                                    >Review</button>
                                @elseif($alert->status === 'in_review')
                                    <button
                                        wire:click="confirmAlert({{ $alert->id }})"
                                        class="text-xs px-2 py-1 rounded bg-purple-100 hover:bg-purple-200 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 mr-1 transition-colors"
                                    >{{ $isNl ? 'Bevestigen' : 'Confirm' }}</button>
                                    <button
                                        wire:click="dismissAlert({{ $alert->id }})"
                                        class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-400 transition-colors"
                                    >{{ $isNl ? 'Afwijzen' : 'Dismiss' }}</button>
                                @elseif(in_array($alert->status, ['confirmed', 'dismissed']))
                                    <button
                                        wire:click="resolveAlert({{ $alert->id }})"
                                        class="text-xs px-2 py-1 rounded bg-green-100 hover:bg-green-200 text-green-800 dark:bg-green-900/30 dark:text-green-400 mr-1 transition-colors"
                                    >{{ $isNl ? 'Oplossen' : 'Resolve' }}</button>
                                    @if($alert->status === 'dismissed')
                                        <button
                                            wire:click="reopenAlert({{ $alert->id }})"
                                            class="text-xs px-2 py-1 rounded bg-red-100 hover:bg-red-200 text-red-700 dark:bg-red-900/30 dark:text-red-400 transition-colors"
                                        >{{ $isNl ? 'Heropenen' : 'Reopen' }}</button>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 text-right">
            {{ $alerts->count() }} {{ $isNl ? 'melding(en) gevonden' : 'alert(s) found' }}
        </p>
    @endif
</x-filament-panels::page>
