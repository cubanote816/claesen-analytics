<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Modules\Knx\Tests\Concerns\PinsKnxDemoClock;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Release gate: KNX is not ready for production, so `knx.enabled` (env KNX_ENABLED)
 * is off by default and, when off, the module is invisible and unreachable.
 *
 * phpunit.xml turns the flag on for the whole suite so the rest of the KNX tests
 * exercise the module; these tests flip it per case and pin the production default.
 */
final class KnxFeatureFlagTest extends TestCase
{
    use PinsKnxDemoClock;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);
        $this->seed(KnxDemoSeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_the_production_default_is_off_when_knx_enabled_is_unset(): void
    {
        $env = $_ENV['KNX_ENABLED'] ?? null;
        $server = $_SERVER['KNX_ENABLED'] ?? null;
        $process = getenv('KNX_ENABLED');

        unset($_ENV['KNX_ENABLED'], $_SERVER['KNX_ENABLED']);
        putenv('KNX_ENABLED');

        try {
            $config = require module_path('Knx', 'config/config.php');
        } finally {
            $env === null || $_ENV['KNX_ENABLED'] = $env;
            $server === null || $_SERVER['KNX_ENABLED'] = $server;
            $process === false || putenv('KNX_ENABLED='.$process);
        }

        $this->assertArrayHasKey('enabled', $config);
        $this->assertFalse($config['enabled']);
    }

    public function test_the_suite_runs_with_the_flag_on(): void
    {
        $this->assertTrue(config('knx.enabled'));
    }

    public function test_flag_off_hides_the_nav_item_and_the_landing_route(): void
    {
        config(['knx.enabled' => false]);
        $this->withoutVite();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/bertels')
            ->assertSuccessful()
            ->assertDontSee(route('bertels.knx'), false);

        $this->actingAs($admin)->get('/bertels/knx')->assertNotFound();
    }

    public function test_flag_off_answers_404_on_office_and_field_api_for_an_authorized_user(): void
    {
        config(['knx.enabled' => false]);

        $office = User::query()->where('email', 'lien.smet@electrobertels.be')->sole();
        $field = User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole();

        $this->actingAs($office, 'sanctum')->getJson('/api/v1/knx/me/session')->assertNotFound();
        $this->actingAs($office, 'sanctum')->getJson('/api/v1/knx/zones')->assertNotFound();
        $this->actingAs($field, 'sanctum')->getJson('/api/v1/knx/field/session')->assertNotFound();
    }

    public function test_flag_off_does_not_reveal_the_api_even_to_anonymous_callers_or_login(): void
    {
        config(['knx.enabled' => false]);

        $this->getJson('/api/v1/knx/me/session')->assertNotFound();
        $this->postJson('/api/v1/knx/auth/login', ['email' => 'x@example.com', 'password' => 'x'])->assertNotFound();

        // Same shape as a path that never existed: no KNX error envelope (`code`).
        $this->assertSame(
            array_keys($this->getJson('/api/v1/knx/nothing-here')->json()),
            array_keys($this->getJson('/api/v1/knx/me/session')->json()),
        );
    }

    public function test_flag_off_schedules_nothing_for_knx(): void
    {
        config(['knx.enabled' => false]);

        foreach (app(Schedule::class)->events() as $event) {
            $this->assertStringNotContainsStringIgnoringCase('knx', (string) $event->command);
            $this->assertStringNotContainsStringIgnoringCase('knx', (string) $event->description);
        }
    }

    public function test_flag_on_shows_the_nav_item_and_the_routes_behave_as_before(): void
    {
        config(['knx.enabled' => true]);
        $this->withoutVite();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/bertels')
            ->assertSuccessful()
            ->assertSee(route('bertels.knx'), false);

        $this->actingAs($admin)->get('/bertels/knx')->assertSuccessful();

        $office = User::query()->where('email', 'lien.smet@electrobertels.be')->sole();
        $field = User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole();

        $this->actingAs($office, 'sanctum')->getJson('/api/v1/knx/me/session')->assertOk();
        $this->actingAs($field, 'sanctum')->getJson('/api/v1/knx/field/session')->assertOk();
        $this->postJson('/api/v1/knx/auth/login', ['email' => 'x@example.com', 'password' => 'x'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error');
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'password_set_at' => now(),
            'has_email_authentication' => true,
        ]);
        $user->assignRole('super_admin');

        return $user->refresh();
    }
}
