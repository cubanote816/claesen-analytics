<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\EmployeeResource;
use Modules\Knx\Models\KnxEmployee;

/**
 * `GET /employees` — the people of this domain the office can assign work to (KNX-3).
 *
 * The endpoint exists because `POST /projects` accepts `leadEmployeeId` and validates
 * `exists:knx_employees,id`, but nothing listed employees: the only people list was
 * `GET /technicians`, which is `KnxEmployee::field()->active()` — field staff only.
 * The result was that the office could send a lead id but could not build the selector
 * with real data, so a new project came out without a lead.
 *
 * Defaults to office staff (`role=office`) because that is the selector this route
 * serves; `role=field` answers the same shape for callers that need the other half.
 * Only active people are listed: an inactive employee cannot be picked.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): array
    {
        $validated = $request->validate([
            'role' => ['sometimes', 'nullable', 'string', 'in:office,field'],
        ]);

        $role = $validated['role'] ?? KnxEmployee::KIND_OFFICE;

        $employees = KnxEmployee::query()
            ->when(
                $role === KnxEmployee::KIND_FIELD,
                fn ($query) => $query->field(),
                fn ($query) => $query->office(),
            )
            ->active()
            // Stable order, like the technician list: the office fixture shows the
            // people in insertion order and the selector should not shuffle them.
            ->orderBy('id')
            ->get();

        return EmployeeResource::list($employees, $request);
    }
}
