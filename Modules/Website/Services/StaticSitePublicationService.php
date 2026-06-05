<?php

namespace Modules\Website\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;
use Modules\Website\DTOs\WebhookResult;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\PublicationState;

class StaticSitePublicationService
{
    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Request a frontend rebuild after the debounce window.
     *
     * Safe to call from model observers — never throws. The admin save
     * succeeds regardless of webhook outcome; the job handles retries.
     *
     * Debounce: each call generates a new dispatch_key. The previous job
     * reads the persisted key on execution; a mismatch means it was
     * superseded and it aborts silently.
     */
    public function requestRebuild(string $reason = 'content_changed', bool $force = false): void
    {
        if (!config('static_site.enabled')) {
            return;
        }

        $state = PublicationState::current();
        $state->markPending();

        $dispatchKey = Str::uuid()->toString();
        $state->recordDispatch($dispatchKey);

        $debounce = (int) config('static_site.debounce_seconds', 20);

        TriggerStaticSiteRebuildJob::dispatch($dispatchKey, $reason, $force)
            ->delay(now()->addSeconds($debounce));

        Log::info('Static site rebuild requested.', [
            'reason'       => $reason,
            'force'        => $force,
            'dispatch_key' => $dispatchKey,
            'fires_at'     => now()->addSeconds($debounce)->toIso8601String(),
        ]);
    }

    /**
     * Call the frontend rebuild webhook and return the result.
     *
     * Called by TriggerStaticSiteRebuildJob after the debounce window.
     * 202 = request accepted by frontend (build may still be in progress).
     * Real build status is at config('static_site.health_url').
     */
    public function sendWebhook(string $reason, bool $force): WebhookResult
    {
        $url     = config('static_site.webhook_url');
        $secret  = config('static_site.webhook_secret');
        $timeout = (int) config('static_site.webhook_timeout', 3);

        if (!$url || !$secret) {
            return WebhookResult::failure(0, 'webhook_url or webhook_secret not configured in static_site config');
        }

        $timestamp = time();

        $payload = [
            'source'      => 'backend',
            'environment' => config('static_site.environment', 'production'),
            'reason'      => $reason,
            'force'       => $force,
        ];

        // Serialize once — the same $body is signed AND sent as raw bytes.
        // HMAC covers: timestamp + "." + body  (exact bytes, no re-encoding).
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'X-Webhook-Timestamp' => (string) $timestamp,
                    'X-Webhook-Signature' => 'sha256=' . $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if ($response->status() === 202) {
                Log::info('Static site webhook accepted (202).', [
                    'url'    => $url,
                    'reason' => $reason,
                ]);
                return WebhookResult::ok(202);
            }

            $error = sprintf(
                'Unexpected HTTP %d from frontend webhook: %s',
                $response->status(),
                mb_substr($response->body(), 0, 500),
            );
            Log::warning('Static site webhook rejected.', [
                'url'    => $url,
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
            ]);
            return WebhookResult::failure($response->status(), $error);

        } catch (ConnectionException $e) {
            $error = 'Connection failed: ' . $e->getMessage();
            Log::error('Static site webhook connection error.', ['url' => $url, 'error' => $e->getMessage()]);
            return WebhookResult::failure(0, $error);

        } catch (\Throwable $e) {
            $error = 'Unexpected error: ' . $e->getMessage();
            Log::error('Static site webhook unexpected error.', ['url' => $url, 'error' => $e->getMessage()]);
            return WebhookResult::failure(0, $error);
        }
    }
}
