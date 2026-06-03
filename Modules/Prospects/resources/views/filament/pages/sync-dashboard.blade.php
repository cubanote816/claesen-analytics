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

        {{-- Federation cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($federations as $fed)
                @php
                    $isActive  = in_array($fed['status'], ['pending', 'running']);
                    $isBlocked = $activeMaster !== null && ! $isActive;
                    $canSync   = ! $isActive && ! $isBlocked;
                    $statusColor = match($fed['status']) {
                        'pending'   => 'info',
                        'running'   => 'warning',
                        'completed' => 'success',
                        'failed'    => 'danger',
                        default     => 'gray',
                    };
                @endphp

                <x-filament::section>
                    <div class="flex flex-col gap-3 h-full">

                        {{-- Header --}}
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-2xl leading-none">{{ $fed['icon'] }}</span>
                                <h3 class="mt-1 text-base font-bold text-gray-900 dark:text-white">
                                    {{ $fed['label'] }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $fed['sport'] }}
                                </p>
                            </div>

                            {{-- Status badge --}}
                            @if($fed['status'])
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0',
                                    'bg-info-100 text-info-700 dark:bg-info-900/30 dark:text-info-400'       => $fed['status'] === 'pending',
                                    'bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400' => $fed['status'] === 'running',
                                    'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' => $fed['status'] === 'completed',
                                    'bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400' => $fed['status'] === 'failed',
                                ])>
                                    @if($fed['status'] === 'running') <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span> @endif
                                    {{ __('prospects::resource.options.status.' . $fed['status']) }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400 flex-shrink-0">
                                    Nooit gesync'd
                                </span>
                            @endif
                        </div>

                        {{-- Metrics --}}
                        <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                            @if($fed['records_count'])
                                <p>📋 {{ number_format($fed['records_count']) }} records</p>
                            @endif
                            @if($fed['started_at'])
                                <p>🕐 {{ \Carbon\Carbon::parse($fed['started_at'])->format('d M Y, H:i') }}</p>
                            @endif
                            @if(! $fed['records_count'] && ! $fed['started_at'])
                                <p class="italic">Geen historiek beschikbaar</p>
                            @endif
                        </div>

                        {{-- Action --}}
                        <div class="mt-auto pt-2">
                            @if($isActive && $fed['history_id'])
                                <x-filament::button
                                    tag="a"
                                    :href="route('filament.admin.resources.sync-histories.view', ['record' => $fed['history_id']])"
                                    color="warning"
                                    size="sm"
                                    icon="heroicon-o-eye"
                                    class="w-full justify-center"
                                >
                                    Bekijk voortgang
                                </x-filament::button>
                            @elseif($isBlocked)
                                <x-filament::button
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-o-lock-closed"
                                    disabled
                                    class="w-full justify-center"
                                >
                                    Geblokkeerd
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    wire:click="syncFederation('{{ $fed['command'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="syncFederation('{{ $fed['command'] }}')"
                                    color="{{ $fed['status'] === 'failed' ? 'danger' : 'primary' }}"
                                    size="sm"
                                    icon="heroicon-o-arrow-path"
                                    class="w-full justify-center"
                                >
                                    <span wire:loading.remove wire:target="syncFederation('{{ $fed['command'] }}')">
                                        {{ $fed['status'] === 'failed' ? 'Opnieuw proberen' : 'Sync nu' }}
                                    </span>
                                    <span wire:loading wire:target="syncFederation('{{ $fed['command'] }}')">
                                        Bezig...
                                    </span>
                                </x-filament::button>
                            @endif
                        </div>

                    </div>
                </x-filament::section>
            @endforeach
        </div>

        {{-- Recent activity feed --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">Recente Activiteit</x-slot>

            @if(empty($recentActivity))
                <p class="text-sm text-gray-400 italic">Geen synchronisaties gevonden.</p>
            @else
                <div class="space-y-1">
                    @foreach($recentActivity as $record)
                        <a
                            href="{{ route('filament.admin.resources.sync-histories.view', ['record' => $record['id']]) }}"
                            class="flex items-center gap-3 rounded-md px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors group"
                        >
                            {{-- Status dot --}}
                            <span @class([
                                'w-2 h-2 rounded-full flex-shrink-0',
                                'bg-info-500'                    => $record['status'] === 'pending',
                                'bg-warning-500 animate-pulse'   => $record['status'] === 'running',
                                'bg-success-500'                 => $record['status'] === 'completed',
                                'bg-danger-500'                  => $record['status'] === 'failed',
                                'bg-gray-400'                    => ! in_array($record['status'], ['pending','running','completed','failed']),
                            ])></span>

                            {{-- Label --}}
                            <span class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ str(str_replace('prospects:sync-', '', str_replace('prospects:', '', $record['command'])))->replace('-', ' ')->title() }}
                            </span>

                            {{-- Records --}}
                            @if($record['records_count'])
                                <span class="text-xs text-gray-400">{{ number_format($record['records_count']) }} records</span>
                            @endif

                            {{-- Time --}}
                            <span class="text-xs text-gray-400 flex-shrink-0">
                                {{ $record['started_at'] ? \Carbon\Carbon::parse($record['started_at'])->diffForHumans() : '—' }}
                            </span>

                            <x-filament::icon
                                icon="heroicon-o-arrow-right"
                                class="w-3 h-3 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 flex-shrink-0"
                            />
                        </a>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

    </div>
</x-filament-panels::page>
