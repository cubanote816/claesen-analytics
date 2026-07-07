<?php

declare(strict_types=1);

namespace Modules\Analytics\Observers;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Modules\Analytics\Enums\AppSource;
use Modules\Analytics\Enums\EventName;
use Modules\Analytics\Services\EventTracker;

/**
 * Attached to every model backing a Filament resource — see
 * AnalyticsServiceProvider::registerResourceEventObservers(). Fires
 * resource_created/resource_updated only when the mutation happened inside
 * an actual Filament panel request: Filament::getCurrentPanel() is set by
 * Filament's SetUpPanel middleware, which only runs on panel-routed HTTP
 * requests. A sync command or an API write touching the same model never
 * goes through that middleware, so it is excluded automatically — no
 * per-module opt-out list needed (CLA-231).
 */
class TrackableModelObserver
{
    public function __construct(private readonly EventTracker $tracker) {}

    public function created(Model $model): void
    {
        $this->record(EventName::RESOURCE_CREATED, $model);
    }

    public function updated(Model $model): void
    {
        $this->record(EventName::RESOURCE_UPDATED, $model);
    }

    private function record(EventName $eventName, Model $model): void
    {
        if (Filament::getCurrentPanel() === null) {
            return;
        }

        $this->tracker->track(
            eventName: $eventName,
            app: AppSource::BACKOFFICE,
            sessionId: session()->getId(),
            user: auth()->user(),
            entityType: class_basename($model),
            entityId: (string) $model->getKey(),
        );
    }
}
