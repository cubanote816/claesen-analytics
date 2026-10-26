<?php

declare(strict_types=1);

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Website\Models\Announcement;
use Modules\Website\Models\SiteSetting;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Public read-only surface for SiteSetting/Announcement. Both models use
 * BelongsToSite, so a plain query is already scoped to whatever
 * Modules\Core\Http\Middleware\ResolveRequestSite resolved for this
 * request (already registered on the whole `v1/website` route group) —
 * this controller never touches OrganizationContext directly.
 *
 * Only `key` + the resolved (locale-aware) `value` are ever serialized —
 * never `type`, `id`, `site_id`, timestamps, or anything from the Site
 * model itself (no static_site_webhook_secret/organization internals can
 * leak through here).
 */
class SiteContentController extends Controller
{
    public function settings(): JsonResponse
    {
        $settings = SiteSetting::cachedForCurrentSite();

        // Los ajustes **vacíos se omiten**. El contrato del consumidor (el validador del
        // sync del sitio) dice «non-empty string WHEN PRESENT»: un campo presente y nulo
        // lo rechaza, y con razón — no se distingue de un error. Y hay ajustes que están
        // vacíos **a propósito** porque el cliente todavía no dio el dato
        // (`vat_number`, `contact_consent_version`): el sembrado los deja ausentes por eso
        // mismo. Servirlos en null contradecía esa decisión y rompía el sync entero.
        //
        // Se conservan las listas vacías y los valores falsy que sí significan algo
        // (`[]` es «ninguna», `0`/`false` son valores). Sólo se cae lo que no puede
        // distinguirse de «no hay dato».
        $payload = $settings
            ->mapWithKeys(fn (SiteSetting $setting) => [$setting->key => $setting->resolvedValue()])
            ->reject(fn (mixed $value): bool => $value === null
                || (is_string($value) && trim($value) === ''));

        return response()->json([
            // Cast a objeto: con todos los ajustes vacíos un mapa sin entradas
            // serializa como `[]`, y eso es una lista, no un mapa.
            'data' => (object) $payload->all(),
        ]);
    }

    public function announcements(): JsonResponse
    {
        $announcements = Announcement::cachedActiveForCurrentSite();

        return response()->json([
            'data' => $announcements->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'message' => $announcement->message,
                'starts_at' => $announcement->starts_at?->toIso8601String(),
                'ends_at' => $announcement->ends_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
