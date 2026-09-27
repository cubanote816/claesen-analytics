<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxConflictLog;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;

/**
 * Registering an apparatus from the field (V11.c, CLA-609).
 *
 * The app may be offline for hours, so the same registration can arrive twice, out
 * of order or after the address was taken by somebody else. Three rules answer that,
 * and they are the whole point of this class:
 *
 *   1. **The app's `clientId` decides identity.** A replay returns the row that is
 *      already there (200), never a second one.
 *   2. **An address already in use is not an error to hide.** Nothing is created,
 *      the app is told which device is in the way (`409 address_in_use`), and the
 *      attempt is written down as a `duplicate_address` conflict so the office sees
 *      it in the Conflictencentrum — with the photo as evidence. Losing the attempt
 *      would leave the technician with "error" and the office with nothing.
 *   3. **A device and a notification are two different things**: the device is the
 *      registration the dossier shows, the notification is the event the office's
 *      field inbox has to confirm. Creating one without the other breaks one of the
 *      two screens, so they are always created together, in one transaction.
 */
class FieldDeviceService
{
    /** What one photo may weigh once decoded. Cameras rarely exceed this. */
    private const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    /**
     * The image types we accept, and the extension to store them under.
     *
     * `svg+xml` is here because the app's own fixture generates its photos as inline
     * SVG data URLs; a camera produces the other three.
     */
    private const PHOTO_EXTENSIONS = [
        'png' => 'png',
        'jpeg' => 'jpg',
        'jpg' => 'jpg',
        'webp' => 'webp',
        'gif' => 'gif',
        'svg+xml' => 'svg',
    ];

    /**
     * Register (or recognise) one apparatus.
     *
     * Returns rather than throws when the address is taken, so the transaction that
     * recorded the conflict is committed before the caller turns it into a 409: an
     * exception raised inside a transaction would roll the evidence back.
     *
     * @param  array<string, mixed>  $input  validated input
     * @return array{device: KnxDevice, created: bool, collision: KnxDevice|null}
     */
    public function register(KnxEmployee $technician, KnxProject $project, array $input): array
    {
        $replay = $this->withContext()
            ->where('client_id', $input['clientId'])
            ->first();

        if ($replay !== null) {
            // Same key, another project: the app has reused a UUID. Answering with the
            // stored device would hand one project's apparatus to a request about
            // another, so this is a bad request and not idempotency.
            if ((int) $replay->project_id !== (int) $project->getKey()) {
                throw ValidationException::withMessages(['clientId' => [__('knx::field.client_id_reused')]]);
            }

            return ['device' => $replay, 'created' => false, 'collision' => null];
        }

        // The room is checked before anything is written: a rejected registration must
        // not leave a photo behind.
        $room = $this->roomFor($project, $input);

        $photoPath = $this->storePhoto($input['clientId'], $input['photoDataUrl'] ?? null);

        $collision = $this->withContext()
            ->where('project_id', $project->getKey())
            ->where('address', $input['address'])
            ->first();

        if ($collision !== null) {
            // A retry must not leave a second identical conflict in the office's
            // worklist. The app's own key identifies the attempt, so a replay of the
            // same attempt answers the same 409 without writing anything new.
            if (! KnxConflict::query()->where('client_id', $input['clientId'])->exists()) {
                $this->recordCollision($technician, $project, $collision, $input, $photoPath);
            }

            return ['device' => $collision, 'created' => false, 'collision' => $collision];
        }

        $capturedAt = Carbon::parse($input['capturedAt']);

        $device = DB::transaction(function () use ($technician, $project, $room, $input, $photoPath, $capturedAt): KnxDevice {
            $board = $this->boardFor($project, $input['boardCode'] ?? null);

            $device = KnxDevice::create([
                'project_id' => $project->getKey(),
                'room_id' => $room->getKey(),
                'board_id' => $board?->getKey(),
                'type' => $input['type'],
                'address' => $input['address'],
                'serial' => $input['serial'] ?? null,
                'source' => KnxDevice::SOURCE_FIELD,
                'registered_by_employee_id' => $technician->getKey(),
                // The technician's own capture time: that is when it was registered.
                // When the server received it is the row's created_at.
                'registered_at' => $capturedAt,
                // Not acknowledged: this is exactly what makes the office dossier show
                // it as new until somebody confirms it.
                'acknowledged_at' => null,
                'client_id' => $input['clientId'],
                'captured_at' => $capturedAt,
                'photo_path' => $photoPath,
            ]);

            KnxNotification::create([
                'project_id' => $project->getKey(),
                'device_id' => $device->getKey(),
                'board_id' => $board?->getKey(),
                // The notification mirrors the device (type, address, room, serial):
                // the inbox row is the event, and it reads like the registration.
                'type' => $device->type,
                'address' => $device->address,
                'room' => $room->name,
                'serial' => $device->serial,
                'reported_by_employee_id' => $technician->getKey(),
                'reported_at' => $capturedAt,
                'acknowledged_at' => null,
            ]);

            return $device;
        });

        return ['device' => $device, 'created' => true, 'collision' => null];
    }

    private function withContext()
    {
        return KnxDevice::query()->with(['room', 'board', 'registeredBy']);
    }

