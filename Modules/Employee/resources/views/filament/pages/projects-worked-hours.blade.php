<x-filament-panels::page>
    @php $isNl = app()->getLocale() === 'nl'; @endphp

    {{-- Date filter --}}
    <x-filament::section class="mb-6">
        <x-slot name="heading">{{ $isNl ? 'Periode' : 'Period' }}</x-slot>

        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    {{ $isNl ? 'Van' : 'From' }}
                </label>
                <input type="date" wire:model="startDate"
                       class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-2 py-1.5">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                    {{ $isNl ? 'Tot' : 'To' }}
                </label>
                <input type="date" wire:model="endDate"
                       class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm px-2 py-1.5">
            </div>
            <x-filament::button wire:click="filter" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="filter">{{ $isNl ? 'Zoeken' : 'Search' }}</span>
                <span wire:loading wire:target="filter">...</span>
            </x-filament::button>
        </div>
    </x-filament::section>

    @if($errorMessage)
        <x-filament::section>
            <p class="text-danger-600 dark:text-danger-400">{{ $errorMessage }}</p>
        </x-filament::section>
    @elseif(empty($projects))
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400 italic py-4">
                {{ $isNl ? 'Geen actieve projecten met gewerkte uren voor de geselecteerde periode.' : 'No active projects with worked hours for the selected period.' }}
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">
                {{ $isNl ? 'Actieve projecten' : 'Active projects' }}
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">({{ count($projects) }})</span>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400">
                                {{ $isNl ? 'Project' : 'Project' }}
                            </th>
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400">
                                {{ $isNl ? 'Startdatum' : 'Start date' }}
                            </th>
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">
                                {{ $isNl ? 'Facturatie periode' : 'Period invoiced' }}
                            </th>
                            <th class="pb-2 pr-4 font-medium text-gray-600 dark:text-gray-400 text-right">
                                {{ $isNl ? 'Betaald' : 'Paid' }}
                            </th>
                            <th class="pb-2 font-medium text-gray-600 dark:text-gray-400 text-right">
                                {{ $isNl ? 'Openstaand' : 'Outstanding' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $project)
                            @php
                                $totalPending = $project['total_pending'] ?? 0;
                                $hasInvoices  = $project['has_invoices_in_period'] ?? false;
                            @endphp
                            <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="py-2.5 pr-4">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $project['name'] ?? '—' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $project['id'] ?? '' }}</div>
                                </td>
                                <td class="py-2.5 pr-4 text-gray-600 dark:text-gray-400">
                                    {{ $project['date_start_formatted'] ?? '—' }}
                                </td>
                                <td class="py-2.5 pr-4 text-right">
                                    @if($hasInvoices)
                                        <span class="font-medium text-gray-900 dark:text-gray-100">
                                            €{{ number_format($project['total_invoiced'] ?? 0, 2, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-600 italic text-xs">
                                            {{ $isNl ? 'Geen factuur' : 'No invoice' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-4 text-right text-success-600 dark:text-success-400">
                                    @if($hasInvoices)
                                        €{{ number_format($project['total_paid'] ?? 0, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2.5 text-right">
                                    @if($totalPending > 0.01)
                                        <span class="font-semibold text-danger-600 dark:text-danger-400">
                                            €{{ number_format($totalPending, 2, ',', '.') }}
                                        </span>
                                    @elseif($hasInvoices)
                                        <span class="text-success-600 dark:text-success-400 font-medium text-xs">
                                            {{ $isNl ? 'Volledig betaald' : 'Fully paid' }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
