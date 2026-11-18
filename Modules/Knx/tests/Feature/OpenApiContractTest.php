<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Knx\Http\Middleware\AssertDocumentedSuccessCode;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The KNX module's contract is a committed artifact, not a promise in a chat
 * (KNX-4): `docs/Knx/openapi.yaml` freezes the shape of the 52 `api/v1/knx/*`
 * routes, and this test fails if the spec and the real routes drift in either
 * direction. A plain markdown description can go stale in silence; this cannot.
 *
 * Same two-way check as Modules/Website/tests/Feature/OpenApiContractTest.php:
 * every documented path+method must resolve to a registered route, and every
 * registered `api/v1/knx/*` route must be documented.
 *
 * KNX-20 adds the third leg: existence of a path is not the promise. A controller
 * that returned `204` while the spec said `200` survived this test for months, so
 * `AssertDocumentedSuccessCode` now checks every 2xx the suite really produces
 * against the declared responses in any feature test that hits a KNX route.
 */
final class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    private const PREFIX = 'api/v1/knx';

    public function test_every_documented_path_is_a_real_registered_route(): void
    {
        $spec = Yaml::parseFile(base_path('docs/Knx/openapi.yaml'));

        foreach ($spec['paths'] as $path => $methods) {
            // Registered route URIs keep their literal {param} placeholder; both
            // sides are normalized to the same shape rather than substituted.
            $uri = ltrim(self::PREFIX.($path === '/' ? '' : $path), '/');
            $uri = (string) preg_replace('/\{[^}]+\}/', '{param}', $uri);

            foreach (array_keys($methods) as $method) {
                $matched = collect(Route::getRoutes())->contains(
                    fn ($route) => (string) preg_replace('/\{[^}]+\}/', '{param}', $route->uri()) === $uri
                        && in_array(strtoupper($method), $route->methods(), true)
                );

                $this->assertTrue(
                    $matched,
                    "Documented {$method} {$path} has no matching registered route.",
                );
            }
        }
    }

    public function test_every_registered_knx_route_is_documented(): void
    {
        $spec = Yaml::parseFile(base_path('docs/Knx/openapi.yaml'));
        $documented = [];

        foreach ($spec['paths'] as $path => $methods) {
            $uri = self::PREFIX.($path === '/' ? '' : $path);

            foreach (array_keys($methods) as $method) {
                $documented[] = strtoupper($method).' '.preg_replace('/\{[^}]+\}/', '{param}', ltrim($uri, '/'));
            }
        }

        $registered = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), self::PREFIX))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn ($method) => $method === 'HEAD')
                ->map(fn ($method) => $method.' '.preg_replace('/\{[^}]+\}/', '{param}', $route->uri())))
            ->unique()
            ->values()
            ->all();

        sort($documented);
        sort($registered);

        $this->assertSame(
            $documented,
            $registered,
            'docs/Knx/openapi.yaml has drifted from the real registered api/v1/knx/* routes.',
        );
    }

    /**
     * The status-code leg only works while the guard is wired into the suite, so its
     * registration is asserted here: removing it from the service provider must fail
     * this test instead of silently weakening the contract.
     */
    public function test_the_response_code_guard_is_registered_for_the_suite(): void
    {
        $middleware = app(Kernel::class)->getGlobalMiddleware();

        $this->assertContains(
            AssertDocumentedSuccessCode::class,
            $middleware,
            'AssertDocumentedSuccessCode is not registered: the spec no longer checks response codes.',
        );
    }

    /**
     * Every operation has to declare at least one 2xx, or the guard has nothing to
     * compare a real success against.
     */
    public function test_every_operation_declares_at_least_one_success_code(): void
    {
        $spec = Yaml::parseFile(base_path('docs/Knx/openapi.yaml'));

        foreach ($spec['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $responses = $methods[$method]['responses'] ?? [];
                $success = array_filter(
                    array_keys($responses),
                    fn ($code) => is_numeric($code) && (int) $code >= 200 && (int) $code < 300,
                );

                $this->assertNotEmpty(
                    $success,
                    "Documented {$method} {$path} declares no 2xx success code.",
                );
            }
        }
    }
}
