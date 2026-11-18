<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxConflictLog;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Support\IdempotentWrite;

/**
 * An incident, from site or from the office (V11.d, CLA-609; KNX-56).
 *
 * The contract is explicit about what makes these useful: an incident is always
 * anchored to a place and, when it is known, to an apparatus and a channel. Losing
 * that context would turn "something is wrong somewhere" into a message the office
 * has to chase, so the location, the apparatus and the channel all end up in the
 * conflict the office reads.
 *
 * The reported incident **is** a conflict: that is what the Conflictencentrum already
 * is, and duplicating the concept would give the office two worklists to keep in sync.
 *
 * Two entry points share one writer:
 *   - the field reports with its own `clientId` (offline retries must be idempotent);
 *   - the office reports with ids (`roomId`, `deviceId`, `boardId`) and no key.
 *
 * `kind: malfunction` is the physical installation misbehaving (KNX-56): it is not
 * another word for "damaged", it has its own `lifecycle`, its `symptom` and its own
 * validated cycle — and it always warns as it happens.
 */
class IssueService
{
    /**
     * The apps' `kind` → the office's conflict `type`.
     *
     * All of them map one to one, because both contracts are describing the same
     * findings. The office's `ConflictType` did **not** have an `other` at first, and
     * its label map is a `Record<ConflictType, …>` with no fallback, so storing it
     * would have rendered as `undefined` in the Conflictencentrum. The office contract
     * was extended with that value on 2026-09-27 (`types.ts` + `constants.ts` + both
     * `ct` dictionaries, checked by the compiler) precisely so a technician can report
     * a finding that fits none of the three — refusing it would have left them
     * choosing the nearest wrong type, which is worse for the office than an honest
     * "other". `malfunction` joined it in KNX-56 with its own lifecycle.
     */
    private const KIND_TO_TYPE = [
        'damaged' => 'damaged',
        'missing' => 'missing_device',
        'plan_mismatch' => 'plan_mismatch',
        'other' => 'other',
        'malfunction' => 'malfunction',
    ];

    /**
     * The severity per type.
     *
     * The first four come from the office fixture, which already uses exactly these.
     * `other` has no fixture entry because it did not exist there: it lands on `warning`
     * deliberately — an undefined finding has to be looked at, so it must not be buried
     * at `info`, and calling it `critical` would cry wolf on every unknown note.
     * `malfunction` is a real fault the client notices, so it is a `warning`: worse
     * than cosmetic damage, without claiming an installation-blocking address conflict.
     */
    private const TYPE_SEVERITY = [
        'duplicate_address' => 'critical',
        'missing_device' => 'warning',
        'plan_mismatch' => 'warning',
        'damaged' => 'info',
        'other' => 'warning',
        'malfunction' => 'warning',
    ];

    /**
     * The office fixture's own wording for an address nothing is registered at. Dutch
     * because these description strings are the office's data (they live in the
     * database and are read in the office's language), not a request-time UI string.
     */
    private const NOT_REGISTERED = '— niet gevonden ter plaatse';

    public function __construct(
        private readonly FieldPhotoService $photos,
        private readonly BoardService $boards,
    ) {}

    /**
     * `POST /field/projects/{code}/issues` — idempotent by the app's own `clientId`.
     *
     * @param  array<string, mixed>  $input  validated input
     * @return array{conflict: KnxConflict, created: bool}
     */
    public function reportFromField(KnxEmployee $technician, KnxProject $project, array $input): array
    {
        $existing = $this->replayFor($project, $input['clientId']);

        if ($existing !== null) {
            return ['conflict' => $existing, 'created' => false];
        }

        $photoPath = $this->photos->store($input['clientId'], $input['photoDataUrl'] ?? null);
        $target = $this->resolveTarget($project, $input);

        $conflict = IdempotentWrite::run(
            fn (): KnxConflict => $this->writeReportedConflict(
                $project,
                $technician,
                $input,
                $target,
                $photoPath,
                clientId: $input['clientId'],
                capturedAt: Carbon::parse($input['capturedAt']),
            ),
            fn (): ?KnxConflict => $this->replayFor($project, $input['clientId']),
        );

        return [
            'conflict' => $conflict,
            // Exact answer to "did we write it?": false means this row came from the
            // winner of a lost race, so the caller answers 200 instead of 201.
            'created' => $conflict->wasRecentlyCreated,
        ];
    }

