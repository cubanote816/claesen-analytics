<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * backoffice.claesen.local (Filament, sesión Azure OAuth) y service.claesen-verlichting.be
 * (Sanctum SPA stateful, llama a backend.claesen-verlichting.be) son dominios sin
 * relación entre sí que no pueden compartir un único SESSION_DOMAIN estático
 * (incidente de producción, sesión 2026-07-06): Filament necesita la cookie sin
 * Domain fijo (exact-match del host actual), mientras que el SPA de producción
 * necesita ".claesen-verlichting.be" para compartir la cookie entre su propio
 * origen y el de la API.
 *
 * OJO: no se puede decidir por $request->getHost() — el túnel/proxy que trae
 * el tráfico de la API pública reescribe el Host interno a
 * "backoffice.claesen.local" antes de llegar acá (confirmado en
 * claesen-access.log: las requests de /api/v1/safety/* de la SPA aparecen
 * ahí, no en el vhost de backend.claesen-verlichting.be). Lo único que
 * sobrevive el proxy es Origin/Referer.
 *
 * No alcanza con "es un frontend stateful" (EnsureFrontendRequestsAreStateful::
 * fromFrontend(), basado en SANCTUM_STATEFUL_DOMAINS) — ese set también incluye
 * los orígenes de dev local (localhost:5173/5174, ver .env), que necesitan la
 * cookie sin Domain fijo igual que Filament (el navegador está en "localhost",
 * nunca va a mandar de vuelta una cookie con Domain=.claesen-verlichting.be).
 * Por eso se mira específicamente el host del Origin/Referer contra el sufijo
 * de producción, no el resultado genérico de fromFrontend() (CLA-234).
 */
class ResolveSessionCookieDomain
{
    private const PRODUCTION_DOMAIN_SUFFIX = 'claesen-verlichting.be';

    public function handle(Request $request, Closure $next): Response
    {
        $frontendHost = $this->resolveFrontendHost($request);
        $isProductionFrontend = $frontendHost === self::PRODUCTION_DOMAIN_SUFFIX
            || ($frontendHost !== null && str_ends_with($frontendHost, '.'.self::PRODUCTION_DOMAIN_SUFFIX));

        config([
            'session.domain' => $isProductionFrontend
                ? '.'.self::PRODUCTION_DOMAIN_SUFFIX
                : null,
        ]);

        return $next($request);
    }

    private function resolveFrontendHost(Request $request): ?string
    {
        $uri = $request->headers->get('origin') ?? $request->headers->get('referer');

        if (! $uri) {
            return null;
        }

        return parse_url($uri, PHP_URL_HOST);
    }
}
