<?php

namespace Modules\Prospects\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Modules\Prospects\Jobs\ExecuteSyncJob;
use Modules\Prospects\Jobs\MasterSyncJob;
use Modules\Prospects\Models\SyncHistory;

class SyncDashboardPage extends Page
{
    protected string $view = 'prospects::filament.pages.sync-dashboard';

    protected static ?string $slug = 'sync-dashboard';

    protected static ?int $navigationSort = 10;

    // Ordered as MasterSyncJob chains them
    private const FEDERATIONS = [
        ['command' => 'prospects:sync-lbfa-clubs',   'label' => 'LBFA',   'sport' => 'Atletiek FR',    'icon' => '🏃'],
        ['command' => 'prospects:sync-aft-clubs',    'label' => 'AFT',    'sport' => 'Tennis FR',      'icon' => '🎾'],
        ['command' => 'prospects:sync-hockey-clubs', 'label' => 'Hockey', 'sport' => 'Hockey',         'icon' => '🏒'],
        ['command' => 'prospects:sync-tpv-clubs',    'label' => 'TPV',    'sport' => 'Tennis & Padel', 'icon' => '🎾'],
        ['command' => 'prospects:sync-val-clubs',    'label' => 'VAL',    'sport' => 'Atletiek NL',    'icon' => '🏃'],
        ['command' => 'prospects:sync-rbfa-graphql', 'label' => 'RBFA',   'sport' => 'Football',       'icon' => '⚽'],
    ];

    public array $federations = [];
    public ?array $activeMaster = null;
    public array $recentActivity = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('prospects::resource.navigation_group');
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::CircleStack;
    }

    public static function getNavigationLabel(): string
    {
        return 'Sync Dashboard';
    }

    public function getTitle(): string
    {
        return 'Synchronisatie Dashboard';
    }

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $commands = array_column(self::FEDERATIONS, 'command');

        // Last record per individual command — max 6 rows
        $latestPerCommand = SyncHistory::whereIn('command', $commands)
            ->orderByDesc('started_at')
            ->get(['id', 'command', 'status', 'records_count', 'started_at', 'finished_at'])
            ->unique('command')
            ->keyBy('command');

        // Active master (pending or running)
        $master = SyncHistory::where('command', 'prospects:sync-master')
            ->whereIn('status', ['pending', 'running'])
            ->latest()
            ->first(['id', 'status', 'started_at']);

        $this->activeMaster = $master
            ? ['id' => $master->id, 'status' => $master->status, 'started_at' => $master->started_at?->toDateTimeString()]
            : null;

        // Build federation card data
        $this->federations = collect(self::FEDERATIONS)
            ->map(function (array $def) use ($latestPerCommand): array {
                $last = $latestPerCommand->get($def['command']);
                return [
                    ...$def,
                    'status'        => $last?->status,
                    'records_count' => $last?->records_count,
                    'started_at'    => $last?->started_at?->toDateTimeString(),
                    'history_id'    => $last?->id,
                ];
            })
            ->toArray();

        // Recent activity feed — max 10 rows, minimal columns
        $this->recentActivity = SyncHistory::latest('started_at')
            ->take(10)
            ->get(['id', 'command', 'status', 'records_count', 'started_at'])
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_all')
                ->label(__('prospects::resource.actions.sync_master.label'))
                ->icon(Heroicon::Bolt)
                ->color('primary')
                ->requiresConfirmation()
                ->disabled(fn (): bool => $this->activeMaster !== null)
                ->action(fn () => $this->syncAll()),
        ];
    }

    public function syncFederation(string $command): void
    {
        // Reject unknown commands — prevents arbitrary artisan execution
        $validCommands = array_column(self::FEDERATIONS, 'command');
        if (! in_array($command, $validCommands, true)) {
            return;
        }

        $result  = null;
        $history = null;

        DB::transaction(function () use ($command, &$result, &$history): void {
            $masterActive = SyncHistory::where('command', 'prospects:sync-master')
                ->whereIn('status', ['pending', 'running'])
                ->lockForUpdate()
                ->exists();

            if ($masterActive) {
                $result = 'master_active';
                return;
            }

            $duplicate = SyncHistory::where('command', $command)
                ->whereIn('status', ['pending', 'running'])
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                $result = 'duplicate';
                return;
            }

            $history = SyncHistory::create([
                'command'    => $command,
                'type'       => 'individual',
                'status'     => 'pending',
                'started_at' => null,
                'user_id'    => auth()->id(),
                'logs'       => [],
            ]);
        });

        // Side effects outside the transaction
        if ($result === 'master_active') {
            Notification::make()
                ->title(__('prospects::resource.notifications.sync_blocked_master.title'))
                ->body(__('prospects::resource.notifications.sync_blocked_master.body'))
                ->warning()
                ->send();
            return;
        }

        if ($result === 'duplicate') {
            Notification::make()
                ->title(__('prospects::resource.notifications.sync_already_running.title'))
                ->warning()
                ->send();
            return;
        }

        ExecuteSyncJob::dispatch($command, auth()->id(), $history->id);

        Notification::make()
            ->title(__('prospects::resource.notifications.sync_started.title'))
            ->info()
            ->send();

        $this->loadData();
    }

    public function syncAll(): void
    {
        $result  = null;
        $history = null;

        DB::transaction(function () use (&$result, &$history): void {
            $anyActive = SyncHistory::whereIn('status', ['pending', 'running'])
                ->lockForUpdate()
                ->exists();

            if ($anyActive) {
                $result = 'any_active';
                return;
            }

            $history = SyncHistory::create([
                'command'    => 'prospects:sync-master',
                'type'       => 'master',
                'status'     => 'pending',
                'started_at' => null,
                'user_id'    => auth()->id(),
                'logs'       => [[
                    'time'    => now()->format('H:i:s'),
                    'message' => __('prospects::resource.sync_history.logs.master_requested'),
                    'type'    => 'info',
                    'icon'    => '🚀',
                ]],
            ]);
        });

        if ($result === 'any_active') {
            Notification::make()
                ->title(__('prospects::resource.notifications.sync_blocked_any.title'))
                ->body(__('prospects::resource.notifications.sync_blocked_any.body'))
                ->warning()
                ->send();
            return;
        }

        MasterSyncJob::dispatch(auth()->id(), $history->id);

        Notification::make()
            ->title(__('prospects::resource.notifications.master_sync_started.title'))
            ->info()
            ->body(__('prospects::resource.notifications.master_sync_started.body'))
            ->send();

        $this->loadData();
    }
}
