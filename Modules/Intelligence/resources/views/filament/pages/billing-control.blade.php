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
        $typeLabels = [
            'missing_customer_invoice' => app()->getLocale() === 'nl' ? 'Ontbrekende factuur'    : 'Missing invoice',
            'overdue_receivable'       => app()->getLocale() === 'nl' ? 'Vervallen vordering'    : 'Overdue receivable',
            'partial_payment'          => app()->getLocale() === 'nl' ? 'Gedeeltelijke betaling' : 'Partial payment',
            'unbilled_followup_cost'   => app()->getLocale() === 'nl' ? 'Niet-gefactureerde kost': 'Unbilled cost',
            'project_billing_gap'      => app()->getLocale() === 'nl' ? 'Factuurkloof'           : 'Billing gap',
            'closed_with_balance'      => app()->getLocale() === 'nl' ? 'Gesloten met saldo'     : 'Closed with balance',
            'credit_note'              => app()->getLocale() === 'nl' ? 'Creditnota'             : 'Credit note',
            'monthly_close_blocker'    => app()->getLocale() === 'nl' ? 'Maandafsluiting'        : 'Month-close blocker',
        ];
    @endphp

    {{-- Monthly close blocker banner --}}
    @if($kpis['blocker'])
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 flex items-center gap-3">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-600 flex-shrink-0" />
            <span class="text-sm font-medium text-red-700 dark:text-red-400">
                {{ app()->getLocale() === 'nl'
                    ? 'Maandafsluiting geblokkeerd — er zijn nog kritieke of hoge facturatieafwijkingen onopgelost.'
                    : 'Monthly close blocked — critical or high billing anomalies remain unresolved.' }}
            </span>
        </div>
    @endif

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @foreach([
            ['label' => app()->getLocale() === 'nl' ? 'Totaal' : 'Total',       'value' => $kpis['total'],     'color' => 'gray'],
            ['label' => app()->getLocale() === 'nl' ? 'Open'   : 'Open',         'value' => $kpis['open'],      'color' => 'red'],
            ['label' => app()->getLocale() === 'nl' ? 'Review' : 'Review',       'value' => $kpis['in_review'], 'color' => 'yellow'],
            ['label' => app()->getLocale() === 'nl' ? 'Bevestigd' : 'Confirmed', 'value' => $kpis['confirmed'], 'color' => 'purple'],
            ['label' => 'Kritiek',                                                'value' => $kpis['critical'],  'color' => 'red'],
            ['label' => app()->getLocale() === 'nl' ? 'Hoog'  : 'High',          'value' => $kpis['high'],      'color' => 'orange'],
        ] as $kpi)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $kpi['value'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $kpi['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Tabs --}}
    @php
        $tabs = [
            'all'        => [app()->getLocale() === 'nl' ? 'Alle'         : 'All',         $counts['all']],
            'invoicing'  => [app()->getLocale() === 'nl' ? 'Facturatie'   : 'Invoicing',   $counts['invoicing']],
            'receivables'=> [app()->getLocale() === 'nl' ? 'Vorderingen'  : 'Receivables', $counts['receivables']],
            'costs'      => [app()->getLocale() === 'nl' ? 'Kosten'       : 'Costs',       $counts['costs']],
            'credits'    => [app()->getLocale() === 'nl' ? 'Creditnotas'  : 'Credits',     $counts['credits']],
            'system'     => ['System',                                                       $counts['system']],
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
            <p>{{ app()->getLocale() === 'nl' ? 'Geen facturatieafwijkingen voor deze periode.' : 'No billing anomalies for this period.' }}</p>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ app()->getLocale() === 'nl' ? 'Type'     : 'Type' }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ app()->getLocale() === 'nl' ? 'Project'  : 'Project' }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ app()->getLocale() === 'nl' ? 'Ernst'    : 'Severity' }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ app()->getLocale() === 'nl' ? 'Status'   : 'Status' }}</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">{{ app()->getLocale() === 'nl' ? 'Bedrag'   : 'Amount' }}</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ app()->getLocale() === 'nl' ? 'Aanbeveling' : 'Recommendation' }}</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">{{ app()->getLocale() === 'nl' ? 'Acties' : 'Actions' }}</th>
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
                            <td class="px-4 py-3">
                                <span @class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', $statusColors[$alert->status] ?? ''])>
                                    {{ $alert->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                @if($alert->amount_open !== null)
                                    €{{ number_format((float) $alert->amount_open, 2, ',', '.') }}
                                @elseif($alert->amount_activity_cost !== null)
                                    €{{ number_format((float) $alert->amount_activity_cost, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-xs truncate" title="{{ $alert->recommendation }}">
                                {{ $alert->recommendation }}
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
                                    >{{ app()->getLocale() === 'nl' ? 'Bevestigen' : 'Confirm' }}</button>
                                    <button
                                        wire:click="dismissAlert({{ $alert->id }})"
                                        class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-400 transition-colors"
                                    >{{ app()->getLocale() === 'nl' ? 'Afwijzen' : 'Dismiss' }}</button>
                                @elseif(in_array($alert->status, ['confirmed', 'dismissed']))
                                    <button
                                        wire:click="resolveAlert({{ $alert->id }})"
                                        class="text-xs px-2 py-1 rounded bg-green-100 hover:bg-green-200 text-green-800 dark:bg-green-900/30 dark:text-green-400 mr-1 transition-colors"
                                    >{{ app()->getLocale() === 'nl' ? 'Oplossen' : 'Resolve' }}</button>
                                    @if($alert->status === 'dismissed')
                                        <button
                                            wire:click="reopenAlert({{ $alert->id }})"
                                            class="text-xs px-2 py-1 rounded bg-red-100 hover:bg-red-200 text-red-700 dark:bg-red-900/30 dark:text-red-400 transition-colors"
                                        >{{ app()->getLocale() === 'nl' ? 'Heropenen' : 'Reopen' }}</button>
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
            {{ $alerts->count() }} {{ app()->getLocale() === 'nl' ? 'alert(s) gevonden' : 'alert(s) found' }}
        </p>
    @endif
</x-filament-panels::page>
