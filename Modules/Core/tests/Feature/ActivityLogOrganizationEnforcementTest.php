<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Organization;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Tests\TestCase;

/**
 * F2/CLA-465 of the multi-organization program — docs/ai/adr-multi-organization.md,
 * decision D3.
 *
 * Exercises Modules\Core\Models\ActivityLogEntry directly (the class
 * config('activitylog.activity_model') now points to), independent of any
 * particular LogsActivity-using model or manual activity() call site.
 */
final class ActivityLogOrganizationEnforcementTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    public function test_an_activity_caused_by_a_claesen_user_is_stamped_with_the_claesen_organization(): void
    {
        $user = $this->claesenUser();

        activity('test')->causedBy($user)->log('did something');

        $entry = ActivityLogEntry::query()->latest('id')->firstOrFail();

        $this->assertSame(Organization::claesenId(), $entry->organization_id);
    }

    public function test_an_activity_caused_by_a_fixture_org_user_is_stamped_with_that_organization(): void
    {
        $organization = $this->bertelsFixtureOrganization();
        $user = $this->bertelsFixtureUser($organization);

        activity('test')->causedBy($user)->log('did something');

        $entry = ActivityLogEntry::query()->latest('id')->firstOrFail();

        $this->assertSame($organization->id, $entry->organization_id);
    }

    public function test_an_activity_with_no_causer_is_left_without_an_organization(): void
    {
        activity('test')->log('system event, no causer');

        $entry = ActivityLogEntry::query()->latest('id')->firstOrFail();

        $this->assertNull($entry->organization_id);
    }

    public function test_an_explicitly_set_organization_id_is_never_overwritten(): void
    {
        $causerOrganization = $this->bertelsFixtureOrganization();
        $causer = $this->bertelsFixtureUser($causerOrganization);
        $explicitOrganization = $this->bertelsFixtureOrganization();

        $entry = new ActivityLogEntry;
        $entry->causer()->associate($causer);
        $entry->organization_id = $explicitOrganization->id;
        $entry->description = 'explicit organization wins';
        $entry->save();

        $this->assertSame($explicitOrganization->id, $entry->fresh()->organization_id);
    }

    public function test_updating_an_activity_through_eloquent_throws(): void
    {
        activity('test')->log('immutable once written');
        $entry = ActivityLogEntry::query()->latest('id')->firstOrFail();

        $this->expectException(\LogicException::class);

        $entry->description = 'tampered';
        $entry->save();
    }

    public function test_deleting_an_activity_instance_through_eloquent_throws(): void
    {
        activity('test')->log('cannot be deleted directly');
        $entry = ActivityLogEntry::query()->latest('id')->firstOrFail();

        $this->expectException(\LogicException::class);

        $entry->delete();
    }

    public function test_the_retention_cleanup_command_bulk_deletes_without_the_append_only_guard_blocking_it(): void
    {
        activity('test')->log('will be cleaned up');
        $entry = ActivityLogEntry::query()->latest('id')->firstOrFail();
        $entry->forceFill(['created_at' => now()->subDays(400)])->saveQuietly();

        Artisan::call('activitylog:clean');

        $this->assertModelMissing($entry);
    }
}
