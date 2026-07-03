<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Always allow logout, otherwise a user without panel access can never
        // leave the welcome page (the Filament logout route lives inside the panel).
        if ($request->route()?->named('filament.*.auth.logout')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && ! $user->hasPanelAccess()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your account does not have access to this system.',
                ], 403);
            }

            return redirect()->route('auth.no-access');
        }

        return $next($request);
    }
}
