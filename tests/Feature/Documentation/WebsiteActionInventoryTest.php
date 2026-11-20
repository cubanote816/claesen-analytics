<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Support\Documentation\ModuleActionInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Tests\TestCase;

/**
 * CLA-728: the runtime inventory on Website's screens. Some of them resolve the site their panel
 * serves while the table is built (Site::forPanelOrFail()), so every configured panel site exists.
 */
class WebsiteActionInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    /** A factory such as PublishSiteNowAction::make() is named after the action it builds. */
    public function test_action_factories_use_the_name_of_the_action_they_build(): void
    {
        $anchors = array_keys(ModuleActionInventory::anchors(
            base_path('Modules/Website'),
            [base_path('app/Filament/Clusters/Website')],
        ));

        $this->assertContains('action-project-publish_now', $anchors);
        $this->assertContains('action-general-settings-page-publish_now', $anchors);
        $this->assertSame([], array_values(array_filter(
            $anchors,
            fn (string $anchor): bool => str_contains($anchor, 'publish_site_now') || str_contains($anchor, 'publish-site-now'),
        )));
    }
}
