<?php

namespace Modules\Website\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;
use Modules\Core\Models\Site;
use Modules\Website\App\Enums\PublicationStatus;
use Modules\Website\DTOs\WebhookResult;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Modules\Website\Models\PublicationState;

class StaticSitePublicationService
{
    /**
     * Nombre del log de actividad donde queda constancia de las publicaciones.
     *
     * Es el argumento del helper `activity('…')`, **no** un parámetro de `log()`: en
     * spatie/activitylog v5 el nombre del log es el del helper, y pasarlo a `log()` archiva
     * todo bajo "default" — un rastro que parece auditoría y no se puede filtrar por evento.
     */
    public const PUBLICATION_LOG = 'static_site_publication';

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
     *
     * $keys optionally limits the rebuild to specific site_settings keys
     * (per-key publish). Null or empty means a full sync, exactly as before
     * this parameter existed.
     */
    public function requestRebuild(?int $siteId = null, string $reason = 'content_changed', bool $force = false, ?array $keys = null): void
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
        TriggerStaticSiteRebuildJob::dispatch($siteId, $dispatchKey, $reason, $force, $keys)
            ->delay(now()->addSeconds($debounce))
            ->afterCommit();

        Log::info('Static site rebuild requested.', [
            'site_id'      => $siteId,
            'reason'       => $reason,
            'force'        => $force,
            'dispatch_key' => $dispatchKey,
            'fires_at'     => now()->addSeconds($debounce)->toIso8601String(),
        ]);

        $this->recordPublicationEvent('requested', $siteId, [
            'dispatch_key' => $dispatchKey,
            'reason'       => $reason,
            'force'        => $force,
        ]);
    }

    /**
     * Deja constancia de una publicación en el log de actividad.
     *
     * Se registra la **solicitud** y el **resultado** por separado, y es deliberado: la
     * solicitud corre con sesión —alguien apretó el botón— y el resultado corre en un worker
     * donde no hay usuario, así que **el actor sólo existe en la primera**. La clave de despacho
     * va en las propiedades de las dos para poder enlazarlas.
     *
     * Público porque el job es el otro llamador: con dos copias del registro, una se quedaría
     * atrás.
     *
     * `tap()` no es adorno: el autocompletado de `site_id` de `ActivityLogEntry` lo lee del
     * **sujeto**, y un `Site` no tiene columna `site_id` — sin esto la fila quedaría sin sitio y
     * la pantalla no podría filtrar por el sitio del panel.
     *
     * @param array<string, mixed> $properties
     */
    public function recordPublicationEvent(string $event, int $siteId, array $properties): void
    {
        $site = $this->resolveSite($siteId);

        if (! $site) {
            return;
        }

        $logger = activity(self::PUBLICATION_LOG)
            ->performedOn($site)
            ->withProperties(array_merge(['event' => $event], $properties))
            ->tap(function ($activity) use ($site): void {
                $activity->site_id = $site->id;
            });

        // En el worker no hay usuario, y ahí el resultado simplemente no tiene actor; la
        // solicitud es la que lo aporta.
        if ($user = auth()->user()) {
            $logger->causedBy($user);
        }

        $logger->log('site publication '.$event);
    }

    /**
     * Mark a site's content as changed WITHOUT asking for a rebuild.
     *
     * This is the deliberate-publishing model the user chose on 2026-10-06: saving content
     * makes the site dirty, and a human decides when that becomes a publication.
     * requestRebuild() is the other half — the explicit action, still what a manual
     * "publish now" calls.
     *
     * Saving must keep marking. If an edit left no trace the client would lose the only
     * signal that something is waiting to be published, and "editing" would collapse back
     * into "published" — the distinction this whole flow exists to draw.
     *
     * Gated exactly like requestRebuild(): with the pipeline switched off nothing could ever
     * publish, so a pending badge would be one nobody can clear.
     *
     * Safe to call from model observers — never throws, no queue, no network.
     */
    public function markContentChanged(?int $siteId = null): void
    {
        if (!config('static_site.enabled')) {
            return;
        }

        $state = PublicationState::current($siteId);

        // Already dirty in this cycle: markPending() would rewrite the same row. This runs
        // once per saved row — the settings screen saves every allowed key on each submit —
        // so the guard keeps one edit from producing a burst of identical UPDATEs.
        if ($state->status === PublicationStatus::PENDING && $state->pending_since !== null) {
            return;
        }

        $state->markPending();
    }

    /**
     * Call the frontend rebuild webhook and return the result.
     *
     * Called by TriggerStaticSiteRebuildJob after the debounce window.
     * 202 = request accepted by frontend (build may still be in progress).
     * Real build status is at the resolved site's own health_url (or the
     * global config('static_site.health_url') fallback for Claesen).
     *
     * $keys enables per-key publish: when a non-empty list of site_settings
     * key names is given, it is included in the payload and the site writes
     * only those keys' fields over its published snapshot. Null or empty
     * omits the field entirely — the site treats an absent `keys` as a full
     * sync, so an empty array must never leak into the payload (that would
     * silently change behaviour for every existing full-sync caller).
     */
    public function sendWebhook(int $siteId, string $reason, bool $force, ?array $keys = null): WebhookResult
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

        // Absent-or-empty means full sync: the field only exists when there is
        // something to limit to. Order of the given keys is preserved — the
        // site applies them in payload order.
        if ($keys !== null && $keys !== []) {
            $payload['keys'] = array_values($keys);
        }

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
