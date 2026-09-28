<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\Commands\QaResetEnvironmentCommand;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-598 (T7): the local QA fixture for the Bertels panel exists only through the
 * QA reset command, is idempotent, and never touches Claesen's rows.
 * CLA-603: it now also carries Bertels' QA user, without which the panel cannot be
 * signed into at all.
 */
final class QaBertelsFixtureTest extends TestCase
{
    use RefreshDatabase;

    private function runFixture(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $command = new QaResetEnvironmentCommand;
        $method = new \ReflectionMethod($command, 'createBertelsFixture');
        $method->setAccessible(true);

        $method->invoke($command);
    }

    public function test_the_fixture_creates_bertels_org_and_site_idempotently(): void
    {
        $this->runFixture();
        $this->runFixture();

        $organization = Organization::query()->where('slug', 'electro-bertels')->sole();
        $site = Site::query()->where('key', 'electro-bertels')->sole();

        $this->assertSame($organization->id, $site->organization_id);
        $this->assertSame($site->id, Site::forPanel('bertels')?->id);
        $this->assertNotSame(Site::claesenId(), $site->id);
    }

    public function test_the_fixture_creates_a_bertels_user_that_can_reach_that_panel(): void
    {
        $this->runFixture();
        $this->runFixture();

        $organization = Organization::query()->where('slug', 'electro-bertels')->sole();
        $user = User::query()->where('email', 'qa.bertels@electro-bertels.test')->sole();

        $this->assertSame($organization->id, $user->organization_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasCompletedPasswordSetup());
        $this->assertTrue($user->hasRole('super_admin'));

        // The point of the account: without super_admin, User::canAccessPanel()
        // refuses the bertels panel outright (ADR D10), so a QA user of any other
        // role would look broken rather than restricted.
        $this->assertTrue($user->canAccessPanel(\Filament\Facades\Filament::getPanel('bertels')));
    }

    public function test_no_migration_creates_the_bertels_row(): void
    {
        $this->assertNull(Site::forPanel('bertels'));
        $this->assertNull(User::query()->where('email', 'qa.bertels@electro-bertels.test')->first());
    }
}
