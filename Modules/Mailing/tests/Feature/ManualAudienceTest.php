<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Filament\Resources\CampaignResource;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\ViewCampaign;
use Modules\Mailing\Filament\Resources\CampaignResource\Schemas\CampaignForm;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Models\SuppressionEntry;
use Modules\Mailing\Services\SegmentResolverService;
use Modules\Mailing\Services\SuppressionService;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-671 — manual audience: prospect picker on the campaign form, resolution of
 * the stored selection (audience_filters.prospect_ids) and the view page.
 */
class ManualAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mailing.send_delay_ms' => 0, 'app.mailing_driver' => 'simulation']);
        Http::preventStrayRequests();
        Http::fake();

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

    private function manualCampaign(?array $filters, array $attrs = []): Campaign
    {
        return Campaign::factory()->create(array_merge([
            'audience_type' => AudienceType::MANUAL,
            'audience_filters' => $filters,
        ], $attrs));
    }

    private function formData(array $extra): array
    {
        $template = EmailTemplate::factory()->create();

        return array_merge([
            'template_id' => $template->id,
            'template_name' => $template->name,
            'description' => 'Manual audience campaign',
            'subject_snapshot' => 'Subject',
            'body_snapshot' => 'Body',
        ], $extra);
    }

    // ---- S1 / S2: form -----------------------------------------------------

    public function test_create_stores_manual_selection_as_integer_ids(): void
    {
        $this->actAsAdmin();
        $a = $this->prospect('Club A', 'a@example.com');
        $b = $this->prospect('Club B', 'b@example.com');

        Livewire::test(CreateCampaign::class)
            ->fillForm($this->formData([
                'audience_type' => AudienceType::MANUAL->value,
                'audience_filters' => ['prospect_ids' => [(string) $a->id, (string) $b->id]],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $campaign = Campaign::firstOrFail();
        $this->assertSame(AudienceType::MANUAL, $campaign->audience_type);
        $this->assertSame(['prospect_ids' => [$a->id, $b->id]], $campaign->audience_filters);
    }

    public function test_manual_requires_at_least_one_prospect(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateCampaign::class)
            ->fillForm($this->formData([
                'audience_type' => AudienceType::MANUAL->value,
                'audience_filters' => ['prospect_ids' => []],
            ]))
            ->call('create')
            ->assertHasFormErrors(['audience_filters.prospect_ids' => 'required']);

        $this->assertDatabaseCount('mailing_campaigns', 0);
    }

    // ---- CLA-674: malformed ids are a validation error, never coerced --------

    public static function malformedSelections(): array
    {
        return [
            'digits with suffix' => [fn (int $id) => [$id.'oops']],
            'boolean true'       => [fn (int $id) => [true]],
            'nested array'       => [fn (int $id) => [[$id]]],
            'zero'               => [fn (int $id) => ['0']],
            'negative'           => [fn (int $id) => [(string) -$id]],
            'unknown prospect'   => [fn (int $id) => [(string) ($id + 1000)]],
            'valid plus garbage' => [fn (int $id) => [(string) $id, 'x']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedSelections')]
    public function test_malformed_manual_selection_is_rejected(\Closure $selection): void
    {
        $this->actAsAdmin();
        $p = $this->prospect('Club A', 'a@example.com');

        Livewire::test(CreateCampaign::class)
            ->fillForm($this->formData([
                'audience_type' => AudienceType::MANUAL->value,
                'audience_filters' => ['prospect_ids' => $selection($p->id)],
            ]))
            ->call('create')
            ->assertHasFormErrors(['audience_filters.prospect_ids']);

        $this->assertDatabaseCount('mailing_campaigns', 0);
    }

    public function test_selector_is_hidden_unless_audience_is_manual(): void
    {
        $this->actAsAdmin();

        Livewire::test(CreateCampaign::class)
            ->assertFormFieldIsHidden('audience_filters.prospect_ids')
            ->fillForm(['audience_type' => AudienceType::SEGMENT->value])
            ->assertFormFieldIsHidden('audience_filters.prospect_ids')
            ->fillForm(['audience_type' => AudienceType::MANUAL->value])
            ->assertFormFieldIsVisible('audience_filters.prospect_ids');
    }

    public function test_search_finds_by_name_and_by_location_email(): void
    {
        $byName = $this->prospect('Zebra United', 'zz@example.com');
        $byMail = $this->prospect('Other FC', 'findme@example.com');
        $this->prospect('Unrelated', 'nothing@example.com');

        $this->assertSame([$byName->id], array_keys(CampaignForm::searchProspects('Zebra')));
        $this->assertSame([$byMail->id], array_keys(CampaignForm::searchProspects('findme@')));
    }

    public function test_search_label_has_name_sport_federation_region(): void
    {
        $p = $this->prospect('Zebra United', 'zz@example.com');

        $this->assertSame(
            [$p->id => 'Zebra United · football_club · KBVB · Flanders'],
            CampaignForm::searchProspects('Zebra'),
        );
    }

    public function test_search_excludes_unsubscribed_and_prospects_without_email(): void
    {
        $this->prospect('Match Ok', 'ok@example.com');
        $this->prospect('Match Unsub', 'unsub@example.com', ['unsubscribed_at' => now()]);
        $this->prospect('Match NoLocation');
        $noMail = $this->prospect('Match EmptyMail');
        ProspectLocation::create(['prospect_id' => $noMail->id, 'contact_type' => 'main', 'email' => '']);

        $this->assertSame(['Match Ok'], array_map(
            fn (string $l) => explode(' · ', $l)[0],
            array_values(CampaignForm::searchProspects('Match')),
        ));
    }

    public function test_search_returns_at_most_fifty(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            $this->prospect('Bulk '.$i, "bulk{$i}@example.com");
        }

        $this->assertCount(50, CampaignForm::searchProspects('Bulk'));
    }

    // ---- S2 / S3: resolution -------------------------------------------------

    public function test_resolve_returns_only_selected_eligible_ids(): void
    {
        $ok = $this->prospect('Ok', 'ok@example.com');
        $unsub = $this->prospect('Unsub', 'u@example.com', ['unsubscribed_at' => now()]);
        $suppressed = $this->prospect('Suppressed', 's@example.com');
        SuppressionEntry::factory()->create(['prospect_id' => $suppressed->id]);
        $this->prospect('NotSelected', 'n@example.com');

        $campaign = $this->manualCampaign(['prospect_ids' => [$ok->id, $unsub->id, $suppressed->id]]);

        $this->assertSame([$ok->id], $campaign->resolveAudience());
    }

    public function test_resolve_ignores_stray_rules_in_manual_filters(): void
    {
        $sel = $this->prospect('Sel', 'sel@example.com');
        $this->prospect('Other', 'o@example.com');

        $campaign = $this->manualCampaign([
            'prospect_ids' => [$sel->id],
            'operator' => 'OR',
            'rules' => [['type' => 'prospect_field', 'field' => 'federation', 'operator' => '=', 'value' => 'KBVB']],
        ]);

        $this->assertSame([$sel->id], $campaign->resolveAudience());
    }

    public function test_resolve_manual_without_selection_is_empty_and_does_not_throw(): void
    {
        $this->prospect('Someone', 's@example.com');

        $this->assertSame([], $this->manualCampaign([])->resolveAudience());
        $this->assertSame([], $this->manualCampaign(['prospect_ids' => []])->resolveAudience());
        $this->assertSame([], $this->manualCampaign(null)->resolveAudience());
    }

    public function test_job_sends_only_to_selected_eligible_prospects(): void
    {
        $ok = $this->prospect('Ok', 'ok@example.com');
        $unsub = $this->prospect('Unsub', 'u@example.com', ['unsubscribed_at' => now()]);
        $this->prospect('NotSelected', 'n@example.com');

        $campaign = $this->manualCampaign(
            ['prospect_ids' => [$ok->id, $unsub->id]],
            ['status' => CampaignStatus::APPROVED, 'subject_snapshot' => 'Hi', 'body_snapshot' => '<p>Hi</p>'],
        );

        (new ExecuteCampaignJob(campaignId: $campaign->id))
            ->handle(app(MarketingCampaignInterface::class), app(SuppressionService::class));

        $this->assertSame(
            [$ok->id],
            \DB::table('mailing_messages')->where('campaign_id', $campaign->id)->pluck('prospect_id')->map(fn ($i) => (int) $i)->all(),
        );
        $this->assertDatabaseHas('mailing_campaigns', ['id' => $campaign->id, 'status' => CampaignStatus::COMPLETED->value]);
    }

    // ---- S4: preview count (resolver) ---------------------------------------

    public function test_resolver_count_for_prospect_ids_reports_eligible_recipients(): void
    {
        $ok = $this->prospect('Ok', 'ok@example.com');
        $unsub = $this->prospect('Unsub', 'u@example.com', ['unsubscribed_at' => now()]);

        $this->assertSame(1, app(SegmentResolverService::class)->count(['prospect_ids' => [$ok->id, $unsub->id]]));
        $this->assertSame(0, app(SegmentResolverService::class)->count(['prospect_ids' => []]));
    }

    // ---- S5: view page -------------------------------------------------------

    public function test_view_page_shows_audience_type_and_recipient_count(): void
    {
        $this->actAsAdmin();
        $a = $this->prospect('A', 'a@example.com');
        $b = $this->prospect('B', 'b@example.com');
        $c = $this->prospect('C', 'c@example.com', ['unsubscribed_at' => now()]);
        $campaign = $this->manualCampaign(['prospect_ids' => [$a->id, $b->id, $c->id]]);

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->assertSee(AudienceType::MANUAL->label())
            ->assertSee(__('mailing::resource.fields.audience_recipients'))
            ->assertSeeText('2');
    }

    // ---- S6: existing types unchanged ----------------------------------------

    public function test_malformed_manual_ids_never_select_a_prospect(): void
    {
        $p = $this->prospect('Selected nowhere', 'safe@example.com');
        $campaign = $this->manualCampaign([
            'prospect_ids' => [$p->id.'oops', true, [$p->id], -$p->id, 0, null],
        ]);

        $this->assertSame([], $campaign->resolveAudience());
    }

    public function test_digit_string_ids_still_resolve(): void
    {
        $p = $this->prospect('Stored as string', 'string@example.com');
        $campaign = $this->manualCampaign(['prospect_ids' => [(string) $p->id]]);

        $this->assertSame([$p->id], $campaign->resolveAudience());
    }

    public function test_segment_ignores_manual_selection_metadata(): void
    {
        $p = $this->prospect('Eligible', 'eligible@example.com');
        $campaign = Campaign::factory()->create([
            'audience_type'    => AudienceType::SEGMENT,
            'audience_filters' => ['rules' => [], 'prospect_ids' => []],
        ]);

        $this->assertContains($p->id, $campaign->resolveAudience());
    }

    public function test_view_survives_malformed_segment_filters(): void
    {
        $this->actAsAdmin();
        $campaign = Campaign::factory()->create([
            'audience_type'    => AudienceType::SEGMENT,
            'audience_filters' => ['rules' => [[
                'type'  => 'prospect_field',
                'field' => 'typo',
                'value' => 'x',
            ]]],
        ]);

        Livewire::test(ViewCampaign::class, ['record' => $campaign->id])
            ->assertSuccessful()
            ->assertSee(__('mailing::resource.fields.audience_recipients'));
    }

    public function test_segment_rules_without_prospect_ids_are_unchanged(): void
    {
        $kbvb = $this->prospect('K', 'k@example.com');
        $this->prospect('V', 'v@example.com', ['federation' => 'VV']);

        $ids = app(SegmentResolverService::class)->resolveIds([
            'operator' => 'AND',
            'rules' => [['type' => 'prospect_field', 'field' => 'federation', 'operator' => '=', 'value' => 'KBVB']],
        ]);

        $this->assertSame([$kbvb->id], $ids);
    }

    public function test_all_subscribed_audience_is_unchanged(): void
    {
        $a = $this->prospect('A', 'a@example.com');
        $this->prospect('U', 'u@example.com', ['unsubscribed_at' => now()]);

        $campaign = Campaign::factory()->create(['audience_type' => AudienceType::ALL_SUBSCRIBED]);

        $this->assertSame([$a->id], $campaign->resolveAudience());
    }
}
