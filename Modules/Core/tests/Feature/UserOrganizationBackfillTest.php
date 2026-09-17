<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Console\Commands\BackfillUserOrganizationsCommand;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Core\Services\OrganizationContext;
use Tests\TestCase;

/**
 * F1/P2 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Covers CLA-459's acceptance criteria: the users.organization_id column,
 * the idempotent/resumable backfill command with dry-run counts, every
 * creation path defaulting to Claesen, and the resolve-only OrganizationContext.
 */
final class UserOrganizationBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_users_table_has_a_nullable_organization_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'organization_id'));

        // Factory-created users always resolve Claesen (see UserFactory), so
        // assert nullability directly against the schema instead.
        $columns = DB::select("SHOW COLUMNS FROM users WHERE Field = 'organization_id'");
        $this->assertSame('YES', $columns[0]->Null);
    }

    public function test_deleting_an_organization_with_users_attached_is_restricted(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('organizations')->where('id', $user->organization_id)->delete();
    }

    public function test_new_users_default_to_the_claesen_organization_via_the_factory(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Organization::claesenId(), $user->organization_id);
    }

    public function test_dry_run_reports_counts_without_writing(): void
    {
        $unassigned = User::factory()->count(3)->create(['organization_id' => null]);

        $this->artisan(BackfillUserOrganizationsCommand::class, ['--dry-run' => true])
            ->assertSuccessful();

        foreach ($unassigned as $user) {
            $this->assertNull($user->fresh()->organization_id);
        }
    }

    public function test_apply_assigns_claesen_to_every_unassigned_user(): void
    {
        $unassigned = User::factory()->count(3)->create(['organization_id' => null]);
        $alreadyAssigned = User::factory()->create();

        $this->artisan(BackfillUserOrganizationsCommand::class, ['--apply' => true])
            ->assertSuccessful();

        foreach ($unassigned as $user) {
            $this->assertSame(Organization::claesenId(), $user->fresh()->organization_id);
        }
        // Untouched — still points at whatever it already had.
        $this->assertSame(Organization::claesenId(), $alreadyAssigned->fresh()->organization_id);
        $this->assertSame(0, User::query()->whereNull('organization_id')->count());
    }

    public function test_apply_never_overwrites_a_user_already_assigned_to_a_non_claesen_organization(): void
    {
        $otherOrg = Organization::factory()->create(['slug' => 'electro-bertels']);
        $bertelsUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $unassigned = User::factory()->create(['organization_id' => null]);

        $this->artisan(BackfillUserOrganizationsCommand::class, ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame($otherOrg->id, $bertelsUser->fresh()->organization_id);
        $this->assertSame(Organization::claesenId(), $unassigned->fresh()->organization_id);
    }

    public function test_apply_is_idempotent_on_a_second_run(): void
    {
        User::factory()->count(2)->create(['organization_id' => null]);

        Artisan::call(BackfillUserOrganizationsCommand::class, ['--apply' => true]);
        $this->assertSame(0, User::query()->whereNull('organization_id')->count());

        // Second run: nothing left to assign, still succeeds, still 0 unassigned.
        $this->artisan(BackfillUserOrganizationsCommand::class, ['--apply' => true])
            ->assertSuccessful();
        $this->assertSame(0, User::query()->whereNull('organization_id')->count());
    }

    public function test_apply_with_nothing_unassigned_is_a_no_op(): void
    {
        User::factory()->count(2)->create();

        $this->artisan(BackfillUserOrganizationsCommand::class, ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame(0, User::query()->whereNull('organization_id')->count());
    }

    public function test_roles_and_permissions_seeder_assigns_claesen_to_the_bootstrap_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::where('email', 'admin@claesen-analytics.com')->firstOrFail();

        $this->assertSame(Organization::claesenId(), $admin->organization_id);
    }

    public function test_organization_context_resolves_the_authenticated_users_organization(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $context = app(OrganizationContext::class);

        $this->assertNotNull($context->resolve());
        $this->assertTrue($context->resolve()->is($user->organization));
        $this->assertSame(Organization::claesenId(), $context->id());
    }

    public function test_organization_context_resolves_null_for_a_guest(): void
    {
        $context = app(OrganizationContext::class);

        $this->assertNull($context->resolve());
        $this->assertNull($context->id());
    }

    public function test_organization_context_is_registered_scoped_not_singleton(): void
    {
        $first = app(OrganizationContext::class);
        $this->app->forgetScopedInstances();
        $second = app(OrganizationContext::class);

        $this->assertNotSame($first, $second);
    }
}
