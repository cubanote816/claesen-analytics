<?php

declare(strict_types=1);

namespace Tests\Feature\ClaesenBaseline;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Feature\ClaesenBaseline\Support\MatchesBaselineSnapshot;
use Tests\TestCase;

/**
 * Freezes the complete HTTP surface of the Claesen-only backoffice: every URI,
 * route name, action and middleware stack.
 *
 * This is the primary zero-regression guard for the multi-organization program.
 * Adding organization enforcement must not move, rename or unprotect a single
 * existing Claesen route; new phases may only add routes (the Bertels panel,
 * the context switch endpoint), and every addition shows up as an explicit
 * diff in the snapshot.
 */
final class RouteInventorySnapshotTest extends TestCase
{
    use MatchesBaselineSnapshot;

    public function test_the_claesen_route_inventory_matches_the_baseline(): void
    {
        $lines = [];

        foreach (RouteFacade::getRoutes() as $route) {
            $lines[] = sprintf(
                '%s %s | name=%s | action=%s | middleware=%s',
                implode('|', $route->methods()),
                $this->normalizeUri($route->uri()),
                $route->getName() ?? '-',
                $this->normalizeAction($route),
                implode(',', $this->normalizeMiddleware($route)),
            );
        }

        sort($lines);

        $this->assertMatchesBaselineSnapshot('routes', implode("\n", $lines));
    }

    public function test_the_route_count_is_reported_for_a_quick_diff(): void
    {
        // A count alone cannot prove isolation, but it makes an accidental bulk
        // change (a whole module group losing or gaining routes) obvious in CI
        // output even before reading the snapshot diff.
        $this->assertMatchesBaselineSnapshot(
            'routes-count',
            'total_routes='.count(RouteFacade::getRoutes()->getRoutes())
        );
    }

    private function normalizeUri(string $uri): string
    {
        // Livewire 4 derives its update endpoint from the application key, so the
        // path differs per environment and must not be part of the baseline.
        return preg_replace('#^livewire-[0-9a-f]+#', 'livewire-<hash>', $uri) ?? $uri;
    }

    private function normalizeAction(Route $route): string
    {
        $action = $route->getActionName();

        return $action === 'Closure' ? 'Closure' : $action;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeMiddleware(Route $route): array
    {
        return array_map(
            static function (mixed $middleware): string {
                if (! is_string($middleware)) {
                    return 'Closure';
                }

                // Keep parameters (throttle:5,1 / organization:claesen) — they are
                // part of the protection being frozen — but drop namespaces so the
                // snapshot stays readable.
                [$class, $parameters] = array_pad(explode(':', $middleware, 2), 2, null);
                $short = str_contains($class, '\\')
                    ? substr($class, strrpos($class, '\\') + 1)
                    : $class;

                return $parameters === null ? $short : $short.':'.$parameters;
            },
            $route->gatherMiddleware()
        );
    }
}
