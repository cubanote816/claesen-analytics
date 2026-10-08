<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website;

use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Intelligence\Models\TranslationState;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\PagePublication;
use Modules\Website\Models\PublicationState;
use Modules\Website\Models\SiteSetting;
use Modules\Website\Services\StaticSitePublicationService;

/**
 * Los números de los badges de la navegación (WU-S1 del rediseño del backoffice).
 *
 * **Una sola fuente a propósito.** El brief pide «el número de cambios sin publicar de esa
 * página», y eso significa una cosa concreta: cambios registrados **después de la última
 * publicación que cubrió esa página**. Para un contenido que no se publica por partes (proyecto,
 * aviso, traducción) ese corte es la marca de agua del WU4
 * (`PublicationState::last_published_activity_id`). Para una sección de ajustes el corte es el
 * de **su última publicación**: la marca de agua de todo el sitio no puede moverla bien una
 * publicación que sólo sacó las claves de esa sección, así que el corte sale de la cobertura
 * registrada en los eventos `accepted` — y sin historial por sección, se vuelve al corte de
 * siempre.
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

        $sectionKeys = WebsiteSettingSections::keys($section);

        $rows = SiteSetting::query()
            ->where('site_id', $siteId)
            ->whereIn('key', $sectionKeys)
            ->pluck('id');

        if ($rows->isEmpty()) {
            return 0;
        }

        return self::changesSincePublication($siteId, SiteSetting::class, $rows->all(),
            self::sectionWatermark($siteId, $sectionKeys));
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
     * @param int|null $watermarkOverride el corte por sección cuando lo resuelve el llamador;
     *        `null` significa «usa la marca de agua de todo el sitio», que es el corte de siempre
     */
    private static function changesSincePublication(int $siteId, string $subjectType, array $subjectIds = [], ?int $watermarkOverride = null): int
    {
        $watermark = $watermarkOverride
            ?? PublicationState::current($siteId)->last_published_activity_id;

        return ActivityLogEntry::query()
            ->where('site_id', $siteId)
            ->where('subject_type', $subjectType)
            ->when($subjectIds !== [], fn ($query) => $query->whereIn('subject_id', $subjectIds))
            ->when($watermark !== null, fn ($query) => $query->where('id', '>', $watermark))
            ->count();
    }

    /**
     * El corte **de una sección**: la marca de agua de la última publicación aceptada que cubrió
     * sus claves.
     *
     * La cobertura está registrada, no inferida: el job deja en el evento `accepted` las claves
     * que viajaron (`keys`) y la marca de agua que capturó (`watermark`). Sin claves la
     * publicación fue de todo el sitio y cubre cualquier sección; con claves, la cubre si alguna
     * de las de la sección viajó en la carga.
     *
     * Filas anteriores a la publicación por sección no traen ni claves ni marca de agua propia:
     * cubren todo el sitio pero no saben hasta dónde, así que devuelven `null` y el corte es el
     * de todo el sitio — exactamente el que el badge usó siempre.
     *
     * @param list<string> $sectionKeys
     */
    private static function sectionWatermark(int $siteId, array $sectionKeys): ?int
    {
        $event = ActivityLogEntry::query()
            ->where('site_id', $siteId)
            ->where('log_name', StaticSitePublicationService::PUBLICATION_LOG)
            ->where('properties->event', 'accepted')
            ->where(function ($query) use ($sectionKeys): void {
                // `properties->keys` es NULL también cuando la clave no existe: cubre tanto un
                // evento nuevo sin claves (todo el sitio) como una fila vieja sin cobertura.
                $query->whereNull('properties->keys');

                foreach ($sectionKeys as $key) {
                    $query->orWhereRaw(
                        'JSON_CONTAINS(JSON_EXTRACT(properties, ?), ?)',
                        ['$.keys', json_encode($key)],
                    );
                }
            })
            ->orderByDesc('id')
            ->first();

        return $event === null ? null : ($event->properties['watermark'] ?? null);
    }
}
