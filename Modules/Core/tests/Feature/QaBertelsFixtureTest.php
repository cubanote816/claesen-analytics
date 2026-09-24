<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\Commands\QaResetEnvironmentCommand;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Tests\TestCase;

/**
 * CLA-598 (T7): the local QA fixture for the Bertels panel exists only through the
 * QA reset command, is idempotent, and never touches Claesen's rows.
 */
final class QaBertelsFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_fixture_creates_bertels_org_and_site_idempotently(): void
    {
        $command = new QaResetEnvironmentCommand;
        $method = new \ReflectionMethod($command, 'createBertelsFixture');
        $method->setAccessible(true);

        $method->invoke($command);
        $method->invoke($command);

        $organization = Organization::query()->where('slug', 'electro-bertels')->sole();
        $site = Site::query()->where('key', 'electro-bertels')->sole();

        $this->assertSame($organization->id, $site->organization_id);
        $this->assertSame($site->id, Site::forPanel('bertels')?->id);
        $this->assertNotSame(Site::claesenId(), $site->id);
    }

    public function test_no_migration_creates_the_bertels_row(): void
    {
        $this->assertNull(Site::forPanel('bertels'));
    }
}
