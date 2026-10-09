<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxFunctionSpec;
use Modules\Knx\Models\KnxIntent;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;

/**
 * La intención por elemento (KNX-55).
 *
 * El elemento se identifica por su **identidad estable dentro del proyecto**
 * (`target_key`: `module:E10:3`, `channel:E10:3:kanaal 2`, `device:1893`), no por el id
 * de la fila: por eso `PUT` puede ser un upsert sin que el front tenga que recordar un id.
 *
 * Una escritura que **no cambia nada** devuelve la intención vigente tal cual, sin subir
 * versión ni avisar. Subir versión en un doble clic convertiría el historial en ruido y
 * mandaría un aviso por nada; el contrato dice "cada cambio", no "cada llamada".
 */
class IntentService
{
    public function __construct(private readonly BoardService $boards) {}

    /**
     * The current intents of a project, optionally narrowed to one element or room.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, KnxIntent>
     */
    public function list(KnxProject $project, array $filters): Collection
    {
        return KnxIntent::query()
            ->where('project_id', $project->getKey())
            ->current()
            ->with(['board', 'device', 'room', 'function', 'author'])
            ->when(! empty($filters['boardId']), function ($query) use ($filters, $project): void {
                $board = $this->boards->resolve($project, (string) $filters['boardId']);
                $query->where('board_id', $board->getKey());
            })
            ->when(isset($filters['slot']), fn ($query) => $query->where('module_slot', (int) $filters['slot']))
            ->when(! empty($filters['deviceId']), fn ($query) => $query->where('device_id', (int) $filters['deviceId']))
            ->when(! empty($filters['roomId']), fn ($query) => $query->where('room_id', (int) $filters['roomId']))
            ->orderBy('id')
            ->get();
    }

    /**
     * Upsert of one element's intent: writes the next version, or answers the current one
     * when the text (and its links) did not change.
     *
     * @param  array<string, mixed>  $input
     */
    public function put(KnxProject $project, KnxEmployee $author, array $input): KnxIntent
    {
        $target = $this->resolveTarget($project, $input['target']);
        $roomId = $this->resolveRoomId($project, $input['roomId'] ?? null);
        $functionId = $this->resolveFunctionId($project, $input['functionId'] ?? null);
        $text = trim((string) $input['text']);
        $key = $this->targetKey($target);

        return DB::transaction(function () use ($project, $author, $target, $roomId, $functionId, $text, $key): KnxIntent {
            $current = KnxIntent::query()
                ->where('project_id', $project->getKey())
                ->where('target_key', $key)
                ->current()
                ->lockForUpdate()
                ->first();

            if ($current !== null
                && $current->text === $text
                && $current->room_id === $roomId
                && $current->function_id === $functionId) {
                return $this->present($current);
            }

            if ($current !== null) {
                // The previous version is history from here on, never an edit.
                $current->forceFill(['is_current' => false])->save();
            }

            $intent = KnxIntent::create([
                'project_id' => $project->getKey(),
                'target_type' => $target['type'],
                'target_key' => $key,
                'board_id' => $target['board']?->getKey(),
                'module_slot' => $target['slot'],
                'channel' => $target['channel'],
                'device_id' => $target['device']?->getKey(),
                'room_id' => $roomId,
                'function_id' => $functionId,
                'text' => $text,
                'version' => ($current?->version ?? 0) + 1,
                'is_current' => true,
                'author_employee_id' => $author->getKey(),
            ]);

            $this->announce($project, $intent, $author);

            return $this->present($intent);
        });
    }

    /**
     * Every version of the same element, newest first.
     *
     * @return Collection<int, KnxIntent>
     */
    public function versions(KnxIntent $intent): Collection
    {
        return KnxIntent::query()
            ->where('project_id', $intent->project_id)
            ->where('target_key', $intent->target_key)
            ->with(['board', 'device', 'room', 'function', 'author'])
            ->orderByDesc('version')
            ->get();
    }

    /**
     * The current intents that belong to a room, directly or through a device of the
     * room. This is what the room sheet shows.
     *
     * @return Collection<int, KnxIntent>
     */
    public function forRoom(KnxProjectRoom $room): Collection
    {
        return KnxIntent::query()
            ->where('project_id', $room->project_id)
            ->current()
            ->where(fn ($query) => $query
                ->where('room_id', $room->getKey())
                ->orWhereHas('device', fn ($device) => $device->where('room_id', $room->getKey())))
            ->with(['board', 'device', 'room', 'function', 'author'])
            ->orderBy('id')
            ->get();
    }

