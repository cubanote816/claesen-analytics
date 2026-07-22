<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\FieldOps\Models\FoMaintenanceRequestMessage;
use Modules\FieldOps\Services\FieldOpsTenantService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MaintenanceRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        $isClient = app(FieldOpsTenantService::class)->isClientUser($request->user());
        $messages = $this->whenLoaded('messages', fn () => $this->messages
            ->when($isClient, fn ($items) => $items->where('visibility', FoMaintenanceRequestMessage::VISIBILITY_PUBLIC))
            ->values()
            ->map(fn ($message): array => [
                'id' => $message->id,
                'visibility' => $message->visibility,
                'type' => $message->type,
                'body' => $message->body,
                'user' => $message->user ? ['id' => $message->user->id, 'name' => $message->user->name] : null,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->all());
        $attachments = $this->whenLoaded('media', fn () => $this->media
            ->where('collection_name', 'attachments')
            ->when($isClient, fn ($items) => $items->filter(
                fn (Media $media): bool => $media->getCustomProperty('visibility') !== FoMaintenanceRequestMessage::VISIBILITY_INTERNAL,
            ))
            ->values()
            ->map(fn (Media $media): array => self::attachmentPayload($media))
            ->all());

        return [
            'id' => $this->id,
            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->id, 'name' => $this->client->name]),
            'status' => $this->status->value,
            'category' => $this->category,
            'impact' => $this->impact,
            'description' => $this->description,
            'public_response' => $this->public_response,
            'source' => $this->source,
            'maintainable_type' => $this->maintainable_type,
            'maintainable_id' => $this->maintainable_id,
            'luminaire_position_id' => $this->luminaire_position_id,
            'installation_snapshot' => $this->installation_snapshot,
            'intake_data' => $this->intake_data,
            'messages' => $messages,
            'attachments' => $attachments,
            'work_order_id' => $this->work_order_id,
            'work_order_ids' => $this->whenLoaded('workOrders', fn () => $this->workOrders->pluck('id')->all()),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'reopened_at' => $this->reopened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public static function attachmentPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'visibility' => $media->getCustomProperty('visibility', FoMaintenanceRequestMessage::VISIBILITY_PUBLIC),
            'message_id' => $media->getCustomProperty('message_id'),
            'url' => url("/api/v1/fieldops/maintenance-request-attachments/{$media->id}"),
        ];
    }
}
