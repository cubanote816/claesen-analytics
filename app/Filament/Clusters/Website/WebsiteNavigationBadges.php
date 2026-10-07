<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website;

use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\PagePublication;
use Modules\Website\Models\PublicationState;
use Modules\Website\Models\SiteSetting;
use Modules\Intelligence\Models\TranslationState;

/**
 * Los números de los badges de la navegación (WU-S1 del rediseño del backoffice).
 *
 * **Una sola fuente a propósito.** El brief pide «el número de cambios sin publicar de esa
 * página», y hoy eso significa una cosa concreta: cambios registrados **después de la última
 * publicación**, del sujeto que esa página edita. La marca de agua del WU4
 * (`PublicationState::last_published_activity_id`) es exactamente ese corte, así que no hay que
 * inventar un contador por página.
 *
 * **Lo que este conteo es y lo que no.** Cuenta **cambios hechos**, no **diferencias que
 * quedan**: si alguien edita un valor y lo devuelve a como estaba publicado, el badge sigue
 * diciendo 1 hasta que esa sección se publique. Es lo que el brief pide al hablar de «cambios», y
 * es lo mismo que muestra la pantalla de historial —pinchando el badge se ven esos cambios, uno
 * por uno—, pero conviene saberlo. La versión por **diferencia** (lo que el sitio tiene contra lo
 * que el API sirve) es la del `--check` del sync, y llega con la publicación por sección (S4):
 * ahí este servicio cambia de fuente y **sólo acá**.
 *
 * Un conteo de 0 devuelve `null` y no `'0'`: un badge que dice cero es ruido en un menú.
 */
final class WebsiteNavigationBadges
{
    /**
     * Cambios sin publicar de una sección de ajustes.
     *
     * El sujeto de la actividad de un ajuste es **su fila** (`SiteSetting`), no su clave, así que
     * se resuelven primero los ids de las claves de la sección. Son dos consultas por sección
     * para un menú, y el menú se dibuja en cada request: si alguna vez pesa, esto es lo que se
     * cachea — no hay nada más que cachear.
     */
    public static function forSettingSection(string $section): int
    {
        $siteId = Site::forPanel()?->id;

        if ($siteId === null) {
            return 0;
        }

        $rows = SiteSetting::query()
            ->where('site_id', $siteId)
            ->whereIn('key', WebsiteSettingSections::keys($section))
            ->pluck('id');

        if ($rows->isEmpty()) {
            return 0;
        }

        return self::changesSincePublication($siteId, SiteSetting::class, $rows->all());
    }

    /** Cambios sin publicar de un contenido que es un modelo (proyecto, media, aviso, slot). */
    public static function forSubjectType(string $subjectType): int
    {
        $siteId = Site::forPanel()?->id;

        return $siteId === null ? 0 : self::changesSincePublication($siteId, $subjectType);
    }

    /** Solicitudes nuevas: lo que la bandeja todavía no tomó. */
    public static function forNewConsultations(): int
    {
        $siteId = Site::forPanel()?->id;

        if ($siteId === null) {
            return 0;
        }

        return ConsultationRequest::query()
            ->where('site_id', $siteId)
            ->where('status', ConsultationRequest::STATUS_NEW)
            ->count();
    }

    /**
     * Páginas aprobadas esperando salir: `reviewed` es «el cliente la leyó y la aprobó», y el
     * paso siguiente es publicarla. Es el número que dice que la compuerta tiene algo en la mano.
     */
    public static function forPagesWaitingToGoLive(): int
    {
        $siteId = Site::forPanel()?->id;

        if ($siteId === null) {
            return 0;
        }

        return PagePublication::query()
            ->where('site_id', $siteId)
            ->where('status', PagePublication::STATUS_REVIEWED)
            ->count();
    }

    /**
     * Traducciones esperando una persona: la pantalla se llama «machine translations waiting for
     * a human check», así que el badge son los estados que **no** son revisados ni publicados
     * (`machine`, `needs_review`, `stale`, `missing`, `failed`). Contar las publicadas sería
     * contar lo que ya no hay que hacer.
     */
    public static function forTranslationsWaiting(): int
    {
        $siteId = Site::forPanel()?->id;

        if ($siteId === null) {
            return 0;
        }

        return TranslationState::query()
            ->where('site_id', $siteId)
            ->whereNotIn('status', [TranslationState::STATUS_REVIEWED, TranslationState::STATUS_PUBLISHED])
            ->count();
    }

    /**
     * El corte: filas de actividad con id **posterior** a la última publicación.
     *
     * Es un id y no una fecha por la misma razón que en el WU4 —los `timestamp` de MySQL llegan al
     * segundo— y sin marca de agua (nunca se publicó) el corte es «todo lo registrado».
     *
     * @param list<int> $subjectIds limita a unas filas concretas; vacío = cualquier id del tipo
     */
    private static function changesSincePublication(int $siteId, string $subjectType, array $subjectIds = []): int
    {
        $watermark = PublicationState::current($siteId)->last_published_activity_id;

        return ActivityLogEntry::query()
            ->where('site_id', $siteId)
            ->where('subject_type', $subjectType)
            ->when($subjectIds !== [], fn ($query) => $query->whereIn('subject_id', $subjectIds))
            ->when($watermark !== null, fn ($query) => $query->where('id', '>', $watermark))
            ->count();
    }
}
