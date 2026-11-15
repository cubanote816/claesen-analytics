<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Knx\Models\KnxEmployee;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration only: the signed-in employee (set by EnsureKnxApp) must have the
 * `admin` role. Everyone else gets a 403 `knx_admin_required`.
 */
class EnsureKnxAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->attributes->get('knx_employee');

        if (! $employee instanceof KnxEmployee || ! $employee->isAdmin()) {
            return response()->json([
                'message' => 'This action is reserved for administrators.',
                'code' => 'knx_admin_required',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
