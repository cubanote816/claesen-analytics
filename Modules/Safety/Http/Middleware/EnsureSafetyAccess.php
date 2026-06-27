<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSafetyAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Niet geautoriseerd voor deze actie.'], 403);
        }

        // 1. Token ability check only applies to Bearer-authenticated requests.
        //    Session-authenticated SPAs use a TransientToken — rely on role check below.
        if ($user->currentAccessToken() instanceof \Laravel\Sanctum\PersonalAccessToken
            && ! $user->tokenCan('role:safety-access')) {
            return response()->json([
                'message' => 'Niet geautoriseerd voor deze actie.'
            ], 403);
        }

        // 2. Double-check the user's real-time attribute/role in the DB
        if (! $user->hasAnyRole(['project_manager', 'super_admin', 'admin'])) {
            return response()->json([
                'message' => 'Je hebt geen toegang tot de veiligheidsinspecties.'
            ], 403);
        }

        // 3. Block accounts that have not completed password setup.
        if (! $user->hasCompletedPasswordSetup()) {
            return response()->json([
                'message' => 'Account setup required. Complete password activation first.',
            ], 403);
        }

        return $next($request);
    }
}
