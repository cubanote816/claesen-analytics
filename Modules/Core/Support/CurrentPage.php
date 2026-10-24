<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The named route of the page the current code is serving, also during Livewire requests.
 *
 * Filament enforces a widget's canView() on every Livewire request, where request()->routeIs()
 * sees the update endpoint instead of the page. Widgets that use canView() for placement (show
 * on one page only) must ask about the page the component was rendered on.
 *
 * On a Livewire request that page comes from `memo.path` / `memo.method` of the component
 * snapshot, which Livewire signs with a checksum and verifies before touching the component.
 * Fails closed: any request whose page cannot be resolved matches no route name.
 */
final class CurrentPage
{
    public static function is(string ...$routeNames): bool
    {
        $route = self::route();

        return $route !== null && $route->named(...$routeNames);
    }

    private static function route(): ?Route
    {
        if (! Livewire::isLivewireRequest()) {
            return request()->route();
        }

        $path = Livewire::originalPath();
        $method = Livewire::originalMethod();

        if (! is_string($path) || ! is_string($method) || $path === 'POST') {
            return null;
        }

        try {
            return app('router')->getRoutes()->match(Request::create('/' . ltrim($path, '/'), $method));
        } catch (HttpException) {
            return null;
        }
    }
}