    /**
     * The room the app picked, which has to be one of this project's own.
     *
     * Strict on purpose: the app builds its room list from
     * `GET /field/projects/{code}`, so an unknown id means the list is stale — and
     * guessing a room from the free-text name would put apparatus in the wrong place.
     */
    private function roomFor(KnxProject $project, array $input): KnxProjectRoom
    {
        $room = $project->rooms()->whereKey($input['roomId'])->first();

        if ($room === null) {
            throw ValidationException::withMessages(['roomId' => [__('knx::field.room_unknown')]]);
        }

        return $room;
    }

    /**
     * The board the technician read off the cabinet.
     *
     * Created when the office has not modelled it yet, which is the convention the
     * demo fixture already uses: the code exists in the installation whether or not
     * the office has a row for it, and dropping it would lose what the technician
     * actually saw. It is created **without a name** on purpose — we know the code,
     * not what the office calls that cabinet.
     */
    private function boardFor(KnxProject $project, ?string $code): ?KnxBoard
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return KnxBoard::firstOrCreate(
            ['project_id' => $project->getKey(), 'code' => trim($code)],
            ['name' => null],
        );
    }

    /**
     * Decode the app's photo and put it on disk.
     *
     * Both encodings the app can produce are accepted — `;base64,` (what a camera
     * gives through a canvas) and the percent-encoded form its own fixture uses.
     * The file is named after the `clientId`, so a replay can never store the same
     * photo twice under two names.
     */
    private function storePhoto(string $clientId, ?string $dataUrl): ?string
    {
        if ($dataUrl === null || $dataUrl === '') {
            return null;
        }

        // The header may carry parameters between the subtype and the comma:
        // `;base64` from a canvas, `;utf8` / `;charset=utf-8` from the app's own
        // inline-SVG fixture. Parsing the three parts beats a regex per encoding.
        if (! preg_match('#^data:image/([a-z0-9.+-]+)(;[^,]*)?,(.*)$#is', $dataUrl, $matches)) {
            throw ValidationException::withMessages(['photoDataUrl' => [__('knx::field.photo_invalid')]]);
        }

        $extension = self::PHOTO_EXTENSIONS[strtolower($matches[1])] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages(['photoDataUrl' => [__('knx::field.photo_unsupported')]]);
        }

        $parameters = strtolower($matches[2] ?? '');
        $payload = $matches[3];

        $bytes = str_contains($parameters, 'base64')
            ? base64_decode($payload, true)
            : rawurldecode($payload);

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages(['photoDataUrl' => [__('knx::field.photo_invalid')]]);
        }

        if (strlen($bytes) > self::MAX_PHOTO_BYTES) {
            throw ValidationException::withMessages(['photoDataUrl' => [__('knx::field.photo_too_large')]]);
        }

        $path = 'knx/devices/'.$clientId.'.'.$extension;

        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    /**
     * Write the rejected attempt down as a conflict.
     *
     * This is the part of the contract that is easy to skip and expensive to skip: the
     * technician cannot resolve a duplicate address, so if the attempt is not recorded
     * the office never learns that ETS and reality disagree at that address.
     *
     * The two description texts follow the office fixture's own convention —
     * "type · room" — because that is what the Conflictencentrum displays as
     * `deviceExisting` (what is registered) and `deviceField` (what came from site).
     */
    private function recordCollision(
        KnxEmployee $technician,
        KnxProject $project,
        KnxDevice $taken,
        array $input,
        ?string $photoPath,
    ): void {
        DB::transaction(function () use ($technician, $project, $taken, $input, $photoPath): void {
            $conflict = KnxConflict::create([
                'project_id' => $project->getKey(),
                'device_id' => $taken->getKey(),
                'client_id' => $input['clientId'],
                // Critical, like every duplicate address in the fixture: two apparatus
                // answering to one address is how an installation misbehaves.
                'severity' => 'critical',
                'type' => 'duplicate_address',
                'address' => $input['address'],
                'device_existing' => $this->describe($taken),
                'device_field' => $this->describeIncoming($input),
                'reported_by_employee_id' => $technician->getKey(),
                'reported_at' => $input['capturedAt'],
                'note' => $input['note'] ?? null,
                'photo_path' => $photoPath,
                'status' => 'open',
            ]);

            // The history opens with the report itself: the office's conflict detail
            // reads it as the first line of the ETS worklist.
            KnxConflictLog::create([
                'conflict_id' => $conflict->getKey(),
                'at' => now(),
                'action' => 'reported',
                'address' => $input['address'],
            ]);
        });
    }

    private function describe(KnxDevice $device): string
    {
        $parts = array_filter([
            $device->type,
            $device->room?->name,
        ]);

        $description = implode(' · ', $parts);

        // Saying where the existing registration came from is the difference between
        // "somebody typed this twice" and "ETS disagrees with the site".
        return $device->source === KnxDevice::SOURCE_ETS
            ? $description.' · ETS'
            : $description;
    }

    private function describeIncoming(array $input): string
    {
        return implode(' · ', array_filter([$input['type'], $input['roomName'] ?? null]));
    }
}
