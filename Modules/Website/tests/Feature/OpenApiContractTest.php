<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * F3/CLA-471 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "OpenAPI/contract tests y ejemplos" — a real automated check that
 * docs/api/website-v1-openapi.yaml stays truthful: every documented
 * path+method must resolve to an actually-registered v1/website/* route,
 * and every actually-registered v1/website/* route must be documented.
 * A drift in either direction fails this test — the spec can't silently
 * go stale the way a plain markdown description could.
 */
final class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_documented_path_is_a_real_registered_route(): void
    {
        $spec = Yaml::parseFile(base_path('docs/api/website-v1-openapi.yaml'));

        foreach ($spec['paths'] as $path => $methods) {
            // Registered route URIs keep their literal {param} placeholder
            // (Laravel never resolves it in ->uri()) — normalize both sides
            // to the same placeholder shape rather than substituting a fake
            // value, which would never match a real route's own URI string.
            $uri = ltrim('v1/website'.($path === '/' ? '' : $path), '/');
            $uri = preg_replace('/\{[^}]+\}/', '{param}', $uri);

            foreach (array_keys($methods) as $method) {
                $matched = collect(Route::getRoutes())->contains(
                    fn ($route) => preg_replace('/\{[^}]+\}/', '{param}', $route->uri()) === $uri
                        && in_array(strtoupper($method), $route->methods(), true)
                );

                $this->assertTrue(
                    $matched,
                    "Documented {$method} {$path} has no matching registered route."
                );
            }
        }
    }

    public function test_every_registered_v1_website_route_is_documented(): void
    {
        $spec = Yaml::parseFile(base_path('docs/api/website-v1-openapi.yaml'));
        $documented = [];

        foreach ($spec['paths'] as $path => $methods) {
            $uri = 'v1/website'.($path === '/' ? '' : $path);
            foreach (array_keys($methods) as $method) {
                $documented[] = strtoupper($method).' '.preg_replace('/\{[^}]+\}/', '{param}', ltrim($uri, '/'));
            }
        }

        $registered = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'v1/website'))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn ($m) => $m === 'HEAD')
                ->map(fn ($m) => $m.' '.preg_replace('/\{[^}]+\}/', '{param}', $route->uri())))
            ->unique()
            ->values()
            ->all();

        sort($documented);
        sort($registered);

        $this->assertSame($documented, $registered, 'docs/api/website-v1-openapi.yaml has drifted from the real registered v1/website/* routes.');
    }
}
