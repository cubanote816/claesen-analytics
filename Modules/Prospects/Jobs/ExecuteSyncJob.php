<?php

namespace Modules\Prospects\Jobs;

use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Models\User;
use Modules\Prospects\Models\SyncHistory;
use RuntimeException;

class ExecuteSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $command,
        protected ?int $userId = null,
        protected ?int $historyId = null
    ) {}

    public function handle(): void
    {
        $exitCode = Artisan::call($this->command, array_filter([
            '--user' => $this->userId,
            '--history' => $this->historyId,
        ]));

        // Clean command name for notification (e.g. 'prospects:sync-lbfa-clubs' -> 'LBFA Clubs')
        $cleanName = str($this->command)
            ->after(':')
            ->after('sync-')
            ->replace('-', ' ')
            ->title();

        if ($exitCode !== 0) {
            $this->markHistoryAsFailed("Command exited with code {$exitCode}.");
            $this->sendFailureNotification($cleanName);

            throw new RuntimeException("Sync command {$this->command} exited with code {$exitCode}.");
        }

        // Send Notification if a user triggered it
        if ($this->userId) {
            $user = User::find($this->userId);
            if ($user) {
                Notification::make()
                    ->title("Sincronización {$cleanName} Finalizada")
                    ->body("El proceso de actualización de {$cleanName} ha terminado satisfactoriamente.")
                    ->success()
                    ->sendToDatabase($user);
            }
        }
    }

    private function markHistoryAsFailed(string $message): void
    {
        if (! $this->historyId) {
            return;
        }

        $history = SyncHistory::find($this->historyId);
        if (! $history || ! in_array($history->status, ['pending', 'running'], true)) {
            return;
        }

        $logs = $history->logs ?? [];
        $logs[] = [
            'time' => now()->format('H:i:s'),
            'message' => $message,
            'type' => 'error',
            'icon' => '❌',
        ];

        $history->update([
            'status' => 'failed',
            'finished_at' => now(),
            'logs' => $logs,
        ]);
    }

    private function sendFailureNotification(string $cleanName): void
    {
        if (! $this->userId) {
            return;
        }

        $user = User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title("Sincronización {$cleanName} Fallida")
                ->body("El proceso de actualización de {$cleanName} ha finalizado con errores.")
                ->danger()
                ->sendToDatabase($user);
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->command))->releaseAfter(60),
        ];
    }
}
