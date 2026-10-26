<?php

namespace Modules\Website\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;
use Modules\Website\Models\PublicationState;
use Modules\Website\Services\StaticSitePublicationService;

class TriggerStaticSiteRebuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // Backoff in seconds between retry attempts (30 s → 60 s → 120 s).
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int    $siteId,
        public readonly string $dispatchKey,
        public readonly string $reason,
        public readonly bool   $force,
        /**
         * Optional per-key publish: site_settings key names to limit the
         * rebuild to. Null means full sync (unchanged historical behaviour).
         * Carried as plain scalar data so SerializesModels round-trips it
         * untouched across queue/retry — it plays no part in the
         * dispatch_key supersede check below, so it cannot change what
         * "stale" means.
         */
        public readonly ?array $keys = null,
    ) {}

    public function handle(StaticSitePublicationService $service): void
    {
        // F3/CLA-472 (ADR D6): this job is the first real consumer that
        // needs BelongsToSite-scoped queries to resolve correctly inside a
        // background job — PublicationState::current($this->siteId) below
        // would otherwise throw MissingOrganizationContext (no HTTP
        // middleware or authenticated user exists here to have set one).
        // Setting it explicitly from the job's own scalar payload is exactly
        // what D6 already prescribed for this situation.
        app(OrganizationContext::class)->setSite(Site::query()->find($this->siteId));

        $state = PublicationState::current($this->siteId);

        // Debounce: a newer requestRebuild() generated a different dispatch_key.
        // This job is stale — abort silently. The newer job will handle the rebuild.
        if ($state->dispatch_key !== $this->dispatchKey) {
            Log::info('Static site rebuild job superseded — aborting.', [
                'our_key'     => $this->dispatchKey,
                'current_key' => $state->dispatch_key,
            ]);
            return;
        }

        // Marca de agua de «qué cambió»: el id más alto del log **antes** de pedir el rebuild.
        // Se toma antes y no después a propósito — un cambio que llegue mientras el webhook
        // viaja queda del lado de «pendiente», que es el error seguro: mostrar un cambio ya
        // publicado molesta, esconder uno pendiente hace creer que la web está al día.
        $watermark = ActivityLogEntry::query()->max('id');

        $result = $service->sendWebhook($this->siteId, $this->reason, $this->force, $this->keys);

        if ($result->success) {
            // Re-check before marking accepted: another admin save may have
            // arrived during the webhook call and reset the dispatch_key to
            // a new value. If so, leave the state as-is (pending) so the
            // new job handles the next rebuild.
            $state->refresh();
            if ($state->dispatch_key === $this->dispatchKey) {
                // Una publicación por sección no puede mover la marca de agua de todo el sitio:
                // sólo cubrió sus propias claves. Con claves se acepta sin tocarla, para que los
                // badges de las demás secciones (y del sitio) sigan contando desde la última
                // publicación que sí las cubrió.
                $publishedKeys = $this->keys === [] ? null : $this->keys;

                $state->markAccepted($publishedKeys === null ? $watermark : null);

                $service->recordPublicationEvent('accepted', $this->siteId, [
                    'dispatch_key' => $this->dispatchKey,
                    'reason'       => $this->reason,
                    // La cobertura de la publicación: sin claves fue de todo el sitio, con
                    // claves de las que viajan acá (el sitio trata «sin claves» como sync
                    // completo, y el evento registrado dice lo mismo que lo que recibió).
                    // La marca de agua capturada va al lado: es el corte desde el que cuenta
                    // cada sección que esta publicación cubrió, y así no se infiere.
                    'keys'         => $publishedKeys,
                    'watermark'    => $watermark,
                ]);
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
        app(OrganizationContext::class)->setSite(Site::query()->find($this->siteId));

        $state = PublicationState::current($this->siteId);

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

        // El resultado, en el log de actividad: sin esto la solicitud queda sin desenlace y el
        // historial no puede responder "¿y qué pasó con esa publicación?".
        app(StaticSitePublicationService::class)->recordPublicationEvent('failed', $this->siteId, [
            'dispatch_key' => $this->dispatchKey,
            'reason'       => $this->reason,
            'error'        => sprintf('[%s] %s', get_class($exception), $exception->getMessage()),
        ]);

        Log::error('Static site rebuild job exhausted all retries.', [
            'dispatch_key' => $this->dispatchKey,
            'reason'       => $this->reason,
            'error'        => $exception->getMessage(),
        ]);
    }
}
