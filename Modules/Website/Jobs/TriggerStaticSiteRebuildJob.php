<?php

namespace Modules\Website\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Website\Models\PublicationState;
use Modules\Website\Services\StaticSitePublicationService;

class TriggerStaticSiteRebuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // Backoff in seconds between retry attempts (30 s → 60 s → 120 s).
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly string $dispatchKey,
        public readonly string $reason,
        public readonly bool   $force,
    ) {}

    public function handle(StaticSitePublicationService $service): void
    {
        $state = PublicationState::current();

        // Debounce: a newer requestRebuild() generated a different dispatch_key.
        // This job is stale — abort silently. The newer job will handle the rebuild.
        if ($state->dispatch_key !== $this->dispatchKey) {
            Log::info('Static site rebuild job superseded — aborting.', [
                'our_key'     => $this->dispatchKey,
                'current_key' => $state->dispatch_key,
            ]);
            return;
        }

        $result = $service->sendWebhook($this->reason, $this->force);

        if ($result->success) {
            // Re-check before marking accepted: another admin save may have
            // arrived during the webhook call and reset the dispatch_key to
            // a new value. If so, leave the state as-is (pending) so the
            // new job handles the next rebuild.
            $state->refresh();
            if ($state->dispatch_key === $this->dispatchKey) {
                $state->markAccepted();
            }
            return;
        }

        // Webhook call failed. Throw to trigger the retry+backoff mechanism.
        // Laravel will re-enqueue this job according to $backoff.
        throw new \RuntimeException(
            sprintf(
                'Static site webhook failed (HTTP %d): %s',
                $result->statusCode,
                $result->errorMessage,
            )
        );
    }

    /**
     * Called by Laravel after all $tries are exhausted.
     *
     * Only marks error if our dispatch_key is still the active one in DB.
     * If it changed, a newer job superseded us — don't overwrite its state.
     */
    public function failed(\Throwable $exception): void
    {
        $state = PublicationState::current();

        if ($state->dispatch_key !== $this->dispatchKey) {
            Log::info('Static site rebuild failed but was superseded — not marking error.', [
                'our_key'     => $this->dispatchKey,
                'current_key' => $state->dispatch_key,
            ]);
            return;
        }

        $state->markError(
            sprintf('[%s] %s', get_class($exception), $exception->getMessage())
        );

        Log::error('Static site rebuild job exhausted all retries.', [
            'dispatch_key' => $this->dispatchKey,
            'reason'       => $this->reason,
            'error'        => $exception->getMessage(),
        ]);
    }
}
