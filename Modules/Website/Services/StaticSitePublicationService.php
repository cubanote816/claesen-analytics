<?php

namespace Modules\Website\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;
use Modules\Core\Models\Site;
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
     *
     * F3/CLA-472: $siteId defaults to Claesen (every existing caller omits
     * it), keeping its own dispatch_key/debounce/webhook target completely
     * independent of any other site's — a failure or a slow build on one
     * site's endpoint can never block or clobber another's.
     */
    public function requestRebuild(?int $siteId = null, string $reason = 'content_changed', bool $force = false): void
    {
        if (!config('static_site.enabled')) {
            return;
        }

        $siteId ??= Site::claesenId();

        $state = PublicationState::current($siteId);
        $state->markPending();

        $dispatchKey = Str::uuid()->toString();
        $state->recordDispatch($dispatchKey);

        $debounce = $this->resolveSite($siteId)?->static_site_debounce_seconds
            ?? (int) config('static_site.debounce_seconds', 20);

        // ->afterCommit() (Illuminate\Bus\Queueable) so a rolled-back save
        // never fires a rebuild for content that was never actually
        // persisted.
        TriggerStaticSiteRebuildJob::dispatch($siteId, $dispatchKey, $reason, $force)
            ->delay(now()->addSeconds($debounce))
            ->afterCommit();

        Log::info('Static site rebuild requested.', [
            'site_id'      => $siteId,
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
     * Real build status is at the resolved site's own health_url (or the
     * global config('static_site.health_url') fallback for Claesen).
     */
    public function sendWebhook(int $siteId, string $reason, bool $force): WebhookResult
    {
        $site = $this->resolveSite($siteId);

        $url     = $site?->static_site_webhook_url ?? config('static_site.webhook_url');
        $secret  = $site?->static_site_webhook_secret ?? config('static_site.webhook_secret');
        $timeout = (int) ($site?->static_site_webhook_timeout ?? config('static_site.webhook_timeout', 3));

        if (!$url || !$secret) {
            return WebhookResult::failure(0, 'webhook_url or webhook_secret not configured for this site (nor in the global static_site config)');
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

    private function resolveSite(int $siteId): ?Site
    {
        return Site::query()->find($siteId);
    }
}
