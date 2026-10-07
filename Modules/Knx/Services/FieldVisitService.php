<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Models\KnxVisit;
use Modules\Knx\Models\KnxVisitItem;
use Modules\Knx\Support\IdempotentWrite;

/**
 * Closing a visit from site (V11.e, CLA-609).
 *
 * The three closures are phases of one delivery, and the field contract is explicit
 * that they are not interchangeable. Two rules make them trustworthy:
 *
 *   1. **Idempotent by the app's `clientId`**, like every other field write: a closure
 *      queued offline and synced twice is one closure.
 *   2. **The four lists are stored as the app sent them** — labels, in order. They are
 *      free text, not references, and matching them to function or document rows by
 *      their wording would invent a link the technician never made. What the office
 *      gets is exactly what was written, and it can link them itself later.
 *
 * Nothing else is changed by a closure: a `final` does not flip a project's status,
 * `pending` items do not become conflicts, and verified functions do not close
 * acceptance tests. Those are office decisions about its own records, and taking them
 * automatically from a free-text list would put words in the office's mouth.
 */
class FieldVisitService
{
    /**
     * @param  array<string, mixed>  $input  validated input
     * @return array{visit: KnxVisit, created: bool}
     */
    public function close(KnxEmployee $technician, KnxProject $project, array $input): array
    {
        $replay = $this->replayFor($project, $input['clientId']);

        if ($replay !== null) {
            return ['visit' => $replay, 'created' => false];
        }

        $visit = IdempotentWrite::run(
            fn (): KnxVisit => $this->createClosedVisit($technician, $project, $input),
            fn (): ?KnxVisit => $this->replayFor($project, $input['clientId']),
        );

        return [
            'visit' => $visit->load('items'),
            // Exact answer to "did we write it?": false means this row came from the
            // winner of a lost race, so the caller answers 200 instead of 201.
            'created' => $visit->wasRecentlyCreated,
        ];
    }

    /**
     * The closure this `clientId` already produced, if any.
     *
     * Same key but another project is a client bug and not idempotency: answering with
     * the stored closure would hand one project's delivery to a request about another.
     * Both the first read and the recovery read after a lost race go through here, so the
     * rule cannot drift between them.
     */
    private function replayFor(KnxProject $project, string $clientId): ?KnxVisit
    {
        $visit = KnxVisit::query()->with('items')->where('client_id', $clientId)->first();

        if ($visit === null) {
            return null;
        }

        if ((int) $visit->project_id !== (int) $project->getKey()) {
            throw ValidationException::withMessages(['clientId' => [__('knx::field.client_id_reused')]]);
        }

        return $visit;
    }

    /**
     * The write itself, in one transaction: the closure and its four lists are one
     * record, and a closure that lost its lists would be a signature over nothing.
     */
    private function createClosedVisit(KnxEmployee $technician, KnxProject $project, array $input): KnxVisit
    {
        return DB::transaction(function () use ($technician, $project, $input): KnxVisit {
            $room = $this->roomFor($project, $input['room'] ?? null);

            $visit = KnxVisit::create([
                'project_id' => $project->getKey(),
                'room_id' => $room?->getKey(),
                // Only when the office has no room by that name: the room row is the
                // authority when it exists, and the technician's own word is kept when
                // it does not.
                'room_label' => $room === null ? ($input['room'] ?? null) : null,
                'client_id' => $input['clientId'],
                'type' => $input['type'],
                'work_done' => $input['workDone'],
                'minutes' => $input['minutes'] ?? null,
                'signed_by' => $input['signedBy'] ?? null,
                'captured_at' => Carbon::parse($input['capturedAt']),
                'closed_by_employee_id' => $technician->getKey(),
            ]);

            $this->storeItems($visit, $input);

            return $visit;
        });
    }

    /**
     * The room the closure happened in, when the office has one by that name.
     *
     * An exact, case-insensitive match and nothing fuzzier: the app sends the name it
     * showed the technician, and a loose match could file the work against the wrong
     * space. No match is a normal answer, not a failure — see `room_label`.
     */
    private function roomFor(KnxProject $project, ?string $name): ?KnxProjectRoom
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return $project->rooms()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function storeItems(KnxVisit $visit, array $input): void
    {
        $lists = [
            KnxVisit::ITEM_PENDING => $input['pending'] ?? [],
            KnxVisit::ITEM_RESERVATION => $input['reservations'] ?? [],
            KnxVisit::ITEM_VERIFIED_FUNCTION => $input['verifiedFunctions'] ?? [],
            KnxVisit::ITEM_DOCUMENT => $input['documents'] ?? [],
        ];

        foreach ($lists as $kind => $labels) {
            foreach (array_values($labels) as $position => $label) {
                $label = trim((string) $label);

                if ($label === '') {
                    continue;
                }

                KnxVisitItem::create([
                    'visit_id' => $visit->getKey(),
                    'kind' => $kind,
                    'label' => $label,
                    'position' => $position,
                ]);
            }
        }
    }
}
