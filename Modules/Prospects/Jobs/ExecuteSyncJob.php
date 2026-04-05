<?php

namespace Modules\Prospects\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Filament\Notifications\Notification;
use Modules\Core\Models\User;

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
        // Execute the command with the user option
        Artisan::call($this->command, array_filter([
            '--user' => $this->userId,
            '--history' => $this->historyId,
        ]));

        // Clean command name for notification (e.g. 'prospects:sync-lbfa-clubs' -> 'LBFA Clubs')
        $cleanName = str($this->command)
            ->after(':')
            ->after('sync-')
            ->replace('-', ' ')
            ->title();

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
