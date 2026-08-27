<x-filament-panels::page>
    <div wire:poll.5s="loadData">

        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            {{ __('intelligence::mirror_sync.description') }}
        </p>

        {{-- Last sync card --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-4 mb-6">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-1">
                        {{ __('intelligence::mirror_sync.labels.last_sync') }}
                    </p>

                    @if($latestRun)
                        <div class="flex items-center gap-2">
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400' => $latestRun['status'] === 'running',
                                'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' => $latestRun['status'] === 'completed',
                                'bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400' => $latestRun['status'] === 'failed',
                            ])>
                                @if($latestRun['status'] === 'running')<span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>@endif
                                {{ __('intelligence::mirror_sync.status.' . $latestRun['status']) }}
                            </span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($latestRun['started_at'])->diffForHumans() }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {{ $latestRun['trigger_source'] === 'manual'
                                ? __('intelligence::mirror_sync.labels.triggered_by_manual', ['name' => $latestRun['triggered_by_name'] ?? '—'])
                                : __('intelligence::mirror_sync.labels.triggered_by_scheduled') }}
                            @if($latestRun['duration_seconds'] !== null)
                                · {{ __('intelligence::mirror_sync.labels.duration') }}: {{ gmdate('i\m s\s', $latestRun['duration_seconds']) }}
                            @endif
                        </p>
                        @if($latestRun['status'] === 'failed' && $latestRun['error_message'])
                            <p class="text-xs text-danger-600 dark:text-danger-400 mt-2">
                                {{ __('intelligence::mirror_sync.labels.error') }}: {{ $latestRun['error_message'] }}
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('intelligence::mirror_sync.labels.never_synced') }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Recent runs --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('intelligence::mirror_sync.labels.recent_runs') }}</x-slot>

            @if(empty($recentRuns))
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('intelligence::mirror_sync.labels.never_synced') }}</p>
            @else
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($recentRuns as $run)
                        <div class="flex items-center gap-4 px-4 py-3">
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0',
                                'bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-400' => $run['status'] === 'running',
                                'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' => $run['status'] === 'completed',
                                'bg-danger-100 text-danger-700 dark:bg-danger-900/30 dark:text-danger-400' => $run['status'] === 'failed',
                            ])>
                                {{ __('intelligence::mirror_sync.status.' . $run['status']) }}
                            </span>
                            <span class="text-sm text-gray-700 dark:text-gray-300 flex-shrink-0" style="width:160px;">
                                {{ \Carbon\Carbon::parse($run['started_at'])->format('d M Y, H:i') }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 flex-1 truncate">
                                {{ $run['trigger_source'] === 'manual'
                                    ? __('intelligence::mirror_sync.labels.triggered_by_manual', ['name' => $run['triggered_by_name'] ?? '—'])
                                    : __('intelligence::mirror_sync.labels.triggered_by_scheduled') }}
                                @if($run['duration_seconds'] !== null)
                                    · {{ gmdate('i\m s\s', $run['duration_seconds']) }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

    </div>
</x-filament-panels::page>
