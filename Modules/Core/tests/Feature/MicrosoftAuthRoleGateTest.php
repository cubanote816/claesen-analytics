<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Modules\Core\Http\Controllers\Auth\MicrosoftAuthController;
use Modules\Core\Services\Auth\FrontendRedirectService;
use ReflectionMethod;
use Tests\TestCase;

// CLA-363: MicrosoftAuthController::roleGateForFrontend() is the OAuth-callback
// counterpart of AuthController::loginSafety()/loginSport()/loginClientPortal() —
// it decides which roles may reach each destination before Auth::login() runs.
// Exercised directly (reflection) rather than through the full callback(), since
// callback() talks to Socialite and this codebase has no existing pattern for
// faking that driver in tests.
class MicrosoftAuthRoleGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('fieldops.client_portal_url', 'http://localhost:5180');
        Config::set('fieldops.safety_app_url', 'http://localhost:5173');
        Config::set('fieldops.field_app_url', 'http://localhost:5174');
    }

    private function roleGateForFrontend(?string $intendedFrontendUrl): ?array
    {
        $method = new ReflectionMethod(MicrosoftAuthController::class, 'roleGateForFrontend');
        $method->setAccessible(true);

        return $method->invoke(app(MicrosoftAuthController::class), app(FrontendRedirectService::class), $intendedFrontendUrl);
    }

    public function test_client_portal_destination_only_allows_client(): void
    {
        $gate = $this->roleGateForFrontend('http://localhost:5180/dashboard');

        $this->assertSame(['client'], $gate['roles']);
        $this->assertSame('client_portal', $gate['appSource']);
    }

    public function test_safety_destination_allows_project_manager_and_admin_roles_only(): void
    {
        $gate = $this->roleGateForFrontend('http://localhost:5173/inspections/5');

        $this->assertSame(['project_manager', 'super_admin', 'admin'], $gate['roles']);
        $this->assertSame('safety', $gate['appSource']);
    }

    public function test_sport_destination_also_allows_technician(): void
    {
        $gate = $this->roleGateForFrontend('http://localhost:5174/work-orders');

        $this->assertSame(['technician', 'project_manager', 'super_admin', 'admin'], $gate['roles']);
        $this->assertSame('sport', $gate['appSource']);
    }

    public function test_unrecognised_destination_has_no_role_gate(): void
    {
        $gate = $this->roleGateForFrontend('https://unrelated.example/');

        $this->assertNull($gate);
    }

    public function test_unset_safety_url_never_matches_in_local_dev(): void
    {
        Config::set('fieldops.safety_app_url', null);

        $gate = $this->roleGateForFrontend('http://localhost:5173/inspections/5');

        $this->assertNull($gate);
    }
}
