<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use App\Filament\Clusters\Website\Resources\ConsultationRequestResource;
use App\Filament\Clusters\Website\Resources\ConsultationRequestResource\Pages\EditConsultationRequest;
use App\Filament\Clusters\Website\Resources\ConsultationRequestResource\Pages\ListConsultationRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Website\Models\ConsultationActivity;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Services\RetentionService;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F4/CLA-476 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Retención, exportación y derechos RGPD" — retention/anonymization is
 * gated behind config('website.retention.enabled') (off by default in
 * every environment, matching D4's "inert until explicitly turned on"
 * pattern this whole program already uses) precisely because of this
 * ticket's own "validación jurídica belga marcada como requisito de
 * lanzamiento" criterion: the mechanism exists and is correct, nothing
 * destructive runs against real data until that legal review clears it.
 */
final class RetentionPolicyTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'website.retention.enabled' => true,
            'website.retention.spam_days' => 30,
            'website.retention.closed_anonymize_days' => 730,
        ]);
    }

    public function test_retention_days_fall_back_to_global_config_when_the_organization_has_no_override(): void
    {
        $site = Site::factory()->create();

        $service = app(RetentionService::class);
        $this->assertSame(30, $service->spamRetentionDays($site));
        $this->assertSame(730, $service->closedAnonymizeDays($site));
    }

    public function test_an_organizations_own_retention_policy_overrides_the_global_config(): void
    {
        $organization = Organization::factory()->create([
            'retention_policy' => ['spam_days' => 7, 'closed_anonymize_days' => 365],
        ]);
        $site = Site::factory()->create(['organization_id' => $organization->id]);

        $service = app(RetentionService::class);
        $this->assertSame(7, $service->spamRetentionDays($site));
        $this->assertSame(365, $service->closedAnonymizeDays($site));
    }

    public function test_dry_run_reports_counts_without_deleting_or_anonymizing_anything(): void
    {
        $site = Site::factory()->create();
        $spam = ConsultationRequest::factory()->create([
            'site_id' => $site->id, 'status' => ConsultationRequest::STATUS_SPAM,
            'created_at' => now()->subDays(31),
        ]);
        $closed = ConsultationRequest::factory()->create([
            'site_id' => $site->id, 'status' => ConsultationRequest::STATUS_CLOSED,
            'updated_at' => now()->subDays(731),
        ]);

        $result = app(RetentionService::class)->applyToSite($site, dryRun: true);

        $this->assertSame(1, $result['deleted']);
        $this->assertSame(1, $result['anonymized']);
        $this->assertDatabaseHas('website_consultation_requests', ['id' => $spam->id]);
        $this->assertNull($closed->fresh()->anonymized_at);
    }

    public function test_expired_spam_is_hard_deleted_and_cascades_to_its_activity_log_rows(): void
    {
        $site = Site::factory()->create();
        $spam = ConsultationRequest::factory()->create([
            'site_id' => $site->id, 'status' => ConsultationRequest::STATUS_SPAM,
            'created_at' => now()->subDays(31),
        ]);
        ConsultationActivity::create([
            'consultation_request_id' => $spam->id, 'type' => 'created', 'title' => 'x', 'activity_at' => now(),
        ]);

        app(RetentionService::class)->applyToSite($site, dryRun: false);

        $this->assertDatabaseMissing('website_consultation_requests', ['id' => $spam->id]);
        $this->assertDatabaseMissing('website_consultation_activities', ['consultation_request_id' => $spam->id]);
    }

    public function test_expired_closed_leads_are_anonymized_not_deleted(): void
    {
        $site = Site::factory()->create();
        $closed = ConsultationRequest::factory()->create([
            'site_id' => $site->id, 'status' => ConsultationRequest::STATUS_CLOSED,
            'name' => 'Real Person', 'email' => 'real@example.test',
            'updated_at' => now()->subDays(731),
        ]);

        app(RetentionService::class)->applyToSite($site, dryRun: false);

        $fresh = $closed->fresh();
        $this->assertDatabaseHas('website_consultation_requests', ['id' => $closed->id]);
        $this->assertNotNull($fresh->anonymized_at);
        $this->assertNotSame('Real Person', $fresh->name);
        $this->assertNotSame('real@example.test', $fresh->email);
        // Aggregate/reporting fields survive anonymization.
        $this->assertSame(ConsultationRequest::STATUS_CLOSED, $fresh->status);
    }

    public function test_leads_not_yet_past_their_cutoff_are_left_untouched(): void
    {
        $site = Site::factory()->create();
        $recentSpam = ConsultationRequest::factory()->create([
            'site_id' => $site->id, 'status' => ConsultationRequest::STATUS_SPAM, 'created_at' => now()->subDays(5),
        ]);
        $recentClosed = ConsultationRequest::factory()->create([
            'site_id' => $site->id, 'status' => ConsultationRequest::STATUS_CLOSED, 'updated_at' => now()->subDays(5),
        ]);

        $result = app(RetentionService::class)->applyToSite($site, dryRun: false);

        $this->assertSame(0, $result['deleted']);
        $this->assertSame(0, $result['anonymized']);
        $this->assertDatabaseHas('website_consultation_requests', ['id' => $recentSpam->id]);
        $this->assertNull($recentClosed->fresh()->anonymized_at);
    }

    public function test_an_already_anonymized_closed_lead_is_never_reprocessed(): void
    {
        $site = Site::factory()->create();
        $consultation = ConsultationRequest::factory()->create([
            'site_id' => $site->id, 'status' => ConsultationRequest::STATUS_CLOSED,
            'updated_at' => now()->subDays(731),
        ]);
        $consultation->anonymize();
        $anonymizedAt = $consultation->fresh()->anonymized_at;

        app(RetentionService::class)->applyToSite($site, dryRun: false);

        $this->assertTrue($consultation->fresh()->anonymized_at->equalTo($anonymizedAt));
    }

    public function test_anonymize_is_idempotent(): void
    {
        $consultation = ConsultationRequest::factory()->create(['name' => 'Real Person']);
        $consultation->anonymize();
        $firstName = $consultation->fresh()->name;

        $consultation->anonymize();

        $this->assertSame($firstName, $consultation->fresh()->name);
    }

    public function test_command_behaves_as_dry_run_when_retention_is_disabled_even_without_the_flag(): void
    {
        config(['website.retention.enabled' => false]);
        $site = Site::factory()->create();
        $spam = ConsultationRequest::factory()->create([
            'site_id' => $site->id, 'status' => ConsultationRequest::STATUS_SPAM, 'created_at' => now()->subDays(31),
        ]);

        $this->artisan('website:apply-retention-policy')->assertSuccessful();

        $this->assertDatabaseHas('website_consultation_requests', ['id' => $spam->id]);
    }

    public function test_command_applies_the_policy_for_real_when_enabled(): void
    {
        $site = Site::factory()->create();
        $spam = ConsultationRequest::factory()->create([
            'site_id' => $site->id, 'status' => ConsultationRequest::STATUS_SPAM, 'created_at' => now()->subDays(31),
        ]);

        $this->artisan('website:apply-retention-policy')->assertSuccessful();

        $this->assertDatabaseMissing('website_consultation_requests', ['id' => $spam->id]);
    }

    public function test_erase_now_anonymizes_regardless_of_age_and_logs_a_dedicated_audit_entry(): void
    {
        $consultation = ConsultationRequest::factory()->create([
            'name' => 'Real Person', 'created_at' => now(), // brand new, far from any cutoff
        ]);
        $admin = $this->claesenUser();

        app(RetentionService::class)->eraseNow($consultation, $admin->id);

        $fresh = $consultation->fresh();
        $this->assertNotNull($fresh->anonymized_at);
        $this->assertNotSame('Real Person', $fresh->name);
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $consultation->id,
            'subject_type' => ConsultationRequest::class,
            'causer_id' => $admin->id,
            'description' => 'Erased on demand (GDPR right-to-erasure request)',
        ]);
    }

    public function test_organization_isolation_a_bertels_fixture_sites_expired_spam_is_never_touched_by_claesens_run(): void
    {
        $this->enableOrganizationEnforcement();
        $bertelsSite = $this->bertelsFixtureSite();
        $claesenSite = Site::query()->where('key', Site::CLAESEN_KEY)->first();

        $bertelsSpam = ConsultationRequest::factory()->create([
            'site_id' => $bertelsSite->id, 'status' => ConsultationRequest::STATUS_SPAM, 'created_at' => now()->subDays(31),
        ]);

        app(RetentionService::class)->applyToSite($claesenSite, dryRun: false);

        // Applying the policy to Claesen's site must never touch a row that
        // belongs to a different site.
        $this->assertDatabaseHas('website_consultation_requests', ['id' => $bertelsSpam->id]);
    }

    public function test_the_export_action_is_only_visible_with_the_export_permission(): void
    {
        $user = $this->claesenUser();
        $this->actingAs($user);

        Livewire::test(ListConsultationRequests::class)
            ->assertTableActionHidden('export');

        $user->givePermissionTo(Permission::findOrCreate('website.export-consultation-requests', 'web'));

        Livewire::test(ListConsultationRequests::class)
            ->assertTableActionVisible('export');
    }

    public function test_the_export_action_streams_a_csv_and_logs_an_audited_activity_entry(): void
    {
        $user = $this->claesenUser();
        $user->givePermissionTo(Permission::findOrCreate('website.export-consultation-requests', 'web'));
        $this->actingAs($user);

        ConsultationRequest::factory()->create(['name' => 'Jan Claesen', 'email' => 'jan@example.test']);

        Livewire::test(ListConsultationRequests::class)
            ->callTableAction('export');

        // ConsultationRequest also has Spatie's own automatic created/updated
        // logging (LogsActivity::logAll()) — the factory create() above
        // already wrote one such entry, so the export's own log line is
        // matched by its description, not just "any row exists".
        $entry = Activity::query()
            ->where('description', 'like', 'Exported%')
            ->latest()
            ->first();

        $this->assertNotNull($entry, 'The export action must log its own dedicated audit entry.');
        $this->assertSame($user->id, $entry->causer_id);
    }

    public function test_the_erase_action_is_hidden_for_a_user_without_the_permission_and_visible_for_one_with_it(): void
    {
        $user = $this->claesenUser();
        $this->actingAs($user);
        $consultation = ConsultationRequest::factory()->create();

        Livewire::test(ListConsultationRequests::class)
            ->assertTableActionHidden('erase', $consultation);

        $user->givePermissionTo(Permission::findOrCreate('website.erase-consultation-pii', 'web'));

        Livewire::test(ListConsultationRequests::class)
            ->assertTableActionVisible('erase', $consultation);
    }

    public function test_the_erase_action_is_hidden_once_a_lead_is_already_anonymized(): void
    {
        $user = $this->claesenUser();
        $user->givePermissionTo(Permission::findOrCreate('website.erase-consultation-pii', 'web'));
        $this->actingAs($user);
        $consultation = ConsultationRequest::factory()->create();
        $consultation->anonymize();

        Livewire::test(ListConsultationRequests::class)
            ->assertTableActionHidden('erase', $consultation->fresh());
    }

    public function test_calling_the_erase_action_anonymizes_the_record(): void
    {
        $user = $this->claesenUser();
        $user->givePermissionTo(Permission::findOrCreate('website.erase-consultation-pii', 'web'));
        $this->actingAs($user);
        $consultation = ConsultationRequest::factory()->create(['name' => 'Real Person']);

        Livewire::test(ListConsultationRequests::class)
            ->callTableAction('erase', $consultation);

        $this->assertNotSame('Real Person', $consultation->fresh()->name);
    }

    public function test_contact_fields_are_editable_via_the_filament_form_for_gdpr_rectification(): void
    {
        $user = $this->claesenUser();
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($user);
        $consultation = ConsultationRequest::factory()->create(['email' => 'typo@example.test']);

        Livewire::test(EditConsultationRequest::class, ['record' => $consultation->getRouteKey()])
            ->fillForm(['email' => 'corrected@example.test'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('corrected@example.test', $consultation->fresh()->email);
    }

    public function test_sanitize_csv_value_neutralizes_formula_injection_prefixes(): void
    {
        $method = new \ReflectionMethod(ConsultationRequestResource::class, 'sanitizeCsvValue');
        $method->setAccessible(true);

        foreach (['=cmd|/c calc', '+1', '-1', '@SUM(1,1)'] as $malicious) {
            $this->assertStringStartsWith("'", $method->invoke(null, $malicious));
        }

        $this->assertSame('Jan Claesen', $method->invoke(null, 'Jan Claesen'));
        $this->assertSame('', $method->invoke(null, null));
    }
}
