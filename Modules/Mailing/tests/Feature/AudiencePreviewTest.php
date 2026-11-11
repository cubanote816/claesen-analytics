<?php

namespace Modules\Mailing\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Filament\Resources\CampaignResource;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\Mailing\Filament\Resources\CampaignResource\Schemas\CampaignForm;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\SuppressionEntry;
use Modules\Mailing\Services\SegmentResolverService;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-672 — "Preview audience" lists who will receive the campaign (S1-S6).
 */
class AudiencePreviewTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        $this->region = Region::firstOrCreate(['name' => 'Flanders'], ['slug' => 'flanders']);
    }

    private function actAsAdmin(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get(CampaignResource::getUrl('create'))->assertSuccessful();
    }

    private function prospect(string $name, ?string $email = null, array $attrs = []): Prospect
    {
        $p = Prospect::create(array_merge([
            'name' => $name,
            'type' => 'football_club',
            'federation' => 'KBVB',
            'region_id' => $this->region->id,
        ], $attrs));

        if ($email !== null) {
            ProspectLocation::create(['prospect_id' => $p->id, 'contact_type' => 'main', 'email' => $email]);
        }

        return $p;
    }

    private function render(array $filters): string
    {
        return CampaignForm::previewContent($filters)->render();
    }

    // ---- S2 / S3: preview() ------------------------------------------------

    public function test_preview_excludes_unsubscribed_and_suppressed_and_orders_by_name(): void
    {
        $this->prospect('Zeta FC', 'z@example.com');
        $this->prospect('Alpha FC', 'a@example.com');
        $this->prospect('Gone FC', 'g@example.com', ['unsubscribed_at' => now()]);
        $suppressed = $this->prospect('Blocked FC', 'b@example.com');
        SuppressionEntry::create([
            'email' => 'b@example.com',
            'prospect_id' => $suppressed->id,
            'reason' => 'manual',
            'suppressed_at' => now(),
        ]);

        $result = app(SegmentResolverService::class)->preview([]);

        $this->assertSame(2, $result['total']);
        $this->assertSame(['Alpha FC', 'Zeta FC'], $result['prospects']->pluck('name')->all());
    }

    public function test_preview_limits_rows_but_reports_full_total(): void
    {
        for ($i = 1; $i <= 103; $i++) {
            $this->prospect(sprintf('Club %03d', $i));
        }

        $result = app(SegmentResolverService::class)->preview([]);

        $this->assertSame(103, $result['total']);
        $this->assertCount(100, $result['prospects']);
        $this->assertSame('Club 001', $result['prospects']->first()->name);
        $this->assertSame('Club 100', $result['prospects']->last()->name);
    }

    public function test_preview_still_counts_without_limit_override(): void
    {
        $this->prospect('One');
        $this->prospect('Two');

        $result = app(SegmentResolverService::class)->preview([], 1);

        $this->assertSame(2, $result['total']);
        $this->assertCount(1, $result['prospects']);
    }

    // ---- S1 / S3 / S6: rendered modal --------------------------------------

    public function test_modal_shows_total_columns_and_prospect_details(): void
    {
        $p = $this->prospect('Racing Genk', null, [
            'type' => 'football',
            'federation' => 'KBVB-X',
            'language' => 'nl',
            'contact_person' => 'Jan Peeters',
        ]);
        ProspectLocation::create(['prospect_id' => $p->id, 'contact_type' => 'main', 'email' => 'one@example.com, two@example.com']);

        $html = $this->render([]);

        $this->assertStringContainsString('1 recipients', $html);
        foreach (['Club', 'Sport', 'Federation', 'Region', 'Language', 'Contact person', 'Email(s)'] as $header) {
            $this->assertStringContainsString($header, $html);
        }
        foreach (['Racing Genk', 'football', 'KBVB-X', 'Flanders', 'nl', 'Jan Peeters', 'one@example.com, two@example.com'] as $value) {
            $this->assertStringContainsString($value, $html);
        }
        $this->assertStringContainsString('Send-time checks can still skip a few contacts: category opt-outs and suppressed addresses.', $html);
        $this->assertStringNotContainsString('more', $html);
    }

    public function test_modal_shows_placeholder_for_empty_values(): void
    {
        $this->prospect('Bare FC', null, ['federation' => null]);

        $this->assertStringContainsString('—', $this->render([]));
    }

    public function test_modal_escapes_output(): void
    {
        $this->prospect('<script>alert(1)</script>');

        $html = $this->render([]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_modal_says_how_many_more_when_over_the_limit(): void
    {
        for ($i = 1; $i <= 103; $i++) {
            $this->prospect(sprintf('Club %03d', $i));
        }

        $html = $this->render([]);

        $this->assertStringContainsString('103 recipients', $html);
        $this->assertStringContainsString('… and 3 more', $html);
        $this->assertStringNotContainsString('Club 101', $html);
    }

    public function test_modal_shows_empty_state_when_nobody_matches(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('No recipients match this audience.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_modal_shows_segment_error_instead_of_throwing(): void
    {
        $this->prospect('Some FC');

        $html = $this->render([
            'operator' => 'AND',
            'rules' => [['type' => 'prospect_field', 'field' => 'typo', 'operator' => '=', 'value' => 'x']],
        ]);

        $this->assertStringContainsString('Segment configuration error', $html);
        $this->assertStringContainsString(e("Field 'typo' is not allowed in segment filters."), $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    // ---- previewFilters ----------------------------------------------------

    private function getter(array $state): \Closure
    {
        return fn (string $key) => $state[$key] ?? null;
    }

    public function test_filters_for_all_subscribed_are_empty(): void
    {
        $filters = CampaignForm::previewFilters($this->getter([
            'audience_type' => AudienceType::ALL_SUBSCRIBED->value,
            'audience_filters.prospect_ids' => [1],
            'audience_filters.rules' => [['type' => 'has_event']],
        ]));

        $this->assertSame([], $filters);
    }

    public function test_filters_for_segment_never_carry_prospect_ids(): void
    {
        $rules = [['type' => 'prospect_field', 'field' => 'language', 'operator' => '=', 'value' => 'nl']];

        $filters = CampaignForm::previewFilters($this->getter([
            'audience_type' => AudienceType::SEGMENT->value,
            'audience_filters.operator' => 'OR',
            'audience_filters.rules' => $rules,
            'audience_filters.prospect_ids' => [1, 2],
        ]));

        $this->assertSame(['operator' => 'OR', 'rules' => $rules], $filters);
        $this->assertArrayNotHasKey('prospect_ids', $filters);
    }

    public function test_filters_for_manual_carry_only_prospect_ids(): void
    {
        $filters = CampaignForm::previewFilters($this->getter([
            'audience_type' => AudienceType::MANUAL->value,
            'audience_filters.prospect_ids' => [3, 4],
            'audience_filters.rules' => [['type' => 'has_event']],
        ]));

        $this->assertSame(['prospect_ids' => [3, 4]], $filters);
    }

    // ---- S2: same resolution as the send -----------------------------------

    public static function audienceTypes(): array
    {
        return [
            'all subscribed' => [AudienceType::ALL_SUBSCRIBED],
            'segment' => [AudienceType::SEGMENT],
            'manual' => [AudienceType::MANUAL],
        ];
    }

    #[DataProvider('audienceTypes')]
    public function test_preview_total_matches_resolve_audience(AudienceType $type): void
    {
        $a = $this->prospect('Alpha', 'a@example.com', ['language' => 'nl']);
        $b = $this->prospect('Beta', 'b@example.com', ['language' => 'nl']);
        $this->prospect('Gamma', 'c@example.com', ['language' => 'fr']);
        $this->prospect('Unsub', 'u@example.com', ['language' => 'nl', 'unsubscribed_at' => now()]);
        $sup = $this->prospect('Supp', 's@example.com', ['language' => 'nl']);
        SuppressionEntry::create(['email' => 's@example.com', 'prospect_id' => $sup->id, 'reason' => 'manual', 'suppressed_at' => now()]);

        $state = match ($type) {
            AudienceType::ALL_SUBSCRIBED => [],
            AudienceType::SEGMENT => [
                'audience_filters.operator' => 'AND',
                'audience_filters.rules' => [['type' => 'prospect_field', 'field' => 'language', 'operator' => '=', 'value' => 'nl']],
                // stale manual selection must not narrow a segment
                'audience_filters.prospect_ids' => [$a->id],
            ],
            AudienceType::MANUAL => ['audience_filters.prospect_ids' => [$a->id, $b->id, $sup->id]],
        };
        $state['audience_type'] = $type->value;

        $campaign = Campaign::factory()->create([
            'audience_type' => $type,
            'audience_filters' => match ($type) {
                AudienceType::ALL_SUBSCRIBED => null,
                AudienceType::SEGMENT => ['operator' => 'AND', 'rules' => $state['audience_filters.rules'], 'prospect_ids' => [$a->id]],
                AudienceType::MANUAL => ['prospect_ids' => $state['audience_filters.prospect_ids']],
            },
        ]);

        $result = app(SegmentResolverService::class)->preview(CampaignForm::previewFilters($this->getter($state)));

        $this->assertSame(count($campaign->resolveAudience()), $result['total']);
        $this->assertEqualsCanonicalizing($campaign->resolveAudience(), $result['prospects']->pluck('id')->all());
    }

    // ---- S5: action availability -------------------------------------------

    #[DataProvider('audienceTypes')]
    public function test_preview_action_is_visible_for_every_audience_type(AudienceType $type): void
    {
        $this->actAsAdmin();

        $component = Livewire::test(CreateCampaign::class)
            ->fillForm(['audience_type' => $type->value])
            ->assertSee(__('mailing::resource.actions.preview_audience'));

        $this->assertNotNull($component);
    }
}
