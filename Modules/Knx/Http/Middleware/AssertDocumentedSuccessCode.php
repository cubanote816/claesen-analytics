<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * Test-only guard: every 2xx a KNX route returns during the suite must be declared
 * in `docs/Knx/openapi.yaml` (KNX-20).
 *
 * The bidirectional contract test compares paths and methods, which is why the real
 * drift of the bandeja survived it: `POST /notifications/{id}/ack` documented a `200`
 * and returned a `204` for months, and the test could not see it. This middleware runs
 * on every response generated in tests, resolves the route to its spec operation and
 * fails loudly for an undocumented success code.
 *
 * It asserts only on 2xx on purpose: error codes are many (401/404/409/413/422/429)
 * and their absence from a particular operation is not the bug this ticket fixes. It
 * is registered from `KnxServiceProvider` only when the app is running unit tests, so
 * it never runs in production.
 */
class AssertDocumentedSuccessCode
{
    /** @var array<string, array<string, list<string>>>|null */
    private static ?array $declared = null;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->assertDeclared($request, $response);

        return $response;
    }

    private function assertDeclared(Request $request, Response $response): void
    {
        $code = $response->getStatusCode();

        if ($code < 200 || $code >= 300) {
            return;
        }

        $route = $request->route();

        if ($route === null) {
            return;
        }

        $uri = (string) $route->uri();

        if (! str_starts_with($uri, 'api/v1/knx')) {
            return;
        }

        $path = self::normalize('/'.ltrim(substr($uri, strlen('api/v1/knx')), '/'));
        $method = strtolower($request->getMethod());
        $declared = $this->declared();

        $codes = $declared[$path][$method] ?? null;

        if ($codes === null || ! in_array((string) $code, $codes, true)) {
            throw new \RuntimeException(sprintf(
                'Undocumented success code: %s %s returned %d, and docs/Knx/openapi.yaml declares %s.',
                strtoupper($method),
                $path,
                $code,
                $codes === null ? 'nothing for this operation' : '['.implode(', ', $codes).']',
            ));
        }
    }

    /** @return array<string, array<string, list<string>>> */
    private function declared(): array
    {
        if (self::$declared !== null) {
            return self::$declared;
        }

        $spec = Yaml::parseFile(base_path('docs/Knx/openapi.yaml'));
        $declared = [];

        foreach ($spec['paths'] as $path => $methods) {
            $normalized = self::normalize((string) $path);

            foreach ($methods as $method => $operation) {
                if (! is_array($operation) || ! isset($operation['responses'])) {
                    continue;
                }

                foreach (array_keys($operation['responses']) as $code) {
                    if (is_numeric($code) && (int) $code >= 200 && (int) $code < 300) {
                        $declared[$normalized][strtolower((string) $method)][] = (string) $code;
                    }
                }
            }
        }

        return self::$declared = $declared;
    }

    private static function normalize(string $path): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{param}', $path);
    }
}
