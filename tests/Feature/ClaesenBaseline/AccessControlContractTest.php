<?php

declare(strict_types=1);

namespace Tests\Feature\ClaesenBaseline;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Freezes the authorization decisions that the multi-organization program has to
 * change, and the known gaps it has to close, as assertions over the real source.
 *
 * Why source assertions instead of behaviour here: every one of these decisions
 * is expressed as a hard-coded global Spatie role list, and reproducing the
 * behaviour would need a database plus one user per role. The behavioural half of
 * the matrix lives in PanelAccessMatrixTest (database-backed). This test is the
 * cheap, database-free tripwire: the day someone edits one of these role lists,
 * recipient queries or missing guards, it fails and forces the change to be
 * declared instead of slipping through.
 *
 * A failure here is not automatically a bug. It means: update this baseline in
 * the same pull request, on purpose.
 */
final class AccessControlContractTest extends TestCase
{
    public function test_panel_access_is_decided_by_a_global_role_allowlist(): void
    {
        $user = $this->source('Modules/Core/Models/User.php');

        // The roles that can reach the Filament backoffice today. Organization
        // membership is not part of the decision yet — that is exactly what the
        // multi-organization phases add.
        $this->assertStringContainsString("if (\$this->hasRole('client')) {", $user);
        $this->assertStringContainsString(
            "return \$this->hasAnyRole([\n            'super_admin',\n            'admin',\n"
            ."            'financial_manager',\n            'hr_manager',\n            'viewer',\n        ]);",
            $user
        );

        // canAccessPanel() still ignores the panel argument: it only checks
        // is_active. The per-organization check will land here.
        $this->assertStringContainsString('public function canAccessPanel(Panel $panel): bool', $user);
        $this->assertStringContainsString('return (bool) $this->is_active;', $user);
    }

    public function test_super_admin_bypasses_every_policy_through_gate_before_except_a_cross_organization_subject(): void
    {
        // Declared inversion (F1/P5c, CLA-552-cont, ADR D5): this used to freeze
        // the CRITICAL gap the comment above named — "no policy can deny
        // super_admin, the organization boundary must be evaluated before this
        // bypass" — as a literal, unconditional Gate::before. P5c closed exactly
        // that gap: with organizations.enforce off (every environment today)
        // this is still byte-for-byte the old unconditional bypass; with it on,
        // a subject registered in organizations.owned_models only bypasses when
        // it belongs to the super_admin's own organization.
        $appServiceProvider = $this->source('app/Providers/AppServiceProvider.php');

        $this->assertStringContainsString("if (! \$user->hasRole('super_admin')) {\n                return null;", $appServiceProvider);
        $this->assertStringContainsString("if (! config('organizations.enforce')) {\n                return true;", $appServiceProvider);
        $this->assertStringContainsString('$ownedModules = config(\'organizations.owned_models\');', $appServiceProvider);
        $this->assertStringContainsString('$user->organization?->slug === $owningOrgSlug', $appServiceProvider);
    }

    public function test_roles_are_global_because_spatie_teams_are_disabled(): void
    {
        $this->assertFalse(config('permission.teams'));
        $this->assertNull(config('permission.models.team'));
    }

    public function test_api_and_app_logins_are_gated_by_role_only(): void
    {
        $auth = $this->source('Modules/Core/Http/Controllers/Auth/AuthController.php');

        $this->assertStringContainsString("'client_portal', ['client']", $auth);
        $this->assertStringContainsString(
            "'sport', ['technician', 'project_manager', 'super_admin', 'admin']",
            $auth
        );

        $this->assertStringContainsString(
            "hasAnyRole(['project_manager', 'super_admin', 'admin'])",
            $this->source('Modules/Safety/Http/Middleware/EnsureSafetyAccess.php')
        );

        $this->assertStringContainsString(
            "hasAnyRole(['client', 'technician']",
            $this->source('Modules/Core/Filament/Pages/Auth/Login.php')
        );
    }

