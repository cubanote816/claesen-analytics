<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F3/CLA-471 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Caché, ETag o estrategia equivalente" for the public v1/website/*
 * endpoints. Only touches successful (2xx) GET/HEAD responses — a POST
 * (consultations/contact-email) or a non-2xx response (including the
 * sanitized error envelope from bootstrap/app.php's exceptions renderer)
 * is never cacheable and passes through untouched.
 *
 * The ETag is a hash of the response body, computed after the response is
 * built — this is deliberately not tied to a database "last updated"
 * timestamp (which would need per-endpoint plumbing: projects, settings
 * and announcements each have their own notion of "changed"). A
 * conditional request (If-None-Match) that matches gets a bodyless 304,
 * saving bandwidth without needing that per-endpoint wiring.
 */
class SetPublicApiCacheHeaders
{
    private const MAX_AGE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return $response;
        }

        $etag = '"'.sha1((string) $response->getContent()).'"';
        $response->setEtag($etag, weak: false);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE_SECONDS);

        // Symfony\Component\HttpFoundation\Response::isNotModified() mutates
        // $response in place (status 304, body/headers stripped) when the
        // request's If-None-Match matches the ETag just set above — the
        // return value only needs checking if a caller wants to branch,
        // which nothing here does.
        $response->isNotModified($request);

        return $response;
    }
}