    private function present(KnxIntent $intent): KnxIntent
    {
        return $intent->load(['board', 'device', 'room', 'function', 'author']);
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array{type: string, board: KnxBoard|null, slot: int|null, channel: string|null, device: KnxDevice|null}
     */
    private function resolveTarget(KnxProject $project, array $target): array
    {
        $type = (string) $target['type'];

        if ($type === KnxIntent::TARGET_DEVICE) {
            $device = KnxDevice::query()
                ->where('project_id', $project->getKey())
                ->whereKey($target['deviceId'])
                ->first();

            if ($device === null) {
                throw ValidationException::withMessages(['target.deviceId' => [__('knx::field.unknown_device')]]);
            }

            return ['type' => $type, 'board' => null, 'slot' => null, 'channel' => null, 'device' => $device];
        }

        // `module` and `channel` hang off a cabinet; an unknown or foreign board is a
        // 404 from the resolver, which is the contract's answer for "not of this project".
        $board = $this->boards->resolve($project, (string) $target['boardId']);

        return [
            'type' => $type,
            'board' => $board,
            'slot' => (int) $target['slot'],
            'channel' => $type === KnxIntent::TARGET_CHANNEL ? trim((string) $target['channel']) : null,
            'device' => null,
        ];
    }

    /**
     * @param  array{type: string, board: KnxBoard|null, slot: int|null, channel: string|null, device: KnxDevice|null}  $target
     */
    private function targetKey(array $target): string
    {
        return match ($target['type']) {
            KnxIntent::TARGET_MODULE => 'module:'.$this->boardIdentity($target['board']).':'.$target['slot'],
            KnxIntent::TARGET_CHANNEL => 'channel:'.$this->boardIdentity($target['board']).':'.$target['slot'].':'.$target['channel'],
            KnxIntent::TARGET_DEVICE => 'device:'.$target['device']?->getKey(),
        };
    }

    private function boardIdentity(?KnxBoard $board): string
    {
        return (string) ($board?->slug ?? $board?->code);
    }

    private function resolveRoomId(KnxProject $project, mixed $roomId): ?int
    {
        if ($roomId === null || $roomId === '') {
            return null;
        }

        $room = KnxProjectRoom::query()
            ->where('project_id', $project->getKey())
            ->whereKey($roomId)
            ->first();

        if ($room === null) {
            throw ValidationException::withMessages(['roomId' => [__('knx::field.room_unknown')]]);
        }

        return $room->getKey();
    }

    private function resolveFunctionId(KnxProject $project, mixed $functionId): ?int
    {
        if ($functionId === null || $functionId === '') {
            return null;
        }

        $function = KnxFunctionSpec::query()
            ->where('project_id', $project->getKey())
            ->whereKey($functionId)
            ->first();

        if ($function === null) {
            throw ValidationException::withMessages(['functionId' => [__('knx::intents.unknown_function')]]);
        }

        return $function->getKey();
    }

    /**
     * A change of intent is a typed notice (KNX-54). It goes to the digest: the office
     * already knows what it wrote, and the field reads it on the element's own card.
     */
    private function announce(KnxProject $project, KnxIntent $intent, KnxEmployee $author): void
    {
        KnxNotification::create([
            'project_id' => $project->getKey(),
            'room_id' => $intent->room_id,
            'device_id' => $intent->device_id,
            'kind' => KnxNotification::KIND_INTENT_CHANGED,
            'title' => __('knx::notifications.intent_changed'),
            'summary' => $this->describe($intent),
            'target_type' => 'intent',
            'target_id' => (string) $intent->getKey(),
            'reported_by_employee_id' => $author->getKey(),
            'reported_at' => now(),
        ]);
    }

    /**
     * The element in the office's words: `module E10 slot 3`, `kanaal kanaal 2 van E10`,
     * `1.1.101`. Short, because it is the line the inbox shows.
     */
    private function describe(KnxIntent $intent): string
    {
        return match ($intent->target_type) {
            KnxIntent::TARGET_MODULE => 'module '.$intent->boardExternalId().' slot '.$intent->module_slot,
            KnxIntent::TARGET_CHANNEL => 'kanaal '.$intent->channel.' van '.$intent->boardExternalId(),
            KnxIntent::TARGET_DEVICE => (string) ($intent->device?->address ?? 'device '.$intent->device_id),
            default => $intent->target_key,
        };
    }
}
