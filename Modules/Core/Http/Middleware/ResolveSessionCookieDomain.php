<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * backoffice.claesen.local (Filament, sesión Azure OAuth) y *.claesen-verlichting.be
 * (service.claesen-verlichting, Sanctum SPA stateful) son dominios sin relación
 * entre sí que no pueden compartir un único SESSION_DOMAIN estático (CLA-232/233):
 * Filament necesita la cookie sin Domain fijo (exact-match del host actual),
 * mientras que el SPA necesita ".claesen-verlichting.be" para compartir la
 * cookie entre su propio origen y el de la API. Se resuelve por host en vez
 * de por .env, corriendo antes que cualquier middleware que fije cookies
 * (StartSession, VerifyCsrfToken, Sanctum stateful).
 */
class ResolveSessionCookieDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'session.domain' => str_ends_with($request->getHost(), 'claesen-verlichting.be')
                ? '.claesen-verlichting.be'
                : null,
        ]);

        return $next($request);
    }
}
