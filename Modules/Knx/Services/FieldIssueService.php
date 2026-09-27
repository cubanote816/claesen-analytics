<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxConflictLog;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;

/**
 * An incident reported from site (V11.d, CLA-609).
 *
 * The contract is explicit about what makes these useful: an incident is always
 * anchored to a place and, when it is known, to an apparatus and a channel. Losing
 * that context would turn "something is wrong somewhere" into a message the office
 * has to chase, so the location, the apparatus and the channel all end up in the
 * conflict the office reads.
 *
 * The reported incident **is** a conflict: that is what the Conflictencentrum already
 * is, and duplicating the concept would give the office two worklists to keep in sync.
 */
class FieldIssueService
{
    /**
     * The app's `kind` → the office's conflict `type`.
     *
     * Three of the four map one to one, because both contracts are describing the
     * same four findings. `other` has **no counterpart**: the office's `ConflictType`
     * is `duplicate_address | missing_device | plan_mismatch | damaged`, and its label
     * map is a `Record<ConflictType, …>` with no fallback, so storing `other` would
     * render as `undefined` in the Conflictencentrum.
     *
     * Rather than mislabel the technician's finding as one of the three, the fourth is
     * refused with a field error: that is a declared gap in the office contract, not an
     * incident that can be filed. See docs/Knx/knx-kantoor-backend.md.
     */
    private const KIND_TO_TYPE = [
        'damaged' => 'damaged',
        'missing' => 'missing_device',
        'plan_mismatch' => 'plan_mismatch',
    ];

    /** The severity the office fixture already uses for each type. */
    private const TYPE_SEVERITY = [
        'duplicate_address' => 'critical',
        'missing_device' => 'warning',
        'plan_mismatch' => 'warning',
        'damaged' => 'info',
    ];

    /**
     * The office fixture's own wording for an address nothing is registered at. Dutch
     * because these description strings are the office's data (they live in the
     * database and are read in the office's language), not a request-time UI string.
     */
    private const NOT_REGISTERED = '— niet gevonden ter plaatse';

    public function __construct(private readonly FieldPhotoService $photos) {}

    /**
     * @param  array<string, mixed>  $input  validated input
     * @return array{conflict: KnxConflict, created: bool}
     */
    public function report(KnxEmployee $technician, KnxProject $project, array $input): array
    {
        $existing = KnxConflict::query()->where('client_id', $input['clientId'])->first();

        if ($existing !== null) {
            // Same key, another project: the app reused a UUID, and answering with the
            // stored conflict would hand one project's incident to a request about
            // another.
            if ((int) $existing->project_id !== (int) $project->getKey()) {
                throw ValidationException::withMessages(['clientId' => [__('knx::field.client_id_reused')]]);
            }

            return ['conflict' => $existing, 'created' => false];
        }

        $type = self::KIND_TO_TYPE[$input['kind']] ?? null;

        if ($type === null) {
            throw ValidationException::withMessages(['kind' => [__('knx::field.kind_unsupported')]]);
        }

        $photoPath = $this->photos->store($input['clientId'], $input['photoDataUrl'] ?? null);

        $address = $input['deviceAddress'] ?? '';

        // When the office has an apparatus at that address, the incident is anchored to
        // it: that is what turns a report into something the ETS list can be checked
        // against.
        $registered = $address === '' ? null : KnxDevice::query()
            ->with(['room'])
            ->where('project_id', $project->getKey())
            ->where('address', $address)
            ->first();

        $conflict = DB::transaction(function () use ($technician, $project, $input, $type, $address, $registered, $photoPath): KnxConflict {
            $conflict = KnxConflict::create([
                'project_id' => $project->getKey(),
                'device_id' => $registered?->getKey(),
                'client_id' => $input['clientId'],
                'severity' => self::TYPE_SEVERITY[$type],
                'type' => $type,
                // Empty when the incident is about a space rather than an address: the
                // column is not nullable, and inventing an address would be worse than
                // an empty one.
                'address' => $address,
                'device_existing' => $registered === null
                    ? self::NOT_REGISTERED
                    : implode(' · ', array_filter([$registered->type, $registered->room?->name])),
                'device_field' => $this->describeIncoming($input),
                'reported_by_employee_id' => $technician->getKey(),
                'reported_at' => Carbon::parse($input['capturedAt']),
                'note' => $input['note'],
                'photo_path' => $photoPath,
                'status' => 'open',
            ]);

            KnxConflictLog::create([
                'conflict_id' => $conflict->getKey(),
                'at' => now(),
                'action' => 'reported',
                'address' => $address,
            ]);

            return $conflict;
        });

        return ['conflict' => $conflict, 'created' => true];
    }

    /**
     * The place, the apparatus and the channel, in the fixture's own convention
     * ("place · apparatus · detail" separated by " · ").
     *
     * All four values are kept when they are there: an incident about `1.1.150` on
     * board `E10` in row 4 is more useful with all three than with a choice between
     * them, and the office's field is exactly the free-text description meant to carry
     * them.
     */
    private function describeIncoming(array $input): string
    {
        return implode(' · ', array_filter([
            $input['room'] ?? null,
            $input['deviceAddress'] ?? null,
            $input['boardCode'] ?? null,
            $input['channel'] ?? null,
        ]));
    }
}
