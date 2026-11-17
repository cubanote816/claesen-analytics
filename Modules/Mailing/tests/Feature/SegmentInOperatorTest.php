<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Services\SegmentResolverService;
use Modules\Prospects\Models\Prospect;
use Tests\TestCase;

class SegmentInOperatorTest extends TestCase
{
    use RefreshDatabase;

    private SegmentResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(SegmentResolverService::class);
    }

    private function prospect(string $federation): Prospect
    {
        return Prospect::factory()->create(['unsubscribed_at' => null, 'federation' => $federation]);
    }

    private function ids(string $operator, mixed $value): array
    {
        return $this->resolver->resolveIds([
            'rules' => [['type' => 'prospect_field', 'field' => 'federation', 'operator' => $operator, 'value' => $value]],
        ]);
    }

    public function test_comma_separated_list_matches_each_item(): void
    {
        $rbfa = $this->prospect('RBFA');
        $lbfa = $this->prospect('LBFA');
        $aft  = $this->prospect('AFT');

        $ids = $this->ids('in', 'RBFA,LBFA');

        $this->assertContains($rbfa->id, $ids);
        $this->assertContains($lbfa->id, $ids);
        $this->assertNotContains($aft->id, $ids);
        $this->assertSame(2, $this->resolver->preview(['rules' => [['type' => 'prospect_field', 'field' => 'federation', 'operator' => 'in', 'value' => 'RBFA,LBFA']]])['total']);
    }

    public function test_spaces_around_items_are_trimmed(): void
    {
        $rbfa = $this->prospect('RBFA');
        $lbfa = $this->prospect('LBFA');
        $aft  = $this->prospect('AFT');

        $ids = $this->ids('in', 'RBFA, LBFA');

        $this->assertContains($rbfa->id, $ids);
        $this->assertContains($lbfa->id, $ids);
        $this->assertNotContains($aft->id, $ids);
    }

    public function test_single_value_is_unchanged(): void
    {
        $rbfa = $this->prospect('RBFA');
        $lbfa = $this->prospect('LBFA');

        $ids = $this->ids('in', 'RBFA');

        $this->assertContains($rbfa->id, $ids);
        $this->assertNotContains($lbfa->id, $ids);
    }

    public function test_empty_items_are_ignored(): void
    {
        $rbfa  = $this->prospect('RBFA');
        $empty = $this->prospect('');

        $ids = $this->ids('in', 'RBFA,,');

        $this->assertContains($rbfa->id, $ids);
        $this->assertNotContains($empty->id, $ids);
    }

    public function test_equals_operator_keeps_comma_literal(): void
    {
        $literal = $this->prospect('RBFA,LBFA');
        $rbfa    = $this->prospect('RBFA');

        $ids = $this->ids('=', 'RBFA,LBFA');

        $this->assertContains($literal->id, $ids);
        $this->assertNotContains($rbfa->id, $ids);
    }

    public function test_array_value_still_works(): void
    {
        $rbfa = $this->prospect('RBFA');
        $lbfa = $this->prospect('LBFA');
        $aft  = $this->prospect('AFT');

        $ids = $this->ids('in', ['RBFA', 'LBFA']);

        $this->assertContains($rbfa->id, $ids);
        $this->assertContains($lbfa->id, $ids);
        $this->assertNotContains($aft->id, $ids);
    }

    public function test_empty_lists_and_stored_segment_campaign_agree_with_preview(): void
    {
        $rbfa = $this->prospect('RBFA');
        $this->prospect('');

        foreach (['', ' , , ', [], ' RBFA,,RBFA '] as $value) {
            $filters = ['rules' => [[
                'type' => 'prospect_field',
                'field' => 'federation',
                'operator' => 'in',
                'value' => $value,
            ]]];
            $campaign = Campaign::factory()->create([
                'audience_type' => AudienceType::SEGMENT,
                'audience_filters' => $filters,
            ]);
            $expected = $value === ' RBFA,,RBFA ' ? [$rbfa->id] : [];
            $label = var_export($value, true);

            $this->assertEqualsCanonicalizing($expected, $campaign->fresh()->resolveAudience(), $label);
            $preview = $this->resolver->preview($filters);
            $this->assertSame(count($expected), $preview['total'], $label);
            $this->assertEqualsCanonicalizing($expected, $preview['prospects']->modelKeys(), $label);
        }
    }
}
