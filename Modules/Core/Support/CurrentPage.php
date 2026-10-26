<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Throwable;

/**
 * The named route of the page the current code is serving, also during Livewire requests.
 *
 * Filament enforces a widget's canView() on every Livewire request, where request()->routeIs()
 * sees the update endpoint instead of the page. Widgets that use canView() for placement (show
 * on one page only) must ask about the page the component was rendered on.
 *
 * On the Livewire update route that page is the `memo.path` / `memo.method` of a snapshot whose checksum
 * Livewire verified (see VerifiedLivewirePage); the raw request payload is never read. Fails closed: when
 * no verified page is known, or it cannot be matched, no route name matches.
 */
final class CurrentPage
{
    public static function is(string ...$routeNames): bool
    {
        try {
            $route = self::route();

            return $route !== null && $route->named(...$routeNames);
        } catch (Throwable) {
            return false;
        }
    }

    private static function route(): ?Route
    {
        if (! app(HandleRequests::class)->isLivewireRoute()) {
            return request()->route();
        }

        $page = app(VerifiedLivewirePage::class)->for(request());

        if ($page === null || trim($page[0]) === '' || trim($page[1]) === '') {
            return null;
        }

        return app('router')->getRoutes()->match(Request::create('/' . ltrim($page[0], '/'), $page[1]));
    }
}
