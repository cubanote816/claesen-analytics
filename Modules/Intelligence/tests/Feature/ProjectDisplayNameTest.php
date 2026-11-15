<?php

namespace Modules\Intelligence\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Intelligence\Database\Seeders\BiConfigSeeder;
use Modules\Intelligence\Filament\Pages\MonthlyBillingControlPage;
use Modules\Intelligence\Models\BillingAlert;
use Modules\Intelligence\Services\MonthlyBillingGuardianService;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorProjectLink;
use Modules\Performance\Models\Mirror\MirrorRelation;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-688 — in CAFCA `project.name` holds the CLIENT name; the real project
 * name is `project.descr` (CLA-173). Billing Control must show the project id,
 * the project name and the client, never `project.name` as the project name.
 */
class ProjectDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT_NAME = 'Limburg - Veldverlichting Diepenbeek';

    private const CLIENT_NAME = 'TC Tenkie';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 7, 4, 12, 0, 0, 'Europe/Brussels'));
        $this->seed(BiConfigSeeder::class);
        MirrorRelation::create(['id' => 100, 'name' => self::CLIENT_NAME]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeProject(string $id, array $overrides = []): MirrorProject
    {
        return MirrorProject::create(array_merge([
            'id' => $id,
            'name' => self::CLIENT_NAME, // CAFCA stores the client here
            'descr' => self::PROJECT_NAME,
            'relation_id' => 100,
            'contract_price' => 50000,
            'fl_active' => true,
            'state' => 1,
        ], $overrides));
    }

    private function detect(string $rule, int $month = 6): array
    {
        $guardian = app(MonthlyBillingGuardianService::class);
        $ref = new \ReflectionMethod($guardian, $rule);

        return $ref->invoke($guardian, 2026, $month);
    }

    private function assertProjectIdentity(array $alert, string $projectId): void
    {
        $this->assertSame(self::PROJECT_NAME, $alert['evidence_json']['project_name']);
        $this->assertSame(self::CLIENT_NAME, $alert['evidence_json']['client_name']);
        $this->assertStringContainsString(
            $projectId.' · '.self::PROJECT_NAME.' ('.self::CLIENT_NAME.')',
            $alert['recommendation']
        );
    }

    // -------------------------------------------------------------------------
    // MirrorProject::display_name
    // -------------------------------------------------------------------------

    public function test_display_name_is_descr_not_name(): void
    {
        $project = $this->makeProject('P20260050');

        $this->assertSame(self::PROJECT_NAME, $project->display_name);
    }

    public function test_display_name_falls_back_to_name_when_descr_is_blank(): void
    {
        $this->assertSame('Old project', $this->makeProject('P1', ['name' => 'Old project', 'descr' => null])->display_name);
        $this->assertSame('Old project', $this->makeProject('P2', ['name' => 'Old project', 'descr' => '   '])->display_name);
    }

    public function test_display_name_falls_back_to_id_when_descr_and_name_are_blank(): void
    {
        $this->assertSame('P3', $this->makeProject('P3', ['name' => '', 'descr' => null])->display_name);
    }

    // -------------------------------------------------------------------------
    // Alert texts and evidence — every alert type that carries a project
    // -------------------------------------------------------------------------

    public function test_missing_invoice_alert_names_project_and_client(): void
    {
        $this->makeProject('P20260050');
        MirrorProjectLink::create(['project_id' => 'P20260050', 'estimate_id' => 'E1', 'link_type' => 1]);
        MirrorCost::create(['id' => 'C1', 'project_id' => 'P20260050', 'cost_price' => 2500, 'quantity' => 1, 'date' => '2026-06-15']);

        $alerts = $this->detect('detectMissingCustomerInvoices');

        $this->assertCount(1, $alerts);
        $this->assertProjectIdentity($alerts[0], 'P20260050');
    }

    public function test_overdue_alert_names_project_and_client(): void
    {
        $this->makeProject('P20260050');
        MirrorInvoice::create([
            'id' => 'F1', 'project_id' => 'P20260050', 'relation_id' => 100,
            'total_price' => 10000, 'total_paid' => 0, 'date' => '2026-04-01',
            'date_expiration' => '2026-05-01', 'fl_paid' => false,
        ]);

        $alerts = $this->detect('detectOverdueReceivables');

        $this->assertCount(1, $alerts);
        $this->assertProjectIdentity($alerts[0], 'P20260050');
    }

    public function test_overdue_alert_for_project_missing_from_mirror_shows_id_and_client(): void
    {
        MirrorInvoice::create([
            'id' => 'F2', 'project_id' => 'P20269999', 'relation_id' => 100,
            'total_price' => 10000, 'total_paid' => 0, 'date' => '2026-04-01',
            'date_expiration' => '2026-05-01', 'fl_paid' => false,
        ]);

        $alerts = $this->detect('detectOverdueReceivables');

        $this->assertCount(1, $alerts);
        $this->assertNull($alerts[0]['evidence_json']['project_name']);
        $this->assertSame(self::CLIENT_NAME, $alerts[0]['evidence_json']['client_name']);
        $this->assertStringStartsWith('Factuur F2 voor project P20269999 ('.self::CLIENT_NAME.'):', $alerts[0]['recommendation']);
    }

    public function test_unbilled_followup_cost_alert_names_project_and_client(): void
    {
        $this->makeProject('P20260050');
        MirrorCost::create(['id' => 'C2', 'project_id' => 'P20260050', 'cost_price' => 900, 'quantity' => 1, 'date' => '2026-06-10', 'invoiced' => false]);

        $alerts = $this->detect('detectUnbilledFollowupCosts');

        $this->assertCount(1, $alerts);
        $this->assertProjectIdentity($alerts[0], 'P20260050');
    }

    public function test_billing_gap_alert_names_project_and_client(): void
    {
        $this->makeProject('P20260050');
        MirrorCost::create(['id' => 'C3', 'project_id' => 'P20260050', 'cost_price' => 100, 'quantity' => 1, 'date' => '2026-06-05']);

        $alerts = $this->detect('detectProjectBillingGaps');

        $this->assertCount(1, $alerts);
        $this->assertProjectIdentity($alerts[0], 'P20260050');
    }

    public function test_credit_note_alert_names_project_and_client(): void
    {
        $this->makeProject('P20260050');
        MirrorInvoice::create([
            'id' => 'CN1', 'project_id' => 'P20260050', 'relation_id' => 100,
            'total_price' => -500, 'total_paid' => 0, 'date' => '2026-06-10', 'fl_paid' => false,
        ]);

        $alerts = $this->detect('detectCreditNotes');

        $this->assertCount(1, $alerts);
        $this->assertProjectIdentity($alerts[0], 'P20260050');
    }

    public function test_closed_with_balance_alert_names_project_and_client(): void
    {
        $this->makeProject('P20260050', ['fl_active' => false]);
        MirrorInvoice::create([
            'id' => 'F3', 'project_id' => 'P20260050', 'relation_id' => 100,
            'total_price' => 5000, 'total_paid' => 0, 'date' => '2026-05-15',
            'date_expiration' => '2026-08-15', 'fl_paid' => false,
        ]);

        $alerts = $this->detect('detectClosedProjectsWithBalance');

        $this->assertCount(1, $alerts);
        $this->assertProjectIdentity($alerts[0], 'P20260050');
    }

    // -------------------------------------------------------------------------
    // Billing Control page — rendered live from the mirror, so alerts persisted
    // before CLA-688 (old recommendation text, no project_name) also show it.
    // -------------------------------------------------------------------------

    private function persistedOldAlert(): BillingAlert
    {
        $this->makeProject('P20260050');

        return BillingAlert::create([
            'dedup_key' => BillingAlert::buildDedupKey(2026, 6, 'missing_customer_invoice', 'P20260050'),
            'period_year' => 2026,
            'period_month' => 6,
            'alert_type' => 'missing_customer_invoice',
            'severity' => 'high',
            'status' => BillingAlert::STATUS_OPEN,
            'project_id' => 'P20260050',
            'relation_id' => 100,
            'evidence_json' => ['costs_in_month' => 1000.00],
            'recommendation' => 'Project P20260050 (TC Tenkie) heeft kosten.',
        ]);
    }

    private function actingAsAdmin(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    public function test_billing_table_shows_project_id_name_and_client(): void
    {
        $this->persistedOldAlert();
        $this->actingAsAdmin();

        Livewire::test(MonthlyBillingControlPage::class)
            ->call('setPeriod', '2026-06')
            ->assertSeeInOrder(['P20260050', self::PROJECT_NAME, self::CLIENT_NAME]);
    }

    /**
     * The page picks its labels from app()->getLocale(), so pin the locale:
     * the environment default differs between local Sail (nl) and CI (en).
     */
    public static function modalLocales(): array
    {
        return [
            'nl' => ['nl', 'Projectnaam', 'Klant'],
            'en' => ['en', 'Project name', 'Client'],
        ];
    }

    #[DataProvider('modalLocales')]
    public function test_billing_modal_shows_descr_as_project_name_and_relation_as_client(
        string $locale,
        string $projectLabel,
        string $clientLabel,
    ): void {
        $alert = $this->persistedOldAlert();
        $this->actingAsAdmin();
        app()->setLocale($locale);

        Livewire::test(MonthlyBillingControlPage::class)
            ->call('setPeriod', '2026-06')
            ->call('openModal', $alert->id)
            ->assertSeeInOrder([$projectLabel, self::PROJECT_NAME, $clientLabel, self::CLIENT_NAME]);
    }
}
