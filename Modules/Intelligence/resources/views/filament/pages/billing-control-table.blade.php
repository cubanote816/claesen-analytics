{{--
    Reusable alert table for Billing Control sections.
    Required: $alerts, $projects, $relations, $insightSet,
              $severityColors, $statusColors, $statusLabels, $statusTooltips,
              $amountLabels, $typeLabels, $isNl, $showType (bool)
    Optional: $showLimit (int) — show N rows; rest revealed via Alpine toggle.
--}}
@php
    $showLimit = $showLimit ?? null;
    $hasMore   = $showLimit && $alerts->count() > $showLimit;
@endphp

<div @if($hasMore) x-data="{ showAll: false }" @endif>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    @if($showType)
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Type' : 'Type' }}</th>
                    @endif
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Project' : 'Project' }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Ernst' : 'Severity' }}</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Status' : 'Status' }}</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300 cursor-help"
                        title="{{ $isNl
                            ? 'Bedrag varieert per meldingstype: gedetecteerde kost (facturatie), open saldo (vorderingen), creditbedrag.'
                            : 'Amount type varies: detected cost (invoicing), open balance (receivables), credit amount.' }}">
                        {{ $isNl ? 'Bedrag ?' : 'Amount ?' }}
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Aanbeveling' : 'Recommendation' }}</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">{{ $isNl ? 'Acties' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($alerts as $i => $alert)
                    <tr @if($hasMore && $i >= $showLimit) x-show="showAll" x-cloak @endif
                        class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">

                        @if($showType)
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs text-gray-600 dark:text-gray-400">
                                {{ $typeLabels[$alert->alert_type] ?? $alert->alert_type }}
                            </span>
                        </td>
                        @endif

                        {{-- Project / invoice cell --}}
                        <td class="px-4 py-3 min-w-[10rem]">
                            @if($alert->project_id)
                                @php
                                    $proj    = $projects->get($alert->project_id);
                                    $relId   = $proj?->relation_id ?? $alert->relation_id;
                                    $rel     = $relId ? $relations->get($relId) : null;
                                    $hasLink = array_key_exists($alert->project_id, $insightSet);
                                @endphp
                                <span class="font-mono font-medium text-gray-900 dark:text-white text-xs block">
                                    {{ $alert->project_id }}
                                </span>
                                @if($proj?->name)
                                    <span class="text-xs text-gray-600 dark:text-gray-300 block leading-tight mt-0.5">
                                        {{ $proj->name }}
                                    </span>
                                @endif
                                @if($rel?->name)
                                    <span class="text-xs text-gray-400 dark:text-gray-500 block leading-tight">
                                        {{ $rel->name }}
                                    </span>
                                @endif
                                @if($hasLink)
                                    <a href="{{ \Modules\Performance\Filament\Resources\ProjectInsightResource::getUrl('view', ['record' => trim($alert->project_id)]) }}"
                                       target="_blank" rel="noopener"
                                       class="mt-1 inline-flex items-center gap-0.5 text-xs text-primary-600 dark:text-primary-400 hover:underline">
                                        &#8599; {{ $isNl ? 'Inzichten' : 'Insights' }}
                                    </a>
                                @endif
                            @else
                                <span class="font-mono font-medium text-gray-900 dark:text-white text-xs">
                                    {{ $alert->invoice_id ?? '—' }}
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <span @class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', $severityColors[$alert->severity] ?? ''])>
                                {{ ucfirst($alert->severity) }}
                            </span>
                        </td>

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

                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button
                                wire:click="openModal({{ $alert->id }})"
                                class="mr-2 text-xs px-2 py-1 rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 transition-colors"
                                title="{{ $isNl ? 'Volledige details bekijken' : 'View full details' }}"
                            >&#9432; {{ $isNl ? 'Details' : 'Details' }}</button>
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

    {{-- Show-more toggle (only when $showLimit is active and there are hidden rows) --}}
    @if($hasMore)
    <div class="mt-3 flex items-center justify-between">
        <p class="text-xs text-gray-400 dark:text-gray-500">
            {{ $isNl
                ? "Top {$showLimit} van {$alerts->count()} meldingen getoond"
                : "Showing top {$showLimit} of {$alerts->count()} alerts" }}
        </p>
        <button @click="showAll = !showAll"
                class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">
            <span x-show="!showAll">
                &#8595; {{ $isNl ? "Toon alle {$alerts->count()} meldingen" : "Show all {$alerts->count()} alerts" }}
            </span>
            <span x-show="showAll" style="display:none">
                &#8593; {{ $isNl ? 'Minder tonen' : 'Show less' }}
            </span>
        </button>
    </div>
    @else
    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 text-right">
        {{ $alerts->count() }} {{ $isNl ? 'melding(en)' : 'alert(s)' }}
    </p>
    @endif
</div>
