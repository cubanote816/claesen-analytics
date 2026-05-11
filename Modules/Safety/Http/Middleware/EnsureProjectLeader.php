<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectLeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. Check if the token itself was issued with the correct ability
        if (! $user || ! $user->tokenCan('role:project_leader')) {
            return response()->json([
                'message' => 'Niet geautoriseerd voor deze actie.'
            ], 403);
        }

        // 2. Double-check the user's real-time attribute/role in the DB 
        if (! $user->hasRole('project_leader')) {
            return response()->json([
                'message' => 'Je bent niet (meer) toegewezen als projectleider.'
            ], 403);
        }

        return $next($request);
    }
}
