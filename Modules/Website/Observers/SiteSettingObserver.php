<?php

namespace Modules\Website\Observers;

use Modules\Website\Models\SiteSetting;
use Modules\Website\Services\StaticSitePublicationService;

/**
 * Editing a site setting marks that site dirty; it does not publish.
 *
 * Without this observer the settings screen left no trace at all: the rebuild observers
 * covered Project and Media only, so changing the address, the opening hours or any other
 * company fact neither published nor registered that something was waiting. Under
 * deliberate publishing the trace IS the feature — it is what tells the client that what
 * they just edited has not gone live yet.
 *
 * Bound to created/updated/deleted rather than saved, deliberately: the settings screen
 * writes every allowed key with updateOrCreate() on each submit, and `saved` fires for each
 * of those rows even when nothing changed. `updated` only fires when the row is dirty, so
 * re-saving a screen without edits does not raise a false "pending".
 */
class SiteSettingObserver
{
    public function __construct(
        private readonly StaticSitePublicationService $publicationService,
    ) {}

    public function created(SiteSetting $setting): void
    {
        $this->publicationService->markContentChanged($setting->site_id);
    }

    public function updated(SiteSetting $setting): void
    {
        $this->publicationService->markContentChanged($setting->site_id);
    }

    public function deleted(SiteSetting $setting): void
    {
        $this->publicationService->markContentChanged($setting->site_id);
    }
}
