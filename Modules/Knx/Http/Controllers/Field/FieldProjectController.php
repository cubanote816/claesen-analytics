<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers\Field;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\FieldDeviceResource;
use Modules\Knx\Http\Resources\FieldPlanResource;
use Modules\Knx\Http\Resources\FieldProjectResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\FieldProjectService;

/**
 * `GET /field/projects/{code}` and `…/plans` — what the app caches to register and
 * to work with no connection (V11.b, CLA-609).
 *
 * Both go through `FieldProjectService`, so the project's scope rule is answered
 * once for the whole `/field/*` surface.
 */
class FieldProjectController extends Controller
{
    public function __construct(private readonly FieldProjectService $projects) {}

    public function show(Request $request, string $code): FieldProjectResource
    {
        return FieldProjectResource::make(
            $this->projects->resolveAuthorized($this->technician($request), $code),
        );
    }

    public function plans(Request $request, string $code): array
    {
        $project = $this->projects->resolveAuthorized($this->technician($request), $code);

        return FieldPlanResource::list($this->projects->plansFor($project), $request);
    }

    /**
     * `GET /field/projects/{code}/devices` (V11.f, CLA-635).
     *
     * Answers the same `FieldDevice` shape the registration does, so the app has one
     * device shape. What it adds over the registration is the whole list: the card's
     * progress and the plan's per-room counts are both derived from it.
     */
    public function devices(Request $request, string $code): array
    {
        $project = $this->projects->resolveAuthorized($this->technician($request), $code);

        return FieldDeviceResource::list($this->projects->devicesFor($project), $request);
    }

    private function technician(Request $request): KnxEmployee
    {
        /** @var KnxEmployee $technician */
        $technician = $request->attributes->get('knx_employee');

        return $technician;
    }
}
