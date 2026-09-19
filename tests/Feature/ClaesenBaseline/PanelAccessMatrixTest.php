<?php

declare(strict_types=1);

namespace Tests\Feature\ClaesenBaseline;

use App\Filament\Clusters\Website\Resources\ConsultationRequestResource;
use App\Filament\Clusters\Website\WebsiteCluster;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Filament\Pages\AccessAnalyticsPage;
use Modules\Core\Filament\Resources\Permissions\PermissionResource;
use Modules\Core\Filament\Resources\Roles\RoleResource;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Models\User;
use Modules\Employee\Filament\Pages\EmployeeHoursDashboard;
use Modules\Employee\Filament\Pages\ProjectsWorkedHoursPage;
use Modules\Employee\Filament\Resources\EmployeeResource;
use Modules\FieldOps\Filament\Resources\Catalogs\AccessTypeResource;
use Modules\FieldOps\Filament\Resources\Catalogs\ElectricalBoardTypeResource;
use Modules\FieldOps\Filament\Resources\Catalogs\FoMaintenanceTypeResource;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypeResource;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireSubgroupResource;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireTypeResource;
use Modules\FieldOps\Filament\Resources\Catalogs\SafetyTypeResource;
use Modules\FieldOps\Filament\Resources\Catalogs\StructureTypeResource;
use Modules\FieldOps\Filament\Resources\Catalogs\TerrainTypeResource;
use Modules\FieldOps\Filament\Resources\ComplexResource;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\FoClientResource;
use Modules\FieldOps\Filament\Resources\FoMaintenancePlanResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRequestResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Filament\Resources\TerrainResource;
use Modules\Intelligence\Filament\Pages\BiConfigPage;
use Modules\Intelligence\Filament\Pages\MirrorSyncStatusPage;
use Modules\Intelligence\Filament\Pages\MonthlyBillingControlPage;
use Modules\Intelligence\Filament\Pages\OfferSimulator;
use Modules\Intelligence\Filament\Pages\ProjectIntelligenceDetail;
use Modules\Mailing\Filament\Resources\CampaignResource;
use Modules\Mailing\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Modules\Performance\Filament\Pages\EmployeeInsight;
use Modules\Performance\Filament\Resources\ProjectInsightResource;
use Modules\Performance\Filament\Resources\ProjectResource;
use Modules\Prospects\Filament\Pages\SyncDashboardPage;
use Modules\Prospects\Filament\Resources\Prospects\ProspectResource;
use Modules\Prospects\Filament\Resources\SyncHistoryResource;
use Modules\Safety\Filament\Resources\ChecklistResource;
use Modules\Safety\Filament\Resources\InspectionResource;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Behavioural half of the access baseline: who can reach the backoffice and
 * which resources and pages each role can open, executed against the real
 * canAccess()/hasPanelAccess() implementations.
 *
 * This is the matrix the multi-organization phases must reproduce exactly for
 * Claesen users while adding the organization boundary on top. Every expectation
 * below is the behaviour observed today, not a wish: PanelAccessMatrixTest is a
 * characterization test, so a failure means "a Claesen permission changed",
 * which in this program is a regression unless the current phase declared it.
 */
