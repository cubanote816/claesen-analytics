<x-filament-panels::page>
    <div wire:poll.5s="loadData">

        {{-- Active master alert --}}
        @if($activeMaster)
            <div class="mb-4 rounded-lg border border-warning-400 bg-warning-50 dark:bg-warning-950/20 px-4 py-3 flex items-center gap-3">
                <x-filament::icon
                    icon="heroicon-o-bolt"
                    class="w-5 h-5 text-warning-500 animate-pulse flex-shrink-0"
                />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-warning-700 dark:text-warning-400">
                        Master Sync in uitvoering
                    </p>
                    <p class="text-xs text-warning-600 dark:text-warning-500">
                        Alle individuele syncs zijn geblokkeerd.
                        @if($activeMaster['started_at'])
                            · Gestart om {{ \Carbon\Carbon::parse($activeMaster['started_at'])->format('H:i') }}
                        @else
                            · In wachtrij...
                        @endif
                    </p>
                </div>
                <a
                    href="{{ route('filament.admin.resources.sync-histories.view', ['record' => $activeMaster['id']]) }}"
                    class="text-sm font-medium text-warning-700 dark:text-warning-400 hover:underline flex-shrink-0"
                >
                    Bekijk voortgang →
                </a>
            </div>
        @endif

        {{-- Federation cards — view toggle (grid / list) persisted in localStorage --}}
        <div x-data="{ viewMode: $persist('grid').as('sync-view-mode') }">

            {{-- Toggle header --}}
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                    Federaties
                </span>
                <div class="inline-flex items-center gap-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
                    {{-- Grid button --}}
                    <button
                        @click="viewMode = 'grid'"
                        :class="viewMode === 'grid'
                            ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-sm'
                            : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                        class="rounded-md p-1.5 transition-all duration-150 cursor-pointer"
                        title="Rasterweergave"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
                        </svg>
                    </button>
                    {{-- List button --}}
                    <button
                        @click="viewMode = 'list'"
                        :class="viewMode === 'list'
                            ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-sm'
                            : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                        class="rounded-md p-1.5 transition-all duration-150 cursor-pointer"
                        title="Lijstweergave"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- ── GRID VIEW ─────────────────────────────────────────────── --}}
            <div
                x-show="viewMode === 'grid'"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="grid"
                style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:0.75rem;"
            >
                @foreach($federations as $fed)
                    @php
                        $isActive    = in_array($fed['status'], ['pending', 'running']);
                        $isBlocked   = $activeMaster !== null && ! $isActive;
                        $metricParts = [];
                        if ($fed['records_count']) $metricParts[] = '📋 ' . number_format($fed['records_count']) . ' records';
                        if ($fed['started_at'])    $metricParts[] = '🕐 ' . \Carbon\Carbon::parse($fed['started_at'])->format('d M Y, H:i');
                    @endphp

                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 flex flex-col gap-2" style="min-height:160px;">

                        {{-- Header: icon + name + badge --}}
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-xl leading-none flex-shrink-0">{{ $fed['icon'] }}</span>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white leading-tight">{{ $fed['label'] }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $fed['sport'] }}</p>
                                </div>
                            </div>
                            @if($fed['status'])
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0',
                                    'bg-info-100 text-info-700 dark:bg-info-900/30 dark:text-info-400'            => $fed['status'] === 'pending',
                                    'bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400' => $fed['status'] === 'running',
                                    'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' => $fed['status'] === 'completed',
                                    'bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400'     => $fed['status'] === 'failed',
                                ])>
                                    @if($fed['status'] === 'running')<span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>@endif
                                    {{ __('prospects::resource.options.status.' . $fed['status']) }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400 flex-shrink-0">
                                    Nooit gesync'd
                                </span>
                            @endif
                        </div>

                        {{-- Metrics --}}
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            @if($metricParts)
                                {{ implode(' · ', $metricParts) }}
                            @else
                                <em>Geen historiek beschikbaar</em>
                            @endif
                        </p>

                        {{-- Action --}}
                        <div class="mt-auto flex justify-end">
                            @if($isActive && $fed['history_id'])
                                <x-filament::button tag="a" :href="route('filament.admin.resources.sync-histories.view', ['record' => $fed['history_id']])" color="warning" size="sm" icon="heroicon-o-eye">Bekijk voortgang</x-filament::button>
                            @elseif($isBlocked)
                                <x-filament::button color="gray" size="sm" icon="heroicon-o-lock-closed" disabled>Geblokkeerd</x-filament::button>
                            @else
                                <x-filament::button wire:click="syncFederation('{{ $fed['command'] }}')" wire:loading.attr="disabled" wire:target="syncFederation('{{ $fed['command'] }}')" color="{{ $fed['status'] === 'failed' ? 'danger' : 'primary' }}" size="sm" icon="heroicon-o-arrow-path">
                                    <span wire:loading.remove wire:target="syncFederation('{{ $fed['command'] }}')">{{ $fed['status'] === 'failed' ? 'Opnieuw proberen' : 'Sync nu' }}</span>
                                    <span wire:loading wire:target="syncFederation('{{ $fed['command'] }}')">Bezig...</span>
                                </x-filament::button>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>

            {{-- ── LIST VIEW ─────────────────────────────────────────────── --}}
            <div
                x-show="viewMode === 'list'"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden divide-y divide-gray-100 dark:divide-gray-800"
            >
                @foreach($federations as $fed)
                    @php
                        $isActive    = in_array($fed['status'], ['pending', 'running']);
                        $isBlocked   = $activeMaster !== null && ! $isActive;
                        $metricParts = [];
                        if ($fed['records_count']) $metricParts[] = '📋 ' . number_format($fed['records_count']) . ' records';
                        if ($fed['started_at'])    $metricParts[] = '🕐 ' . \Carbon\Carbon::parse($fed['started_at'])->format('d M Y, H:i');
                    @endphp

                    <div class="flex items-center gap-4 px-4 py-3">

                        {{-- Icon + Name --}}
                        <div class="flex items-center gap-2 flex-shrink-0" style="width:150px;">
                            <span class="text-base leading-none">{{ $fed['icon'] }}</span>
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white leading-tight">{{ $fed['label'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $fed['sport'] }}</div>
                            </div>
                        </div>

                        {{-- Status badge --}}
                        <div class="flex-shrink-0" style="width:120px;">
                            @if($fed['status'])
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-info-100 text-info-700 dark:bg-info-900/30 dark:text-info-400'            => $fed['status'] === 'pending',
                                    'bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400' => $fed['status'] === 'running',
                                    'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' => $fed['status'] === 'completed',
                                    'bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400'     => $fed['status'] === 'failed',
                                ])>
                                    @if($fed['status'] === 'running')<span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>@endif
                                    {{ __('prospects::resource.options.status.' . $fed['status']) }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    Nooit gesync'd
                                </span>
                            @endif
                        </div>

                        {{-- Metrics --}}
                        <div class="flex-1 min-w-0 text-xs text-gray-400 dark:text-gray-500 truncate">
                            @if($metricParts)
                                {{ implode(' · ', $metricParts) }}
                            @else
                                <em>Geen historiek beschikbaar</em>
                            @endif
                        </div>

                        {{-- Action --}}
                        <div class="flex-shrink-0">
                            @if($isActive && $fed['history_id'])
                                <x-filament::button tag="a" :href="route('filament.admin.resources.sync-histories.view', ['record' => $fed['history_id']])" color="warning" size="sm" icon="heroicon-o-eye">Bekijk voortgang</x-filament::button>
                            @elseif($isBlocked)
                                <x-filament::button color="gray" size="sm" icon="heroicon-o-lock-closed" disabled>Geblokkeerd</x-filament::button>
                            @else
                                <x-filament::button wire:click="syncFederation('{{ $fed['command'] }}')" wire:loading.attr="disabled" wire:target="syncFederation('{{ $fed['command'] }}')" color="{{ $fed['status'] === 'failed' ? 'danger' : 'primary' }}" size="sm" icon="heroicon-o-arrow-path">
                                    <span wire:loading.remove wire:target="syncFederation('{{ $fed['command'] }}')">{{ $fed['status'] === 'failed' ? 'Opnieuw proberen' : 'Sync nu' }}</span>
                                    <span wire:loading wire:target="syncFederation('{{ $fed['command'] }}')">Bezig...</span>
                                </x-filament::button>
                            @endif
                        </div>

                    </div>
                @endforeach
            </div>

        </div>{{-- end x-data --}}

        {{-- Exception feed --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">Aandacht vereist</x-slot>

            @if(empty($failedSyncs))
                <div class="flex items-center gap-3 rounded-lg border border-success-200 bg-success-50 px-4 py-3 dark:border-success-900/50 dark:bg-success-950/20">
                    <x-filament::icon
                        icon="heroicon-o-check-circle"
                        class="w-5 h-5 text-success-600 dark:text-success-400 flex-shrink-0"
                    />
                    <p class="text-sm font-medium text-success-700 dark:text-success-300">
                        Alle syncs gezond
                    </p>
                </div>
            @else
                <div class="rounded-xl border border-danger-200 bg-white dark:border-danger-900/50 dark:bg-gray-900 overflow-hidden divide-y divide-danger-100 dark:divide-danger-950/60">
                    @foreach($failedSyncs as $sync)
                        @php
                            $isBlocked = $activeMaster !== null;
                        @endphp

                        <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center">
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <span class="text-lg leading-none flex-shrink-0">{{ $sync['icon'] }}</span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ $sync['label'] }}
                                        </p>
                                        <span class="inline-flex items-center rounded-full bg-danger-100 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-900/30 dark:text-danger-400">
                                            Mislukt
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        @if($sync['sport'])
                                            {{ $sync['sport'] }} ·
                                        @endif
                                        {{ $sync['started_at'] ? \Carbon\Carbon::parse($sync['started_at'])->format('d M Y, H:i') : 'Tijdstip onbekend' }}
                                        @if($sync['records_count'])
                                            · {{ number_format($sync['records_count']) }} records
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 sm:flex-shrink-0">
                                <x-filament::button
                                    tag="a"
                                    :href="route('filament.admin.resources.sync-histories.view', ['record' => $sync['id']])"
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-o-eye"
                                >
                                    Details
                                </x-filament::button>

                                @if($isBlocked)
                                    <x-filament::button color="gray" size="sm" icon="heroicon-o-lock-closed" disabled>
                                        Geblokkeerd
                                    </x-filament::button>
                                @else
                                    <x-filament::button
                                        wire:click="syncFederation('{{ $sync['command'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="syncFederation('{{ $sync['command'] }}')"
                                        color="danger"
                                        size="sm"
                                        icon="heroicon-o-arrow-path"
                                    >
                                        <span wire:loading.remove wire:target="syncFederation('{{ $sync['command'] }}')">Opnieuw proberen</span>
                                        <span wire:loading wire:target="syncFederation('{{ $sync['command'] }}')">Bezig...</span>
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

    </div>
</x-filament-panels::page>
