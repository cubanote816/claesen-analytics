<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers\Field;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Http\Resources\FieldVisitResultResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxVisit;
use Modules\Knx\Services\FieldProjectService;
use Modules\Knx\Services\FieldVisitService;

/**
 * `POST /field/projects/{code}/visits` — closing a visit (V11.e, CLA-609),
 * idempotent by the app's own `clientId`.
 *
 * The type table of the field contract says which fields apply to each closure, but
 * nothing here refuses an unexpected one: the four lists are accepted for any type,
 * because dropping what a technician wrote down is worse than storing a list the
 * office did not expect. The type is what tells the office how to read them.
 */
class FieldVisitController extends Controller
{
    public function __construct(
        private readonly FieldProjectService $projects,
        private readonly FieldVisitService $visits,
    ) {}

    public function store(Request $request, string $code): JsonResponse
    {
        /** @var KnxEmployee $technician */
        $technician = $request->attributes->get('knx_employee');

        // Scope first, like every other field write.
        $project = $this->projects->resolveAuthorized($technician, $code);

        $input = $request->validate([
            'clientId' => ['required', 'string', 'max:64'],
            'projectCode' => ['required', 'string', 'max:32'],
            // The app's own label; the project row is the authority.
            'projectName' => ['nullable', 'string', 'max:150'],
            'room' => ['nullable', 'string', 'max:150'],
            'type' => ['required', 'string', 'in:'.implode(',', KnxVisit::TYPES)],
            'workDone' => ['required', 'string', 'max:5000'],
            // Minutes spent on site. The first time this domain records time, and what
            // makes the office's `hours` report possible.
            'minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'pending' => ['sometimes', 'array', 'max:50'],
            'pending.*' => ['string', 'max:200'],
            'reservations' => ['sometimes', 'array', 'max:50'],
            'reservations.*' => ['string', 'max:200'],
            'verifiedFunctions' => ['sometimes', 'array', 'max:100'],
            'verifiedFunctions.*' => ['string', 'max:200'],
            'documents' => ['sometimes', 'array', 'max:50'],
            'documents.*' => ['string', 'max:200'],
            'signedBy' => ['nullable', 'string', 'max:150'],
            'capturedAt' => ['required', 'date'],
        ]);

        if ($input['projectCode'] !== $code) {
            throw ValidationException::withMessages(['projectCode' => [__('knx::field.project_mismatch')]]);
        }

        $result = $this->visits->close($technician, $project, $input);

        return response()->json(
            FieldVisitResultResource::make($result['visit'])->resolve($request),
            $result['created'] ? 201 : 200,
        );
    }
}
