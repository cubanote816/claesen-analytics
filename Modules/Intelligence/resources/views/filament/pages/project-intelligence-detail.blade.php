<x-filament-panels::page>
    @php
        $d = $this->getPageData();
        extract($d);

        $isNl = app()->getLocale() === 'nl';

        $typeLabels = [
            'M' => $isNl ? 'Materiaal'       : 'Material',
            'A' => $isNl ? 'Arbeid'           : 'Labour',
            'K' => $isNl ? 'Materieel'        : 'Equipment',
            'O' => $isNl ? 'Onderaanneming'   : 'Subcontract',
            'T' => $isNl ? 'Transport'        : 'Transport',
            'E' => $isNl ? 'Extra'            : 'Extra',
        ];

        $alertTypeLabels = [
            'missing_customer_invoice' => $isNl ? 'Ontbrekende klantfactuur'  : 'Missing customer invoice',
            'project_billing_gap'      => $isNl ? 'Factureringskloof'         : 'Billing gap',
            'overdue_receivable'       => $isNl ? 'Achterstallige vordering'   : 'Overdue receivable',
            'partial_payment'          => $isNl ? 'Gedeeltelijke betaling'     : 'Partial payment',
            'unbilled_followup_cost'   => $isNl ? 'Niet-gefactureerde kost'    : 'Unbilled cost',
            'closed_with_balance'      => $isNl ? 'Afgesloten met saldo'       : 'Closed with balance',
            'credit_note'              => $isNl ? 'Creditnota'                 : 'Credit note',
            'monthly_close_blocker'    => $isNl ? 'Maandafsluiting blokkade'   : 'Month-close blocker',
        ];

        $statusLabels = [
            'open'       => $isNl ? 'Open'          : 'Open',
            'in_review'  => $isNl ? 'In beoordeling': 'In review',
            'confirmed'  => $isNl ? 'Bevestigd'     : 'Confirmed',
            'dismissed'  => $isNl ? 'Afgewezen'     : 'Dismissed',
            'resolved'   => $isNl ? 'Opgelost'      : 'Resolved',
        ];

        $statusColors = [
            'open'      => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            'in_review' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            'confirmed' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
            'dismissed' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
            'resolved'  => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        ];

        // amount: prefer amount_open (receivables), fall back to amount_activity_cost (costs)
        $alertAmount = fn($alert) => $alert->amount_open ?? $alert->amount_activity_cost;

        // Contextual label so the user knows what the number represents (matching billing-control.blade.php)
        $amountLabel = [
            'missing_customer_invoice' => $isNl ? 'Gedetecteerde kost' : 'Detected cost',
            'project_billing_gap'      => $isNl ? 'Gedetecteerde kost' : 'Detected cost',
            'unbilled_followup_cost'   => $isNl ? 'Gedetecteerde kost' : 'Detected cost',
            'overdue_receivable'       => $isNl ? 'Open saldo'         : 'Open balance',
            'partial_payment'          => $isNl ? 'Open saldo'         : 'Open balance',
            'closed_with_balance'      => $isNl ? 'Open saldo'         : 'Open balance',
            'credit_note'              => $isNl ? 'Creditbedrag'       : 'Credit amount',
        ];

        $severityColors = [
            'critical' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
            'high'     => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
            'medium'   => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
            'low'      => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        ];

        $fmt = fn($n) => '€ ' . number_format((float) $n, 2, ',', '.');
    @endphp

    {{-- Back link --}}
    <div class="mb-2">
        <a href="javascript:history.back()"
           class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 dark:hover:text-gray-200">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            {{ $isNl ? 'Terug' : 'Back' }}
        </a>
    </div>

    {{-- Project header --}}
    <x-filament::section>
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-mono text-gray-400">{{ $project->id }}</span>
                    @if($project->fl_active)
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                            {{ $isNl ? 'Actief' : 'Active' }}
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            {{ $isNl ? 'Afgesloten' : 'Closed' }}
                        </span>
                    @endif
                </div>
                <h2 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ $project->name }}
                </h2>
                @if($relation)
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ $relation->name }}
                        @if($relation->city) · {{ $relation->city }} @endif
                    </p>
                @endif
            </div>

            <dl class="grid grid-cols-2 gap-x-8 gap-y-1 text-sm shrink-0">
                @if($project->contract_price)
                    <dt class="text-gray-500">{{ $isNl ? 'Contractprijs' : 'Contract price' }}</dt>
                    <dd class="font-mono text-right">{{ $fmt($project->contract_price) }}</dd>
                @endif
                @if($project->type)
                    <dt class="text-gray-500">{{ $isNl ? 'Type' : 'Type' }}</dt>
                    <dd class="text-right">{{ $project->type }}</dd>
                @endif
                @if($project->state)
                    <dt class="text-gray-500">{{ $isNl ? 'Status' : 'Status' }}</dt>
                    <dd class="text-right">{{ $project->state }}</dd>
                @endif
            </dl>
        </div>
    </x-filament::section>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Gefactureerd' : 'Invoiced' }}</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white font-mono">{{ $fmt($totalInvoiced) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Ontvangen' : 'Received' }}</p>
            <p class="mt-1 text-lg font-semibold text-green-700 dark:text-green-400 font-mono">{{ $fmt($totalPaid) }}</p>
        </div>
        <div class="rounded-xl border {{ $openBalance > 0 ? 'border-orange-300 dark:border-orange-700' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-900 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Open saldo' : 'Open balance' }}</p>
            <p class="mt-1 text-lg font-semibold {{ $openBalance > 0 ? 'text-orange-700 dark:text-orange-400' : 'text-gray-900 dark:text-white' }} font-mono">{{ $fmt($openBalance) }}</p>
            @if($overdueCount > 0)
                <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">{{ $overdueCount }} {{ $isNl ? 'vervallen' : 'overdue' }}</p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Totale kosten' : 'Total costs' }}</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white font-mono">{{ $fmt($totalCost) }}</p>
        </div>
        <div class="rounded-xl border {{ $unbilledCost > 0 ? 'border-red-300 dark:border-red-700' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-900 p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isNl ? 'Niet-gefact. kost' : 'Unbilled cost' }}</p>
            <p class="mt-1 text-lg font-semibold {{ $unbilledCost > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-900 dark:text-white' }} font-mono">{{ $fmt($unbilledCost) }}</p>
        </div>
    </div>

    {{-- Facturen --}}
    <x-filament::section>
        <x-slot name="heading">
            {{ $isNl ? 'Facturen' : 'Invoices' }}
            <span class="ml-2 text-xs font-normal text-gray-400">({{ $invoices->count() }})</span>
        </x-slot>

        @if($invoices->isEmpty())
            <p class="text-sm text-gray-400 italic">{{ $isNl ? 'Geen facturen gevonden.' : 'No invoices found.' }}</p>
        @else
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400">ID</th>
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400">{{ $isNl ? 'Datum' : 'Date' }}</th>
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400 text-right">{{ $isNl ? 'Bedrag excl. btw' : 'Amount excl. VAT' }}</th>
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400">{{ $isNl ? 'Vervaldatum' : 'Due date' }}</th>
                            <th class="pb-2 font-medium text-gray-500 dark:text-gray-400">{{ $isNl ? 'Status' : 'Status' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($invoices as $inv)
                            @php
                                $isOverdue = !$inv->fl_paid && $inv->date_expiration?->isPast();
                                $isCN = str_starts_with((string)$inv->id, 'CN');
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="py-2 pr-4 font-mono text-xs text-gray-500">
                                    {{ $inv->id }}
                                    @if($isCN)
                                        <span class="ml-1 px-1 py-0.5 rounded text-xs bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">CN</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-gray-600 dark:text-gray-300">
                                    {{ $inv->date ? $inv->date->format('d/m/Y') : '—' }}
                                </td>
                                <td class="py-2 pr-4 font-mono text-right {{ $isCN ? 'text-purple-700 dark:text-purple-400' : '' }}">
                                    {{ $fmt($inv->total_price_vat_excl) }}
                                </td>
                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400 text-sm">
                                    @if($inv->date_expiration)
                                        <span class="{{ $isOverdue ? 'text-red-600 dark:text-red-400 font-medium' : '' }}">
                                            {{ $inv->date_expiration->format('d/m/Y') }}
                                        </span>
                                        @if($isOverdue)
                                            <span class="ml-1 text-xs text-red-500">
                                                (+{{ $inv->date_expiration->diffInDays() }}d)
                                            </span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2">
                                    @if($inv->fl_paid)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                            {{ $isNl ? 'Betaald' : 'Paid' }}
                                        </span>
                                    @elseif($isOverdue)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                            {{ $isNl ? 'Vervallen' : 'Overdue' }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                                            {{ $isNl ? 'Openstaand' : 'Open' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 dark:border-gray-600">
                        <tr>
                            <td colspan="2" class="pt-2 text-xs text-gray-500">Totaal</td>
                            <td class="pt-2 pr-4 font-mono text-right font-semibold">{{ $fmt($totalInvoiced) }}</td>
                            <td></td>
                            <td class="pt-2 text-xs text-gray-500">
                                <span class="text-green-700 dark:text-green-400 font-medium">{{ $fmt($totalPaid) }}</span>
                                {{ $isNl ? 'betaald' : 'paid' }} ·
                                <span class="{{ $openBalance > 0 ? 'text-orange-700 dark:text-orange-400 font-medium' : '' }}">{{ $fmt($openBalance) }}</span>
                                {{ $isNl ? 'open' : 'open' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- Kosten overzicht --}}
    <x-filament::section>
        <x-slot name="heading">
            {{ $isNl ? 'Kosten overzicht' : 'Cost overview' }}
            <span class="ml-2 text-xs font-normal text-gray-400">({{ $costs->count() }} {{ $isNl ? 'regels' : 'lines' }})</span>
        </x-slot>

        @if($costs->isEmpty())
            <p class="text-sm text-gray-400 italic">{{ $isNl ? 'Geen kosten gevonden.' : 'No costs found.' }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400">{{ $isNl ? 'Type' : 'Type' }}</th>
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400 text-right">{{ $isNl ? 'Regels' : 'Lines' }}</th>
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400 text-right">{{ $isNl ? 'Totaalbedrag' : 'Total' }}</th>
                            <th class="pb-2 font-medium text-gray-500 dark:text-gray-400 text-right">{{ $isNl ? 'Niet-gefact.' : 'Unbilled' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($costsByType as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="py-2 pr-4">
                                    <span class="font-medium text-gray-700 dark:text-gray-200">
                                        {{ $typeLabels[$row['type']] ?? $row['type'] ?? '—' }}
                                    </span>
                                    @if($row['type'])
                                        <span class="ml-1 text-xs text-gray-400 font-mono">[{{ $row['type'] }}]</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right text-gray-500">{{ $row['count'] }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ $fmt($row['amount']) }}</td>
                                <td class="py-2 text-right font-mono {{ $row['unbilled'] > 0 ? 'text-red-700 dark:text-red-400 font-medium' : 'text-gray-400' }}">
                                    {{ $fmt($row['unbilled']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 dark:border-gray-600">
                        <tr>
                            <td class="pt-2 text-xs text-gray-500">Totaal</td>
                            <td class="pt-2 pr-4 text-right font-semibold text-gray-500">{{ $costs->count() }}</td>
                            <td class="pt-2 pr-4 text-right font-mono font-semibold">{{ $fmt($totalCost) }}</td>
                            <td class="pt-2 text-right font-mono font-semibold {{ $unbilledCost > 0 ? 'text-red-700 dark:text-red-400' : '' }}">{{ $fmt($unbilledCost) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- Guardian alerts history --}}
    <x-filament::section>
        <x-slot name="heading">
            {{ $isNl ? 'Guardian alarmen' : 'Guardian alerts' }}
            <span class="ml-2 text-xs font-normal text-gray-400">({{ $alerts->count() }})</span>
        </x-slot>

        @if($alerts->isEmpty())
            <p class="text-sm text-gray-400 italic">{{ $isNl ? 'Geen alarmen geregistreerd voor dit project.' : 'No alerts registered for this project.' }}</p>
        @else
            <div class="overflow-x-auto -mx-4 sm:mx-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                            <th class="pb-2 pr-3 font-medium text-gray-500 dark:text-gray-400">{{ $isNl ? 'Periode' : 'Period' }}</th>
                            <th class="pb-2 pr-3 font-medium text-gray-500 dark:text-gray-400">{{ $isNl ? 'Type' : 'Type' }}</th>
                            <th class="pb-2 pr-3 font-medium text-gray-500 dark:text-gray-400">Ernst</th>
                            <th class="pb-2 pr-3 font-medium text-gray-500 dark:text-gray-400 text-right">{{ $isNl ? 'Bedrag' : 'Amount' }}</th>
                            <th class="pb-2 pr-3 font-medium text-gray-500 dark:text-gray-400">{{ $isNl ? 'Status' : 'Status' }}</th>
                            <th class="pb-2 font-medium text-gray-500 dark:text-gray-400">{{ $isNl ? 'Opmerking' : 'Note' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($alerts as $alert)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="py-2 pr-3 font-mono text-xs text-gray-500 whitespace-nowrap">
                                    {{ str_pad($alert->period_month, 2, '0', STR_PAD_LEFT) }}/{{ $alert->period_year }}
                                </td>
                                <td class="py-2 pr-3 text-gray-700 dark:text-gray-300">
                                    {{ $alertTypeLabels[$alert->alert_type] ?? $alert->alert_type }}
                                </td>
                                <td class="py-2 pr-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $severityColors[$alert->severity] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ ucfirst($alert->severity) }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3 text-right tabular-nums">
                                    @if(isset($amountLabel[$alert->alert_type]))
                                        <span class="block text-xs text-gray-400 dark:text-gray-500 mb-0.5">
                                            {{ $amountLabel[$alert->alert_type] }}
                                        </span>
                                    @endif
                                    <span class="font-mono text-gray-700 dark:text-gray-300">
                                        {{ $alertAmount($alert) !== null ? $fmt($alertAmount($alert)) : '—' }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$alert->status] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ $statusLabels[$alert->status] ?? $alert->status }}
                                    </span>
                                </td>
                                <td class="py-2 text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate" title="{{ $alert->resolution_notes }}">
                                    {{ $alert->resolution_notes ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- Conditional link to AI Insights --}}
    @if($insightUrl)
        <x-filament::section>
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium text-gray-900 dark:text-white">
                        {{ $isNl ? 'Projectinzichten beschikbaar' : 'Project insights available' }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $isNl ? 'Er is een AI-analyse beschikbaar voor dit project (Gemini 1.5 Flash).' : 'An AI analysis is available for this project (Gemini 1.5 Flash).' }}
                    </p>
                </div>
                <x-filament::button
                    color="primary"
                    tag="a"
                    :href="$insightUrl"
                    icon="heroicon-o-sparkles"
                >
                    {{ $isNl ? 'Bekijk inzichten' : 'View insights' }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
