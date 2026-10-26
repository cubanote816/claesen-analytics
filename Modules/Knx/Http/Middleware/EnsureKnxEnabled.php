<?php

namespace Modules\Knx\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single route-level gate of the KNX release flag (`knx.enabled`, env KNX_ENABLED).
 *
 * KNX is not ready for production, so while the flag is off every KNX route answers a
 * plain 404 — before authentication, so neither its existence nor its auth rules leak.
 * The routes stay registered (the route inventory does not change with the flag); only
 * the answer does. Read through config(), never env(), so `config:cache` is honoured.
 *
 * Alias: `knx.enabled` (bootstrap/app.php).
 */
class EnsureKnxEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('knx.enabled'), 404);

        return $next($request);
    }
}
