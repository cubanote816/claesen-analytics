<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Models\Announcement;
use Modules\Website\Models\SiteSetting;
use Tests\TestCase;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * End-to-end HTTP coverage of Modules\Website\Http\Controllers\SiteContentController,
 * following the same pattern as CLA-471's PublicApiSiteScopingTest (ResolveRequestSite
 * on the real /v1/website/* routes).
 */
final class SiteContentApiTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    public function test_settings_endpoint_returns_only_whitelisted_keys_with_resolved_values(): void
    {
        SiteSetting::factory()->create(['key' => 'phone', 'value' => '+32 111', 'type' => SiteSetting::TYPE_TEXT]);
        SiteSetting::factory()->create([
            'key' => 'hours',
            'value' => ['nl' => 'Ma-Vr: 9-17u', 'en' => 'Mon-Fri: 9am-5pm'],
            'type' => SiteSetting::TYPE_TRANSLATABLE,
        ]);

        $response = $this->getJson('/v1/website/settings')->assertOk();

        $response->assertJsonPath('data.phone', '+32 111');
        $response->assertJsonStructure(['data' => ['phone', 'hours']]);

        // Never leaks id/site_id/type/timestamps or any Site model field.
        $body = $response->json();
        $this->assertArrayNotHasKey('id', $body['data']);
        $this->assertStringNotContainsString('webhook', $response->getContent());
        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    public function test_settings_endpoint_resolves_translatable_value_for_the_current_locale(): void
    {
        SiteSetting::factory()->create([
            'key' => 'hours',
            'value' => ['nl' => 'Ma-Vr: 9-17u', 'en' => 'Mon-Fri: 9am-5pm'],
            'type' => SiteSetting::TYPE_TRANSLATABLE,
        ]);

        $this->getJson('/v1/website/settings', ['Accept-Language' => 'nl'])
            ->assertOk()
            ->assertJsonPath('data.hours', 'Ma-Vr: 9-17u');
    }

    public function test_announcements_endpoint_only_returns_published_items_within_their_date_window(): void
    {
        $active = Announcement::factory()->published()->create(['message' => ['en' => 'Active notice']]);
        Announcement::factory()->create(['message' => ['en' => 'Draft notice']]); // draft
        Announcement::factory()->archived()->create(['message' => ['en' => 'Archived notice']]);
        Announcement::factory()->published()->create([
            'message' => ['en' => 'Not yet started'],
            'starts_at' => now()->addDay(),
        ]);
        Announcement::factory()->published()->create([
            'message' => ['en' => 'Already ended'],
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/v1/website/announcements')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $active->id);
        $response->assertJsonPath('data.0.message', 'Active notice');
        $response->assertJsonStructure(['data' => [['id', 'message', 'starts_at', 'ends_at']]]);
    }

    public function test_settings_response_is_cached_and_invalidated_on_save(): void
    {
        $setting = SiteSetting::factory()->create(['key' => 'phone', 'value' => '+32 111', 'type' => SiteSetting::TYPE_TEXT]);

        $this->getJson('/v1/website/settings')->assertJsonPath('data.phone', '+32 111');

        // Bypass the model's own cache-forgetting save() to prove the cache
        // really was populated (a raw DB write does not trigger the observer).
        DB::table('website_site_settings')->where('id', $setting->id)->update(['value' => json_encode('STALE')]);
        $this->getJson('/v1/website/settings')->assertJsonPath('data.phone', '+32 111');

        // A real model save() forgets the cache for this site.
        $setting->update(['value' => '+32 999']);
        $this->getJson('/v1/website/settings')->assertJsonPath('data.phone', '+32 999');
    }

    public function test_announcements_response_is_cached_and_invalidated_on_save(): void
    {
        $announcement = Announcement::factory()->published()->create(['message' => ['en' => 'First']]);

        $this->getJson('/v1/website/announcements')->assertJsonPath('data.0.message', 'First');

        $announcement->update(['message' => ['en' => 'Updated']]);
        $this->getJson('/v1/website/announcements')->assertJsonPath('data.0.message', 'Updated');
    }

    public function test_settings_and_announcements_are_isolated_per_site(): void
    {
        $this->enableOrganizationEnforcement();

        SiteSetting::factory()->create(['key' => 'phone', 'value' => '+32 CLAESEN', 'type' => SiteSetting::TYPE_TEXT]);
        Announcement::factory()->published()->create(['message' => ['en' => 'Claesen notice']]);

        $bertelsSite = $this->bertelsFixtureSite();
        SiteSetting::factory()->create([
            'site_id' => $bertelsSite->id,
            'key' => 'phone',
            'value' => '+32 BERTELS',
            'type' => SiteSetting::TYPE_TEXT,
        ]);
        Announcement::factory()->published()->create([
            'site_id' => $bertelsSite->id,
            'message' => ['en' => 'Bertels notice'],
        ]);

        $this->getJson('/v1/website/settings?site='.$bertelsSite->key)
            ->assertJsonPath('data.phone', '+32 BERTELS');

        $this->getJson('/v1/website/announcements?site='.$bertelsSite->key)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message', 'Bertels notice');

        $this->getJson('/v1/website/settings')
            ->assertJsonPath('data.phone', '+32 CLAESEN');
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }
}
