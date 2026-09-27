<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers\Field;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Exceptions\AddressInUseException;
use Modules\Knx\Http\Resources\FieldDeviceResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\FieldDeviceService;
use Modules\Knx\Services\FieldProjectService;

/**
 * `POST /field/projects/{code}/devices` — a technician registers an apparatus
 * (V11.c, CLA-609), idempotent by the app's own `clientId`.
 *
 * The 409 is raised here and not in the service on purpose: the service returns the
 * collision so its transaction is committed first, because the conflict it recorded
 * **is** the durable trace of the attempt.
 */
class FieldDeviceController extends Controller
{
    public function __construct(
        private readonly FieldProjectService $projects,
        private readonly FieldDeviceService $devices,
    ) {}

    public function store(Request $request, string $code): JsonResponse
    {
        /** @var KnxEmployee $technician */
        $technician = $request->attributes->get('knx_employee');

        // Scope first: a technician may not write into a project that is not theirs
        // today, and that must be decided before any input is looked at.
        $project = $this->projects->resolveAuthorized($technician, $code);

        $input = $request->validate([
            'clientId' => ['required', 'string', 'max:64'],
            'projectCode' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:32'],
            'type' => ['required', 'string', 'max:100'],
            'roomId' => ['required', 'string', 'max:64'],
            // The app's own labels, accepted because its type carries them and not
            // stored: the room row owns its name and floor, so keeping a second copy
            // would only create a way for the two to disagree.
            'roomName' => ['nullable', 'string', 'max:150'],
            'floor' => ['nullable', 'string', 'max:100'],
            'boardCode' => ['nullable', 'string', 'max:32'],
            'serial' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
            'photoDataUrl' => ['nullable', 'string'],
            'capturedAt' => ['required', 'date'],
        ]);

        // The payload repeats the project code the URL already carries. Ignoring a
        // mismatch would let one wrong variable register apparatus into the wrong
        // project, so it is an error instead.
        if ($input['projectCode'] !== $code) {
            throw ValidationException::withMessages(['projectCode' => [__('knx::field.project_mismatch')]]);
        }

        $result = $this->devices->register($technician, $project, $input);

        if ($result['collision'] !== null) {
            throw AddressInUseException::forFieldRegistration(
                $result['collision']->address,
                FieldDeviceResource::make($result['collision'])->resolve($request),
            );
        }

        return response()->json(
            FieldDeviceResource::make($result['device'])->resolve($request),
            $result['created'] ? 201 : 200,
        );
    }
}