    public function test_azure_login_only_accepts_pre_existing_users_and_falls_back_to_viewer(): void
    {
        $this->assertStringContainsString(
            "\$user = User::where('email', \$azureUser->getEmail())->first();",
            $this->source('Modules/Core/Http/Controllers/Auth/MicrosoftAuthController.php')
        );

        // A user whose Azure groups match nothing gets 'viewer', which is inside
        // the panel allowlist above. Per-organization panel routing has to account
        // for this fallback.
        $this->assertStringContainsString(
            "\$rolesToAssign[] = 'viewer';",
            $this->source('Modules/Core/Services/Auth/AzureRoleService.php')
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function operationalRecipientQueries(): array
    {
        // Declared inversion (F1/P5d, ADR §6 "asíncrono y destinatarios"): all
        // 7 sites now chain User::scopeInOrganization() — inert with
        // organizations.enforce off (the default everywhere today), so this
        // still freezes each query as global-role selection in practice. The
        // presence of ->inOrganization() itself is what this baseline exists
        // to keep frozen from here on, not its absence.
        return [
            'FieldOps request notifications' => [
                'Modules/FieldOps/Services/MaintenanceRequestService.php',
                "->whereHas('roles', fn (\$query) => \$query->whereIn('name', ['admin', 'super_admin']))\n            ->inOrganization()",
            ],
            'FieldOps request alerts' => [
                'Modules/FieldOps/Services/MaintenanceRequestAlertService.php',
                "->whereHas('roles', fn (\$query) => \$query->whereIn('name', ['admin', 'super_admin']))\n            ->inOrganization()",
            ],
            'Safety compliance command' => [
                'Modules/Safety/Console/CheckSafetyComplianceCommand.php',
                "User::role('super_admin')->inOrganization()->get()",
            ],
            'Safety checklist observer' => [
                'Modules/Safety/Observers/ChecklistObserver.php',
                "User::role(['project_manager', 'super_admin'])->inOrganization()->get()",
            ],
            'Safety inspection reminders' => [
                'Modules/Safety/Services/InspectionReminderService.php',
                "User::role('project_manager')->inOrganization()->get()",
            ],
            'Safety inspection controller' => [
                'Modules/Safety/Http/Controllers/InspectionController.php',
                "User::role('super_admin')->inOrganization()->get()",
            ],
            'Mailing deliverability alerts' => [
                'Modules/Mailing/Console/CheckDeliverabilityAlertsCommand.php',
                "User::whereHas('roles', fn (\$q) => \$q->whereIn('id', \$roleIds))->inOrganization()->get()",
            ],
        ];
    }

    /**
     * F1/P5d (ADR §6, "asíncrono y destinatarios"): closed the CRITICAL gap this
     * docblock used to describe — "the day a Bertels user holds one of those
     * roles, Claesen operational data reaches them by e-mail and in the
     * notification bell". Every site now also filters by organization via
     * User::scopeInOrganization(), inert with the flag off (today's default).
     */
    #[DataProvider('operationalRecipientQueries')]
    public function test_operational_notification_recipients_are_selected_by_global_role(
        string $file,
        string $query,
    ): void {
        $this->assertStringContainsString($query, $this->source($file));
    }

    public function test_website_projects_and_leads_are_not_restricted_by_role_in_the_panel(): void
    {
        // Documented gap (pre-existing): unlike every Claesen module resource,
        // the Website resources declare no canAccess(), so any role with panel
        // access can read and edit public projects and incoming leads.
        foreach ([
            'app/Filament/Clusters/Website/Resources/ProjectResource.php',
            'app/Filament/Clusters/Website/Resources/ConsultationRequestResource.php',
        ] as $file) {
            $this->assertStringNotContainsString('function canAccess', $this->source($file));
        }

        // F4/CLA-477 declared inversion: the assignee picker used to list
        // every user in the database unconditionally. It now narrows to the
        // record's own site's organization once config('organizations.enforce')
        // is on (D4) — inert today (the flag's default everywhere), so this
        // assigns-any-user behaviour still holds in practice, but via the
        // scoped query rather than an unconditional relationship() call.
        $source = $this->source('app/Filament/Clusters/Website/Resources/ConsultationRequestResource.php');
        $this->assertStringContainsString('assignableUsersQuery', $source);
        $this->assertStringContainsString("config('organizations.enforce')", $source);
    }

    public function test_the_public_website_api_is_unauthenticated_unthrottled_and_now_site_aware(): void
    {
        // F3/CLA-471 declared inversion: the public read path used to have no
        // notion of a site anywhere — a Bertels project would have surfaced
        // on the Claesen website. Modules\Core\Http\Middleware\
        // ResolveRequestSite (domain/?site= resolution, defaulting to
        // Claesen) plus Modules\Core\Models\Concerns\BelongsToSite's now-real
        // global scope close that gap; behavioural coverage lives in
        // Modules\Website\tests\Feature\PublicApiSiteScopingTest (real cross-
        // site HTTP requests) and Modules\Core\tests\Feature\
        // BelongsToSiteEnforcementTest (the scope in isolation) — both
        // database-backed, unlike this source-only test.
        $routes = $this->source('Modules/Website/Routes/api.php');

        // Still true, unchanged by this ticket: no auth and no throttle on
        // the public intake/read endpoints (documented gap).
        $this->assertStringNotContainsString('auth:sanctum', $routes);
        $this->assertStringNotContainsString('throttle', $routes);

        $this->assertStringContainsString('ResolveRequestSite::class', $routes);
    }

    public function test_static_site_publication_defaults_to_claesen_and_shares_one_global_webhook(): void
    {
        // F1/P3b (CLA-548) gave the table a per-site row (`firstOrCreate` on
        // site_id, no more hardcoded id = 1) — declared inversion of this
        // baseline's previous assertion. What has NOT changed, and is the
        // gap this test still exists to freeze: `current()` still defaults to
        // Claesen when no site is passed, every caller in the codebase still
        // omits that argument, and there is still exactly one webhook target
        // for the whole installation — publishing a Bertels record would
        // still rebuild Claesen's static site until F3/F4 give static_site
        // its own per-site configuration.
        $source = $this->source('Modules/Website/Models/PublicationState.php');
        $this->assertStringContainsString('Site::claesenId()', $source);
        $this->assertStringContainsString('firstOrCreate(', $source);
        $this->assertStringNotContainsString('static::find(1)', $source);

        $this->assertSame(
            ['enabled', 'environment', 'webhook_url', 'webhook_secret', 'webhook_timeout', 'health_url', 'debounce_seconds', 'signature_tolerance_seconds'],
            array_keys(config('static_site')),
        );
    }

    public function test_the_lead_notification_recipient_is_a_single_global_address(): void
    {
        $this->assertArrayHasKey('consultation_notification_email', config('website'));
    }

    private function source(string $relativePath): string
    {
        $path = base_path($relativePath);
        $this->assertFileExists($path, "Baseline file [{$relativePath}] moved or was removed.");

        return (string) file_get_contents($path);
    }
}
