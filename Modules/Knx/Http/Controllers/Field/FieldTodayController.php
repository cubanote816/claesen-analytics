<?php

namespace Modules\Knx\Http\Controllers\Field;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\FieldTodayJobResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\FieldTodayService;

/**
 * `GET /field/today` — the day's jobs, ready to be cached for offline use.
 */
class FieldTodayController extends Controller
{
    public function index(Request $request, FieldTodayService $today): array
    {
        /** @var KnxEmployee $technician */
        $technician = $request->attributes->get('knx_employee');

        return $today->jobsFor($technician)
            ->map(fn (array $job): array => (new FieldTodayJobResource(
                $job['assignment'],
                $job['zone'],
                $job['tasks'],
                $job['openConflicts'],
            ))->resolve($request))
            ->all();
    }
}
