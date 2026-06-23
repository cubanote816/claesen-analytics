<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsSet
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasCompletedPasswordSetup()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Account setup required. Complete password activation first.',
                ], 403);
            }

            return redirect()->route('auth.setup-password');
        }

        return $next($request);
    }
}
