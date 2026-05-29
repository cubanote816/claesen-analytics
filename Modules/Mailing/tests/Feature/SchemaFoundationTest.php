<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\ContactPreference;
use Modules\Prospects\Models\Prospect;
use Tests\TestCase;

class SchemaFoundationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // mailing_campaigns — new Phase 2 columns
    // -------------------------------------------------------------------------

    public function test_campaign_audience_type_defaults_to_all_subscribed(): void
    {
        $campaign = Campaign::factory()->create();

        $this->assertSame('all_subscribed', $campaign->fresh()->audience_type);
    }

    public function test_campaign_accepts_segment_audience_with_filters(): void
    {
        $filters = ['min_clicks' => 2, 'category' => 'offers'];

        $campaign = Campaign::factory()->create([
            'audience_type'    => 'segment',
            'audience_filters' => $filters,
        ]);

        $fresh = $campaign->fresh();
        $this->assertSame('segment', $fresh->audience_type);
        $this->assertSame($filters, $fresh->audience_filters);
    }

    public function test_audience_filters_cast_to_array(): void
    {
        $campaign = Campaign::factory()->create([
            'audience_filters' => ['region' => 'Antwerpen'],
        ]);

        $this->assertIsArray($campaign->fresh()->audience_filters);
    }

    public function test_campaign_accepts_scheduling_columns(): void
    {
        $scheduledAt = now()->addDays(3)->startOfMinute();

        $campaign = Campaign::factory()->create([
            'scheduled_at' => $scheduledAt,
            'timezone'     => 'Europe/Brussels',
        ]);

        $fresh = $campaign->fresh();
        $this->assertSame('Europe/Brussels', $fresh->timezone);
        $this->assertEquals($scheduledAt->toDateTimeString(), $fresh->scheduled_at->toDateTimeString());
    }

    public function test_campaign_audience_filters_nullable(): void
    {
        $campaign = Campaign::factory()->create(['audience_filters' => null]);

        $this->assertNull($campaign->fresh()->audience_filters);
    }

    // -------------------------------------------------------------------------
    // mailing_contact_preferences
    // -------------------------------------------------------------------------

    public function test_contact_preference_can_be_created(): void
    {
        $prospectId = $this->createProspect();

        ContactPreference::create([
            'prospect_id' => $prospectId,
            'category'    => 'offers',
            'subscribed'  => true,
        ]);

        $this->assertDatabaseHas('mailing_contact_preferences', [
            'prospect_id' => $prospectId,
            'category'    => 'offers',
            'subscribed'  => 1,
        ]);
    }

    public function test_contact_preference_subscribed_defaults_to_true(): void
    {
        $prospectId = $this->createProspect();

        $pref = ContactPreference::create([
            'prospect_id' => $prospectId,
            'category'    => 'newsletter',
        ]);

        $this->assertTrue($pref->fresh()->subscribed);
    }

    public function test_contact_preference_unique_per_prospect_and_category(): void
    {
        $prospectId = $this->createProspect();

        ContactPreference::create([
            'prospect_id' => $prospectId,
            'category'    => 'events',
            'subscribed'  => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ContactPreference::create([
            'prospect_id' => $prospectId,
            'category'    => 'events',
            'subscribed'  => false,
        ]);
    }

    public function test_same_category_can_exist_for_different_prospects(): void
    {
        $p1 = $this->createProspect();
        $p2 = $this->createProspect();

        ContactPreference::create(['prospect_id' => $p1, 'category' => 'offers']);
        ContactPreference::create(['prospect_id' => $p2, 'category' => 'offers']);

        $this->assertSame(2, ContactPreference::where('category', 'offers')->count());
    }

    public function test_contact_preference_cascade_deletes_with_prospect(): void
    {
        $prospectId = $this->createProspect();

        ContactPreference::create(['prospect_id' => $prospectId, 'category' => 'offers']);

        DB::table('prospects_prospects')->where('id', $prospectId)->delete();

        $this->assertDatabaseMissing('mailing_contact_preferences', ['prospect_id' => $prospectId]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createProspect(): int
    {
        $regionId = DB::table('prospects_regions')->value('id')
            ?? DB::table('prospects_regions')->insertGetId([
                'name'       => 'Test Region',
                'slug'       => 'test-region',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('prospects_prospects')->insertGetId([
            'name'       => fake()->company(),
            'type'       => 'football_club',
            'region_id'  => $regionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
