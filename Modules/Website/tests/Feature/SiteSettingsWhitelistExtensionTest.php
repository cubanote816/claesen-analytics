<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Site;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\SiteSetting;
use Tests\TestCase;

/**
 * CLA-479 (dynamic part): the extended SiteSetting whitelist for the
 * Electro Bertels company facts (backend-requirements.md §7.2).
 *
 * Freezes three things:
 *  - every §7.2 key is whitelisted with the exact type;
 *  - the pre-existing Claesen keys are untouched (same keys, same types —
 *    'address' deliberately stays `text`);
 *  - the public /settings endpoint resolves `address_country`
 *    (type 'translatable') per the request locale.
 */
final class SiteSettingsWhitelistExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        app()->setLocale('nl');
    }

    public function test_every_company_fact_key_is_whitelisted_with_the_required_type(): void
    {
        $expected = [
            'legal_name' => 'text',
            'founded_year' => 'json',
            'phone_display' => 'text',
            'phone_tel' => 'text',
            'whatsapp_display' => 'text',
            'whatsapp_url' => 'text',
            'address_structured' => 'json',
            'address_country' => 'translatable',
            'maps_embed_url' => 'text',
            'maps_directions_url' => 'text',
            'maps_consent_mode' => 'text',
            'vat_number' => 'text',
            'opening_hours' => 'json',
            'contact_consent_version' => 'text',
        ];

        foreach ($expected as $key => $type) {
            $this->assertSame(
                $type,
                SiteSetting::typeForKey($key),
                "Whitelisted key [{$key}] must have type [{$type}]."
            );
        }
    }

    public function test_the_preexisting_claesen_keys_keep_their_types(): void
    {
        $this->assertSame('translatable', SiteSetting::typeForKey('hours'));
        $this->assertSame('text', SiteSetting::typeForKey('phone'));
        $this->assertSame('text', SiteSetting::typeForKey('email'));
        // Decision 2A: 'address' stays a verbatim Claesen string — the
        // structured variant lives in the separate 'address_structured' key.
        $this->assertSame('text', SiteSetting::typeForKey('address'));
        $this->assertSame('json', SiteSetting::typeForKey('social_links'));
    }

    public function test_whitelist_helpers_recognise_the_new_keys_and_reject_unknown_ones(): void
    {
        foreach (['legal_name', 'address_structured', 'opening_hours', 'contact_consent_version'] as $key) {
            $this->assertTrue(SiteSetting::isAllowedKey($key));
        }

        $this->assertFalse(SiteSetting::isAllowedKey('not_whitelisted'));
        $this->assertNull(SiteSetting::typeForKey('not_whitelisted'));
    }

    public function test_new_keys_persist_and_resolve_through_the_model(): void
    {
        $siteId = Site::claesenId();

        SiteSetting::create(['site_id' => $siteId, 'key' => 'legal_name', 'type' => 'text', 'value' => 'Electro Bertels']);
        SiteSetting::create(['site_id' => $siteId, 'key' => 'address_structured', 'type' => 'json', 'value' => [
            'street' => 'Benoit Jansenstraat 4', 'postal_code' => '2490', 'city' => 'Balen', 'country_code' => 'BE',
        ]]);
        SiteSetting::create(['site_id' => $siteId, 'key' => 'opening_hours', 'type' => 'json', 'value' => [
            ['day_key' => 'saturday', 'hours' => [['open' => '09:00', 'close' => '12:00']]],
        ]]);

        $this->assertDatabaseHas('website_site_settings', ['key' => 'legal_name', 'type' => 'text']);
        $this->assertSame('Electro Bertels', SiteSetting::query()->where('key', 'legal_name')->first()->resolvedValue());
    }

    public function test_settings_endpoint_serves_the_company_facts_with_address_country_resolved_per_locale(): void
    {
        $siteId = Site::claesenId();

        SiteSetting::create(['site_id' => $siteId, 'key' => 'legal_name', 'type' => 'text', 'value' => 'Electro Bertels']);
        SiteSetting::create(['site_id' => $siteId, 'key' => 'phone_tel', 'type' => 'text', 'value' => 'tel:+3214813080']);
        SiteSetting::create(['site_id' => $siteId, 'key' => 'founded_year', 'type' => 'json', 'value' => 1977]);
        SiteSetting::create(['site_id' => $siteId, 'key' => 'address_country', 'type' => 'translatable', 'value' => [
            'nl' => 'België', 'fr' => 'Belgique', 'en' => 'Belgium', 'de' => 'Belgien',
        ]]);
        SiteSetting::create(['site_id' => $siteId, 'key' => 'opening_hours', 'type' => 'json', 'value' => [
            ['day_key' => 'tuesdayToFriday', 'hours' => [['open' => '09:00', 'close' => '18:00']]],
            ['day_key' => 'sunday', 'hours' => null],
        ]]);

        $this->getJson('/v1/website/settings', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'Electro Bertels')
            ->assertJsonPath('data.phone_tel', 'tel:+3214813080')
            ->assertJsonPath('data.founded_year', 1977)
            // The known "België" bug regression: a FR request gets the FR
            // country name, never the Dutch one.
            ->assertJsonPath('data.address_country', 'Belgique')
            ->assertJsonPath('data.opening_hours.0.day_key', 'tuesdayToFriday')
            ->assertJsonPath('data.opening_hours.1.hours', null);

        $this->getJson('/v1/website/settings', ['Accept-Language' => 'de'])
            ->assertOk()
            ->assertJsonPath('data.address_country', 'Belgien');
    }

    public function test_the_settings_endpoint_stays_scoped_to_the_resolved_site(): void
    {
        $bertelsSite = Site::factory()->create(['key' => 'bertels-fixture-settings']);

        SiteSetting::create(['site_id' => Site::claesenId(), 'key' => 'legal_name', 'type' => 'text', 'value' => 'Claesen BV']);
        SiteSetting::create(['site_id' => $bertelsSite->id, 'key' => 'legal_name', 'type' => 'text', 'value' => 'Electro Bertels']);

        // SiteSetting::cachedForCurrentSite() caches per site id — make the
        // second request rebuild its own cache entry instead of reading the
        // first site's.
        Cache::forget('website.site-settings.'.Site::claesenId());
        Cache::forget("website.site-settings.{$bertelsSite->id}");

        $this->getJson('/v1/website/settings?site=bertels-fixture-settings')
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'Electro Bertels');

        $this->getJson('/v1/website/settings')
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'Claesen BV');
    }
}
