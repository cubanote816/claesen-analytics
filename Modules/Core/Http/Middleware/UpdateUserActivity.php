<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            /** @var \Modules\Core\Models\User $user */
            $cacheKey = 'user-is-online-' . $user->id;
            
            // Only update database if more than 1 minute has passed since last update
            $lastUpdate = $user->last_active_at;
            if (!$lastUpdate || $lastUpdate->diffInMinutes(now()) >= 1) {
                // Update the user without triggering observers or updated_at if not desired
                $user->last_active_at = now();
                $user->timestamps = false;
                $user->save();
            }

            // Always update cache with a 5 minute TTL for "online" status
            Cache::put($cacheKey, true, now()->addMinutes(5));
        }

        return $next($request);
    }
}
