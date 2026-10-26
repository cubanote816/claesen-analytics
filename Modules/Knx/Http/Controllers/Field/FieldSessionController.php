<?php

namespace Modules\Knx\Http\Controllers\Field;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\FieldSessionResource;
use Modules\Knx\Models\KnxEmployee;

/**
 * `GET /field/session` — who is holding this phone.
 *
 * The identity is already resolved by the `EnsureKnxApp:field` middleware, which is
 * also what refuses an office token here; this only shapes the answer.
 */
class FieldSessionController extends Controller
{
    public function show(Request $request): FieldSessionResource
    {
        /** @var KnxEmployee $technician */
        $technician = $request->attributes->get('knx_employee');

        return FieldSessionResource::make($technician);
    }
}