final class PanelAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** Roles allowed into the Filament panel today (User::hasPanelAccess()). */
    private const PANEL_ROLES = ['super_admin', 'admin', 'financial_manager', 'hr_manager', 'viewer'];

    /** Roles that authenticate but use the dedicated apps instead of the panel. */
    private const NON_PANEL_ROLES = ['project_manager', 'technician', 'client'];

    protected function setUp(): void
    {
        parent::setUp();

        // This baseline is about authorization, not the asset pipeline: without
        // this the dashboard assertion fails on any checkout that has not run
        // `npm run build` (the same ViteManifestNotFoundException that hit CI in
        // CLA-525), which would say nothing about Claesen's permissions.
        $this->withoutVite();

        foreach ([...self::PANEL_ROLES, ...self::NON_PANEL_ROLES] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_only_the_panel_roles_have_backoffice_access(): void
    {
        foreach (self::PANEL_ROLES as $role) {
            $this->assertTrue(
                $this->userWithRole($role)->hasPanelAccess(),
                "[{$role}] is expected to have panel access"
            );
        }

        foreach (self::NON_PANEL_ROLES as $role) {
            $this->assertFalse(
                $this->userWithRole($role)->hasPanelAccess(),
                "[{$role}] is expected to be kept out of the panel"
            );
        }

        // A user with no role at all is also out.
        $this->assertFalse($this->userWithRole(null)->hasPanelAccess());
    }

    public function test_the_dashboard_is_reachable_by_panel_roles_and_redirects_everyone_else(): void
    {
        foreach (self::PANEL_ROLES as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/')
                ->assertSuccessful();
        }

        foreach (self::NON_PANEL_ROLES as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/')
                ->assertRedirect(route('auth.no-access'));
        }
    }

    public function test_an_inactive_account_cannot_use_the_panel(): void
    {
        $user = $this->userWithRole('admin');
        $user->update(['is_active' => false]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function resourceAccessMatrix(): array
    {
        $superAdminOnly = ['super_admin'];
        $superAdminAndAdmin = ['super_admin', 'admin'];
        $everyPanelRole = self::PANEL_ROLES;

        $matrix = [
            // Platform RBAC administration.
            UserResource::class => $superAdminOnly,
            RoleResource::class => $superAdminOnly,
            PermissionResource::class => $superAdminOnly,
            // FieldOps catalogues.
            AccessTypeResource::class => $superAdminOnly,
            ElectricalBoardTypeResource::class => $superAdminOnly,
            FoMaintenanceTypeResource::class => $superAdminOnly,
            LuminaireFrameTypeResource::class => $superAdminOnly,
            LuminaireSubgroupResource::class => $superAdminOnly,
            LuminaireTypeResource::class => $superAdminOnly,
            SafetyTypeResource::class => $superAdminOnly,
            StructureTypeResource::class => $superAdminOnly,
            TerrainTypeResource::class => $superAdminOnly,
            SyncHistoryResource::class => $superAdminOnly,
            // FieldOps operational data.
            ComplexResource::class => $superAdminAndAdmin,
            TerrainResource::class => $superAdminAndAdmin,
            StructureResource::class => $superAdminAndAdmin,
            LuminaireFrameResource::class => $superAdminAndAdmin,
            LuminaireResource::class => $superAdminAndAdmin,
            ElectricalBoardResource::class => $superAdminAndAdmin,
            FoClientResource::class => $superAdminAndAdmin,
            FoMaintenancePlanResource::class => $superAdminAndAdmin,
            FoMaintenanceRecordResource::class => $superAdminAndAdmin,
            FoMaintenanceRequestResource::class => $superAdminAndAdmin,
            FoMaintenanceWorkOrderResource::class => $superAdminAndAdmin,
            // Open to every panel role today (no canAccess declared, or auth()->check()).
            \App\Filament\Clusters\Website\Resources\ProjectResource::class => $everyPanelRole,
            ConsultationRequestResource::class => $everyPanelRole,
            EmployeeResource::class => $everyPanelRole,
            CampaignResource::class => $everyPanelRole,
            EmailTemplateResource::class => $everyPanelRole,
            ProjectResource::class => $everyPanelRole,
            ProjectInsightResource::class => $everyPanelRole,
            ProspectResource::class => $everyPanelRole,
            ChecklistResource::class => $everyPanelRole,
            InspectionResource::class => $everyPanelRole,
        ];

        return array_combine(
            array_map(static fn (string $class): string => class_basename($class), array_keys($matrix)),
            array_map(static fn (string $class, array $roles): array => [$class, $roles], array_keys($matrix), $matrix),
        );
    }

    /**
     * @param  class-string  $resource
     * @param  array<int, string>  $allowedRoles
     */
    #[DataProvider('resourceAccessMatrix')]
    public function test_resource_access_per_role(string $resource, array $allowedRoles): void
    {
        foreach (self::PANEL_ROLES as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertSame(
                in_array($role, $allowedRoles, true),
                $resource::canAccess(),
                sprintf('%s::canAccess() for role [%s]', class_basename($resource), $role)
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function pageAccessMatrix(): array
    {
        $matrix = [
            AccessAnalyticsPage::class => ['super_admin'],
            BiConfigPage::class => ['super_admin'],
            OfferSimulator::class => ['super_admin'],
            SyncDashboardPage::class => ['super_admin'],
            MonthlyBillingControlPage::class => ['super_admin', 'admin'],
            ProjectIntelligenceDetail::class => ['super_admin', 'admin'],
            MirrorSyncStatusPage::class => ['super_admin', 'admin', 'financial_manager'],
            Dashboard::class => self::PANEL_ROLES,
            WebsiteCluster::class => self::PANEL_ROLES,
            EmployeeHoursDashboard::class => self::PANEL_ROLES,
            ProjectsWorkedHoursPage::class => self::PANEL_ROLES,
            EmployeeInsight::class => self::PANEL_ROLES,
        ];

        return array_combine(
            array_map(static fn (string $class): string => class_basename($class), array_keys($matrix)),
            array_map(static fn (string $class, array $roles): array => [$class, $roles], array_keys($matrix), $matrix),
        );
    }

    /**
     * @param  class-string  $page
     * @param  array<int, string>  $allowedRoles
     */
    #[DataProvider('pageAccessMatrix')]
    public function test_page_access_per_role(string $page, array $allowedRoles): void
    {
        foreach (self::PANEL_ROLES as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->assertSame(
                in_array($role, $allowedRoles, true),
                $page::canAccess(),
                sprintf('%s::canAccess() for role [%s]', class_basename($page), $role)
            );
        }
    }

    private function userWithRole(?string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password_set_at' => now(),
            // CLA-464 (ADR D8): super_admin/admin are now forced to set up MFA
            // before reaching the dashboard (EnsureAdminRoleMultiFactorAuthenticationIsEnabled).
            // This baseline is about role-based panel access, a separate
            // concern from MFA enrollment state — pre-enabling email MFA keeps
            // its original assertions (dashboard reachable / redirected to
            // no-access) meaningful without also asserting the MFA gate,
            // which CLA-464's own tests cover directly.
            'has_email_authentication' => true,
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user->refresh();
    }
}
