<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mailing\Models\ContactPreference;
use Modules\Mailing\Services\PreferenceService;
use Modules\Prospects\Models\Prospect;
use Tests\TestCase;

class PreferencesControllerTest extends TestCase
{
    use RefreshDatabase;

    private function prospect(array $attrs = []): Prospect
    {
        return Prospect::factory()->create(array_merge([
            'unsubscribed_at' => null,
            'language'        => 'nl',
        ], $attrs));
    }

    private function validToken(Prospect $prospect): string
    {
        return $prospect->getUnsubscribeToken();
    }

    // -------------------------------------------------------------------------
    // GET — show
    // -------------------------------------------------------------------------

    public function test_shows_preferences_page_with_valid_token(): void
    {
        $prospect = $this->prospect();

        $response = $this->get(route('mailing.preferences.show', [
            'prospect' => $prospect->id,
            'token'    => $this->validToken($prospect),
        ]));

        $response->assertOk();
        $response->assertViewIs('mailing::preferences');
        $response->assertViewHas('preferences');
        $response->assertViewHas('categories');
    }

    public function test_returns_403_for_invalid_token(): void
    {
        $prospect = $this->prospect();

        $this->get(route('mailing.preferences.show', [
            'prospect' => $prospect->id,
            'token'    => 'totally-wrong-token',
        ]))->assertForbidden();
    }

    public function test_default_subscribed_true_when_no_preference_record(): void
    {
        $prospect = $this->prospect();

        $response = $this->get(route('mailing.preferences.show', [
            'prospect' => $prospect->id,
            'token'    => $this->validToken($prospect),
        ]));

        $preferences = $response->viewData('preferences');

        foreach ($preferences as $subscribed) {
            $this->assertTrue($subscribed, 'Default should be subscribed (true) when no record exists.');
        }
    }

    // -------------------------------------------------------------------------
    // POST — update
    // -------------------------------------------------------------------------

    public function test_updates_preferences_and_redirects(): void
    {
        $prospect   = $this->prospect();
        $categories = array_keys(config('mailing.preference_categories', []));

        // Subscribe to first category, unsubscribe from the rest
        $payload = [];
        if (! empty($categories)) {
            $payload[$categories[0]] = '1';
        }

        $this->post(
            route('mailing.preferences.update', [
                'prospect' => $prospect->id,
                'token'    => $this->validToken($prospect),
            ]),
            $payload
        )->assertRedirect();

        if (count($categories) > 1) {
            $this->assertDatabaseHas('mailing_contact_preferences', [
                'prospect_id' => $prospect->id,
                'category'    => $categories[1],
                'subscribed'  => false,
            ]);
        }
    }

    public function test_update_is_idempotent(): void
    {
        $prospect = $this->prospect();
        $token    = $this->validToken($prospect);

        $categories = array_keys(config('mailing.preference_categories', []));
        $payload    = [];
        foreach ($categories as $cat) {
            $payload[$cat] = '1';
        }

        $this->post(route('mailing.preferences.update', ['prospect' => $prospect->id, 'token' => $token]), $payload);
        $this->post(route('mailing.preferences.update', ['prospect' => $prospect->id, 'token' => $token]), $payload);

        // Exactly one record per category — no duplicates
        foreach ($categories as $cat) {
            $this->assertDatabaseCount('mailing_contact_preferences', count($categories));
        }
    }

    // -------------------------------------------------------------------------
    // Unchecked checkbox saved as false
    // -------------------------------------------------------------------------

    public function test_unchecked_category_is_saved_as_false(): void
    {
        $prospect   = $this->prospect();
        $categories = array_keys(config('mailing.preference_categories', []));

        if (empty($categories)) {
            $this->markTestSkipped('No categories defined in config.');
        }

        $firstCategory = $categories[0];

        // First: mark it as subscribed (true)
        ContactPreference::create([
            'prospect_id' => $prospect->id,
            'category'    => $firstCategory,
            'subscribed'  => true,
        ]);

        // POST without the checkbox for firstCategory (simulates uncheck)
        $payload = [];
        foreach (array_slice($categories, 1) as $cat) {
            $payload[$cat] = '1'; // all others checked
        }

        $this->post(
            route('mailing.preferences.update', [
                'prospect' => $prospect->id,
                'token'    => $this->validToken($prospect),
            ]),
            $payload
        );

        $this->assertDatabaseHas('mailing_contact_preferences', [
            'prospect_id' => $prospect->id,
            'category'    => $firstCategory,
            'subscribed'  => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Global unsubscribe is independent of category preferences
    // -------------------------------------------------------------------------

    public function test_globally_unsubscribed_prospect_can_manage_preferences(): void
    {
        $prospect = $this->prospect(['unsubscribed_at' => now()->subDay()]);

        $this->get(route('mailing.preferences.show', [
            'prospect' => $prospect->id,
            'token'    => $this->validToken($prospect),
        ]))->assertOk();
    }

    public function test_updating_category_to_subscribed_does_not_clear_global_unsubscribe(): void
    {
        $categories   = array_keys(config('mailing.preference_categories', []));
        $unsubscribedAt = now()->subDay();
        $prospect     = $this->prospect(['unsubscribed_at' => $unsubscribedAt]);

        // POST all categories as subscribed
        $payload = [];
        foreach ($categories as $cat) {
            $payload[$cat] = '1';
        }

        $this->post(
            route('mailing.preferences.update', [
                'prospect' => $prospect->id,
                'token'    => $this->validToken($prospect),
            ]),
            $payload
        );

        // unsubscribed_at must be untouched
        $this->assertDatabaseHas('prospects_prospects', [
            'id' => $prospect->id,
        ]);

        $fresh = $prospect->fresh();
        $this->assertNotNull($fresh->unsubscribed_at, 'Global unsubscribe must not be cleared by category preference update.');
    }

    // -------------------------------------------------------------------------
    // Unknown category is ignored
    // -------------------------------------------------------------------------

    public function test_unknown_category_in_post_is_ignored(): void
    {
        $prospect = $this->prospect();

        $this->post(
            route('mailing.preferences.update', [
                'prospect' => $prospect->id,
                'token'    => $this->validToken($prospect),
            ]),
            ['totally_fake_category' => '1']
        )->assertRedirect();

        $this->assertDatabaseMissing('mailing_contact_preferences', [
            'prospect_id' => $prospect->id,
            'category'    => 'totally_fake_category',
        ]);
    }

    // -------------------------------------------------------------------------
    // PreferenceService::isSubscribedToCategory
    // -------------------------------------------------------------------------

    public function test_is_subscribed_defaults_to_true_without_record(): void
    {
        $prospect = $this->prospect();
        $service  = app(PreferenceService::class);

        $this->assertTrue($service->isSubscribedToCategory($prospect, 'newsletter'));
    }
}
