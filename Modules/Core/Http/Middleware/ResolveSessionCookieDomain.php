<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;

/**
 * backoffice.claesen.local (Filament, sesión Azure OAuth) y service.claesen-verlichting.be
 * (Sanctum SPA stateful, llama a backend.claesen-verlichting.be) son dominios sin
 * relación entre sí que no pueden compartir un único SESSION_DOMAIN estático
 * (CLA-232/233): Filament necesita la cookie sin Domain fijo (exact-match del
 * host actual), mientras que el SPA necesita ".claesen-verlichting.be" para
 * compartir la cookie entre su propio origen y el de la API.
 *
 * OJO: no se puede decidir por $request->getHost() — el túnel/proxy que trae
 * el tráfico de la API pública reescribe el Host interno a
 * "backoffice.claesen.local" antes de llegar acá (confirmado en
 * claesen-access.log: las requests de /api/v1/safety/* de la SPA aparecen
 * ahí, no en el vhost de backend.claesen-verlichting.be). Lo único que
 * sobrevive el proxy es Origin/Referer, así que se reusa la misma detección
 * que ya hace Sanctum internamente (EnsureFrontendRequestsAreStateful::
 * fromFrontend(), basada en SANCTUM_STATEFUL_DOMAINS) en vez de reinventar
 * la lógica de host — corre antes que StartSession/VerifyCsrfToken/Sanctum
 * stateful para que la cookie ya salga con el dominio correcto.
 */
class ResolveSessionCookieDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'session.domain' => EnsureFrontendRequestsAreStateful::fromFrontend($request)
                ? '.claesen-verlichting.be'
                : null,
        ]);

        return $next($request);
    }
}