    /**
     * `POST /projects/{code}/issues` — the office filing a complaint, for example the
     * client's call after handover. No `clientId`: there is no offline retry to dedupe.
     *
     * @param  array<string, mixed>  $input  validated input
     */
    public function reportFromOffice(KnxProject $project, ?KnxEmployee $reporter, array $input): KnxConflict
    {
        $target = $this->resolveTarget($project, $input);

        // No app key to name the photo after, so one is generated here. It only names
        // the file; the conflict keeps `client_id` null.
        $photoPath = $this->photos->store((string) Str::uuid(), $input['photoDataUrl'] ?? null);

        return $this->writeReportedConflict(
            $project,
            $reporter,
            $input,
            $target,
            $photoPath,
            clientId: null,
            capturedAt: now(),
        );
    }

    /**
     * The conflict this `clientId` already produced, if any.
     *
     * Same key but another project is a client bug and not idempotency: answering with
     * the stored incident would hand one project's report to a request about another.
     * Both the first read and the recovery read after a lost race go through here.
     */
    private function replayFor(KnxProject $project, string $clientId): ?KnxConflict
    {
        $conflict = KnxConflict::query()->where('client_id', $clientId)->first();

        if ($conflict === null) {
            return null;
        }

        if ((int) $conflict->project_id !== (int) $project->getKey()) {
            throw ValidationException::withMessages(['clientId' => [__('knx::field.client_id_reused')]]);
        }

        return $conflict;
    }

