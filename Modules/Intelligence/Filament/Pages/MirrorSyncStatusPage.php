<?php

declare(strict_types=1);

namespace Modules\Intelligence\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Modules\Intelligence\Jobs\RunManualDataSyncJob;
use Modules\Intelligence\Models\MirrorSyncRun;

class MirrorSyncStatusPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $slug = 'mirror-sync-status';
    protected static ?int $navigationSort = 91;

    protected string $view = 'intelligence::filament.pages.mirror-sync-status';

    public ?array $latestRun = null;
    public array $recentRuns = [];
    public bool $isRunning = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'financial_manager']) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.intelligence_hub');
    }

    public static function getNavigationLabel(): string
    {
        return __('intelligence::mirror_sync.navigation_label');
    }

    public function getTitle(): string
    {
        return __('intelligence::mirror_sync.title');
    }

    public function mount(): void
    {
        $this->loadData();
    }

    /**
     * CLA-439 — distinto de $isRunning (que solo mira MirrorSyncRun, exclusivo
     * del botón "Refresh now"/mirror completo): los 4 syncs manuales nuevos
     * comparten un lock de cache propio, sin tabla de tracking dedicada.
     */
    public function isManualSyncRunning(): bool
    {
        return Cache::has(RunManualDataSyncJob::RUNNING_FLAG_KEY);
    }

    public function loadData(): void
    {
        $latest = MirrorSyncRun::latest('started_at')->first();

        $this->latestRun = $latest ? $this->formatRun($latest) : null;
        $this->isRunning = MirrorSyncRun::where('status', MirrorSyncRun::STATUS_RUNNING)->exists();

        $this->recentRuns = MirrorSyncRun::latest('started_at')
            ->limit(10)
            ->get()
            ->map(fn (MirrorSyncRun $run) => $this->formatRun($run))
            ->toArray();
    }

    private function formatRun(MirrorSyncRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status,
            'trigger_source' => $run->trigger_source,
            'triggered_by_name' => $run->triggeredBy?->name,
            'started_at' => $run->started_at?->toDateTimeString(),
            'finished_at' => $run->finished_at?->toDateTimeString(),
            'duration_seconds' => $run->durationSeconds(),
            'error_message' => $run->error_message,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh_now')
                ->label(__('intelligence::mirror_sync.actions.refresh_now'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->disabled(fn (): bool => $this->isRunning)
                ->action('runSyncNow'),
            Action::make('sync_employees')
                ->label(__('intelligence::mirror_sync.actions.sync_employees'))
                ->icon('heroicon-o-users')
                ->color('gray')
                ->requiresConfirmation()
                ->disabled(fn (): bool => $this->isManualSyncRunning())
                ->action(fn () => $this->runManualSync('employees')),
            Action::make('sync_clients')
                ->label(__('intelligence::mirror_sync.actions.sync_clients'))
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->requiresConfirmation()
                ->disabled(fn (): bool => $this->isManualSyncRunning())
                ->action(fn () => $this->runManualSync('clients')),
            Action::make('sync_complexes')
                ->label(__('intelligence::mirror_sync.actions.sync_complexes'))
                ->icon('heroicon-o-map-pin')
                ->color('gray')
                ->requiresConfirmation()
                ->disabled(fn (): bool => $this->isManualSyncRunning())
                ->action(fn () => $this->runManualSync('complexes')),
            Action::make('sync_all_manual')
                ->label(__('intelligence::mirror_sync.actions.sync_all_manual'))
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->disabled(fn (): bool => $this->isManualSyncRunning())
                ->action(fn () => $this->runManualSync('all')),
        ];
    }

    /**
     * CLA-439 — dispara uno de los 4 sync manuales (empleados/clientes/
     * complejos/todo) en cola (RunManualDataSyncJob), distinto del "Refresh
     * now" ya existente (mirror CAFCA completo, botón/acción separados).
     */
    public function runManualSync(string $task): void
    {
        if ($this->isManualSyncRunning()) {
            Notification::make()
                ->title(__('intelligence::mirror_sync.notifications.already_running_title'))
                ->body(__('intelligence::mirror_sync.notifications.already_running_body'))
                ->warning()
                ->send();

            return;
        }

        RunManualDataSyncJob::dispatch($task, auth()->id());

        Notification::make()
            ->title(__('intelligence::mirror_sync.notifications.queued_title'))
            ->body(__('intelligence::mirror_sync.notifications.queued_body'))
            ->success()
            ->send();
    }

    public function runSyncNow(): void
    {
        $this->loadData();

        if ($this->isRunning) {
            Notification::make()
                ->title(__('intelligence::mirror_sync.notifications.already_running_title'))
                ->body(__('intelligence::mirror_sync.notifications.already_running_body'))
                ->warning()
                ->send();

            return;
        }

        try {
            Artisan::queue('intelligence:sync-mirror', [
                '--source' => MirrorSyncRun::SOURCE_MANUAL,
                '--triggered-by' => (string) auth()->id(),
            ]);

            Notification::make()
                ->title(__('intelligence::mirror_sync.notifications.queued_title'))
                ->body(__('intelligence::mirror_sync.notifications.queued_body'))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('intelligence::mirror_sync.notifications.failed_title'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->loadData();
    }
}
