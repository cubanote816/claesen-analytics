<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;

/**
 * CLA-720: the site every panel serves (config('organizations.panel_sites')). Website screens
 * resolve it while their table is built (Site::forPanelOrFail()), so the runtime action inventory
 * needs these rows; a freshly migrated database only has Claesen's.
 */
trait SeedsPanelSites
{
    protected function seedPanelSites(): void
    {
        foreach (array_unique(array_values((array) config('organizations.panel_sites'))) as $key) {
            if (! Site::query()->where('key', $key)->exists()) {
                Site::factory()->create([
                    'organization_id' => Organization::factory()->create(['slug' => $key])->id,
                    'key' => $key,
                    'status' => Site::STATUS_ACTIVE,
                ]);
            }
        }
    }
}