    /**
     * Where the incident happened, resolved inside the project.
     *
     * The office names things by id and the field by hand (a room name, a physical
     * address, a board code); both end up as the same display values. An id that does
     * not belong to the project is a field error, not a silent no-op: it would anchor
     * the incident nowhere.
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *     roomId: int|null,
     *     roomName: string|null,
     *     device: KnxDevice|null,
     *     address: string,
     *     boardCode: string|null,
     *     moduleSlot: int|null,
     *     channel: string|null,
     * }
     */
    private function resolveTarget(KnxProject $project, array $input): array
    {
        $device = $this->resolveDevice($project, $input);
        $room = $this->resolveRoom($project, $input, $device);
        $boardCode = $this->resolveBoardCode($project, $input);

        $address = $device?->address ?? (string) ($input['deviceAddress'] ?? '');

        return [
            'roomId' => $room?->getKey(),
            'roomName' => $room?->name ?? ($input['room'] ?? null),
            'device' => $device,
            'address' => $address,
            'boardCode' => $boardCode,
            'moduleSlot' => isset($input['moduleSlot']) ? (int) $input['moduleSlot'] : null,
            'channel' => $input['channel'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveDevice(KnxProject $project, array $input): ?KnxDevice
    {
        if (! empty($input['deviceId'])) {
            $device = KnxDevice::query()
                ->with('room')
                ->where('project_id', $project->getKey())
                ->whereKey($input['deviceId'])
                ->first();

            if ($device === null) {
                throw ValidationException::withMessages(['deviceId' => [__('knx::field.unknown_device')]]);
            }

            return $device;
        }

        if (empty($input['deviceAddress'])) {
            return null;
        }

        // An address nothing is registered at is a real finding, not an error: the
        // conflict then says "niet gevonden ter plaatse".
        return KnxDevice::query()
            ->with('room')
            ->where('project_id', $project->getKey())
            ->where('address', $input['deviceAddress'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveRoom(KnxProject $project, array $input, ?KnxDevice $device): ?KnxProjectRoom
    {
        if (! empty($input['roomId'])) {
            $room = KnxProjectRoom::query()
                ->where('project_id', $project->getKey())
                ->whereKey($input['roomId'])
                ->first();

            if ($room === null) {
                throw ValidationException::withMessages(['roomId' => [__('knx::field.room_unknown')]]);
            }

            return $room;
        }

        if (! empty($input['room'])) {
            // The free-text name the technician typed wins over the device's own room:
            // it is what they saw on the spot, and the field contract has always sent it.
            return KnxProjectRoom::query()
                ->where('project_id', $project->getKey())
                ->where('name', $input['room'])
                ->first();
        }

        return $device?->room;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveBoardCode(KnxProject $project, array $input): ?string
    {
        if (! empty($input['boardId'])) {
            return $this->boards->resolve($project, (string) $input['boardId'])->code;
        }

        return $input['boardCode'] ?? null;
    }

    /**
     * The write itself, in one transaction: the incident and the first line of its
     * history are one record, and a report without its 'reported' step would be invisible
     * in the office's worklist. The notice is part of the same record.
     *
     * @param  array<string, mixed>  $input
     * @param  array{roomId: int|null, roomName: string|null, device: KnxDevice|null, address: string, boardCode: string|null, moduleSlot: int|null, channel: string|null}  $target
     */
    private function writeReportedConflict(
        KnxProject $project,
        ?KnxEmployee $reporter,
        array $input,
        array $target,
        ?string $photoPath,
        ?string $clientId,
        Carbon $capturedAt,
    ): KnxConflict {
        $kind = $input['kind'];
        $type = self::KIND_TO_TYPE[$kind] ?? null;

        if ($type === null) {
            // Unreachable through the HTTP layer: the controllers validate the app's own
            // union. It stays as the guard for a caller that skips that validation.
            throw ValidationException::withMessages(['kind' => [__('knx::field.kind_unsupported')]]);
        }

        $isMalfunction = $kind === 'malfunction';
        $device = $target['device'];

        return DB::transaction(function () use ($project, $reporter, $input, $target, $photoPath, $clientId, $capturedAt, $type, $isMalfunction, $device): KnxConflict {
            $conflict = KnxConflict::create([
                'project_id' => $project->getKey(),
                'device_id' => $device?->getKey(),
                'client_id' => $clientId,
                'severity' => self::TYPE_SEVERITY[$type],
                'type' => $type,
                'lifecycle' => $isMalfunction ? KnxConflict::LIFECYCLE_MALFUNCTION : KnxConflict::LIFECYCLE_ETS,
                // Empty when the incident is about a space rather than an address: the
                // column is not nullable, and inventing an address would be worse than
                // an empty one.
                'address' => $target['address'],
                'device_existing' => $device === null
                    ? self::NOT_REGISTERED
                    : implode(' · ', array_filter([$device->type, $device->room?->name])),
                'device_field' => $this->describeIncoming($target),
                'reported_by_employee_id' => $reporter?->getKey(),
                'reported_at' => $capturedAt,
                'note' => $input['note'] ?? null,
                'symptom' => $input['symptom'] ?? null,
                'photo_path' => $photoPath,
                'status' => 'open',
            ]);

            KnxConflictLog::create([
                'conflict_id' => $conflict->getKey(),
                'at' => now(),
                'action' => 'reported',
                'by' => $reporter?->shortName(),
                'address' => $target['address'] !== '' ? $target['address'] : null,
            ]);

            // KNX-54: the incident lands in the inbox, typed, with its target. A
            // malfunction is not another incident: it has its own kind, and that kind is
            // one of the immediate ones.
            KnxNotification::create([
                'project_id' => $project->getKey(),
                'device_id' => $device?->getKey(),
                'issue_id' => $conflict->getKey(),
                'room_id' => $target['roomId'] ?? $device?->room_id,
                'kind' => $isMalfunction
                    ? KnxNotification::KIND_MALFUNCTION_REPORTED
                    : KnxNotification::KIND_ISSUE_REPORTED,
                'title' => __($isMalfunction
                    ? 'knx::notifications.malfunction_reported'
                    : 'knx::notifications.issue_reported'),
                'summary' => trim(($target['address'] !== '' ? $target['address'] : (string) $target['roomName']).' · '.$type, ' ·'),
                'address' => $target['address'] !== '' ? $target['address'] : null,
                'room' => $target['roomName'],
                'target_type' => 'conflict',
                'target_id' => (string) $conflict->getKey(),
                'reported_by_employee_id' => $reporter?->getKey(),
                'reported_at' => $capturedAt,
                'captured_at' => $capturedAt,
                'acknowledged_at' => null,
            ]);

            return $conflict->load(['project', 'reportedBy', 'logs']);
        });
    }

    /**
     * The place, the apparatus and the channel, in the fixture's own convention
     * ("place · apparatus · detail" separated by " · ").
     *
     * All values are kept when they are there: an incident about `1.1.150` on
     * board `E10` in row 4 is more useful with all of them than with a choice between
     * them, and the office's field is exactly the free-text description meant to carry
     * them. A module adds its slot, which is how the office locates it inside the board.
     *
     * @param  array{roomName: string|null, address: string, boardCode: string|null, moduleSlot: int|null, channel: string|null}  $target
     */
    private function describeIncoming(array $target): string
    {
        return implode(' · ', array_filter([
            $target['roomName'],
            $target['address'] !== '' ? $target['address'] : null,
            $target['boardCode'],
            $target['moduleSlot'] !== null ? 'slot '.$target['moduleSlot'] : null,
            $target['channel'],
        ]));
    }
}
