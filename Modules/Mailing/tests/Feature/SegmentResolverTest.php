<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Models\SuppressionEntry;
use Modules\Mailing\Services\SegmentResolverService;
use Modules\Prospects\Models\Prospect;
use Tests\TestCase;

class SegmentResolverTest extends TestCase
{
    use RefreshDatabase;

    private SegmentResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(SegmentResolverService::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function prospect(array $attrs = []): Prospect
    {
        return Prospect::factory()->create(array_merge([
            'unsubscribed_at' => null,
        ], $attrs));
    }

    private function sentMessage(Prospect $prospect, Campaign $campaign): CampaignMessage
    {
        return CampaignMessage::factory()->sent()->create([
            'prospect_id' => $prospect->id,
            'campaign_id' => $campaign->id,
            'email'       => 'test@example.com',
        ]);
    }

    private function recordEvent(CampaignMessage $message, MessageEventType $type, int $daysAgo = 0): void
    {
        MessageEvent::create([
            'message_id'  => $message->id,
            'event_type'  => $type,
            'occurred_at' => now()->subDays($daysAgo),
        ]);
    }

    // -------------------------------------------------------------------------
    // all_subscribed baseline
    // -------------------------------------------------------------------------

    public function test_subscribed_prospect_included_without_filters(): void
    {
        $p = $this->prospect();

        $ids = $this->resolver->resolveIds([]);

        $this->assertContains($p->id, $ids);
    }

    public function test_unsubscribed_prospect_always_excluded(): void
    {
        $p = $this->prospect(['unsubscribed_at' => now()]);

        $ids = $this->resolver->resolveIds([]);

        $this->assertNotContains($p->id, $ids);
    }

    // -------------------------------------------------------------------------
    // has_event
    // -------------------------------------------------------------------------

    public function test_has_event_includes_prospect_who_clicked(): void
    {
        $clicker  = $this->prospect();
        $noClick  = $this->prospect();
        $campaign = Campaign::factory()->create();

        $this->recordEvent($this->sentMessage($clicker, $campaign), MessageEventType::CLICKED);

        $ids = $this->resolver->resolveIds([
            'rules' => [['type' => 'has_event', 'event_type' => 'clicked']],
        ]);

        $this->assertContains($clicker->id, $ids);
        $this->assertNotContains($noClick->id, $ids);
    }

    public function test_has_event_scoped_to_specific_campaign(): void
    {
        $p1       = $this->prospect();
        $p2       = $this->prospect();
        $campaign = Campaign::factory()->create();
        $other    = Campaign::factory()->create();

        $this->recordEvent($this->sentMessage($p1, $campaign), MessageEventType::CLICKED);
        $this->recordEvent($this->sentMessage($p2, $other), MessageEventType::CLICKED);

        $ids = $this->resolver->resolveIds([
            'rules' => [['type' => 'has_event', 'event_type' => 'clicked', 'campaign_id' => $campaign->id]],
        ]);

        $this->assertContains($p1->id, $ids);
        $this->assertNotContains($p2->id, $ids);
    }

    public function test_has_event_respects_within_days_window(): void
    {
        $recent = $this->prospect();
        $old    = $this->prospect();
        $c      = Campaign::factory()->create();

        $this->recordEvent($this->sentMessage($recent, $c), MessageEventType::CLICKED, daysAgo: 5);
        $this->recordEvent($this->sentMessage($old, $c), MessageEventType::CLICKED, daysAgo: 45);

        $ids = $this->resolver->resolveIds([
            'rules' => [['type' => 'has_event', 'event_type' => 'clicked', 'within_days' => 30]],
        ]);

        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    // -------------------------------------------------------------------------
    // has_no_event
    // -------------------------------------------------------------------------

    public function test_has_no_event_excludes_hard_bounced(): void
    {
        $clean   = $this->prospect();
        $bounced = $this->prospect();
        $c       = Campaign::factory()->create();

        $this->recordEvent($this->sentMessage($bounced, $c), MessageEventType::BOUNCED_HARD);

        $ids = $this->resolver->resolveIds([
            'rules' => [['type' => 'has_no_event', 'event_type' => 'bounced_hard']],
        ]);

        $this->assertContains($clean->id, $ids);
        $this->assertNotContains($bounced->id, $ids);
    }

    // -------------------------------------------------------------------------
    // prospect_field
    // -------------------------------------------------------------------------

    public function test_prospect_field_equals_language(): void
    {
        $nl = $this->prospect(['language' => 'nl']);
        $fr = $this->prospect(['language' => 'fr']);

        $ids = $this->resolver->resolveIds([
            'rules' => [['type' => 'prospect_field', 'field' => 'language', 'operator' => '=', 'value' => 'nl']],
        ]);

        $this->assertContains($nl->id, $ids);
        $this->assertNotContains($fr->id, $ids);
    }

    public function test_prospect_field_in_federation(): void
    {
        $rbfa  = $this->prospect(['federation' => 'RBFA']);
        $lbfa  = $this->prospect(['federation' => 'LBFA']);
        $other = $this->prospect(['federation' => 'AFT']);

        $ids = $this->resolver->resolveIds([
            'rules' => [['type' => 'prospect_field', 'field' => 'federation', 'operator' => 'in', 'value' => ['RBFA', 'LBFA']]],
        ]);

        $this->assertContains($rbfa->id, $ids);
        $this->assertContains($lbfa->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_disallowed_field_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not allowed/');

        $this->resolver->resolveIds([
            'rules' => [['type' => 'prospect_field', 'field' => 'name', 'operator' => '=', 'value' => 'foo']],
        ]);
    }

    // -------------------------------------------------------------------------
    // OR operator
    // -------------------------------------------------------------------------

    public function test_or_operator_includes_prospect_matching_either_rule(): void
    {
        $nl      = $this->prospect(['language' => 'nl', 'federation' => 'AFT']);
        $rbfa_fr = $this->prospect(['language' => 'fr', 'federation' => 'RBFA']);
        $neither = $this->prospect(['language' => 'fr', 'federation' => 'AFT']);

        $ids = $this->resolver->resolveIds([
            'operator' => 'OR',
            'rules'    => [
                ['type' => 'prospect_field', 'field' => 'language',   'operator' => '=', 'value' => 'nl'],
                ['type' => 'prospect_field', 'field' => 'federation', 'operator' => '=', 'value' => 'RBFA'],
            ],
        ]);

        $this->assertContains($nl->id, $ids);
        $this->assertContains($rbfa_fr->id, $ids);
        $this->assertNotContains($neither->id, $ids);
    }

    // -------------------------------------------------------------------------
    // CRITICAL: suppressed prospect excluded even when matching an OR rule
    // -------------------------------------------------------------------------

    public function test_suppressed_prospect_excluded_even_when_or_rule_matches(): void
    {
        // This prospect would match the OR segment, but is in the suppression list.
        $suppressed = $this->prospect(['language' => 'nl']);
        $eligible   = $this->prospect(['language' => 'nl']);

        // Suppress by prospect_id (as the parser does for hard bounces)
        SuppressionEntry::create([
            'email'         => 'suppressed@example.com',
            'prospect_id'   => $suppressed->id,
            'reason'        => 'hard_bounce',
            'suppressed_at' => now(),
        ]);

        $ids = $this->resolver->resolveIds([
            'operator' => 'OR',
            'rules'    => [
                ['type' => 'prospect_field', 'field' => 'language', 'operator' => '=', 'value' => 'nl'],
                ['type' => 'prospect_field', 'field' => 'language', 'operator' => '=', 'value' => 'fr'],
            ],
        ]);

        $this->assertNotContains($suppressed->id, $ids, 'Suppressed prospect must be excluded even when matching OR rule.');
        $this->assertContains($eligible->id, $ids);
    }

    // -------------------------------------------------------------------------
    // count() vs resolveIds()
    // -------------------------------------------------------------------------

    public function test_count_returns_integer_without_loading_models(): void
    {
        $this->prospect(['language' => 'nl']);
        $this->prospect(['language' => 'nl']);
        $this->prospect(['language' => 'fr']);

        $count = $this->resolver->count([
            'rules' => [['type' => 'prospect_field', 'field' => 'language', 'operator' => '=', 'value' => 'nl']],
        ]);

        $this->assertSame(2, $count);
    }
}
