<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Modules\Core\Models\Organization;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxAcceptanceTest;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;
use Tests\TestCase;

/**
 * The demo seed is the bridge that makes `VITE_API_MODE=real` look like
 * `VITE_API_MODE=mock`, so what it guarantees is parity with the front's own
 * fixture — plus the awkward cases the UI is designed around.
 */
final class KnxDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);
        $this->seed(KnxDemoSeeder::class);
    }

    public function test_it_seeds_the_projects_of_the_office_apps_fixture(): void
    {
        $this->assertSame(
            ['C1618', '239870', '232146', '240512', '228104'],
            KnxProject::query()->orderBy('id')->pluck('code')->all(),
        );

        $project = KnxProject::query()->where('code', 'C1618')->sole();

        $this->assertSame('UV Campus · Gelijkvloers', $project->name);
        $this->assertSame('Heverlee', $project->city);
        $this->assertSame(24, $project->devices_planned);
        $this->assertSame(17, $project->devices_done);
        $this->assertSame('busy', $project->status);
        // The contract wants `lead` as a short name; it is derived, not stored.
        $this->assertSame('L. Smet', $project->lead->shortName());
    }

    public function test_it_seeds_people_for_both_apps(): void
    {
        $this->assertSame(2, KnxEmployee::query()->office()->count());
        $this->assertSame(5, KnxEmployee::query()->field()->count());

        // The initials of the front's technician list, derived by the model.
        $this->assertSame(
            ['JV', 'MC', 'SW', 'TJ', 'KP'],
            KnxEmployee::query()->field()->orderBy('id')->pluck('initials')->all(),
        );
    }

    public function test_the_devices_reproduce_the_fixtures_generation_formula(): void
    {
        $project = KnxProject::query()->where('code', 'C1618')->sole();

        // The generated devices are the ones the fixture derives from the project's
        // `devicesDone`; the two pending field registrations are separate rows (the
        // notification is the event, the device is the registration).
        $generated = KnxDevice::query()
            ->where('project_id', $project->id)
            ->where('address', '!=', '1.1.131')
            ->orderBy('address')
            ->get();

        $this->assertCount(17, $generated);
        $this->assertSame('1.1.100', $generated->first()->address);
        $this->assertSame('Aanwezigheidsdetector', $generated->get(1)->type);
        $this->assertSame(KnxDevice::SOURCE_ETS, $generated->first()->source);
        // i % 4 === 0 → ets, everything else comes from the field.
        $this->assertSame(KnxDevice::SOURCE_FIELD, $generated->get(1)->source);

        // The field registration the office has not confirmed yet is in the dossier
        // too, flagged as new.
        $pending = KnxDevice::query()->where('project_id', $project->id)->where('address', '1.1.131')->sole();

        $this->assertSame(KnxDevice::SOURCE_FIELD, $pending->source);
        $this->assertNull($pending->acknowledged_at);
        $this->assertTrue($pending->isNew());
    }

    public function test_the_zone_checks_derive_the_statuses_the_ui_expects(): void
    {
        $statuses = KnxZone::query()->with('checks')->get()
            ->mapWithKeys(fn (KnxZone $zone): array => [$zone->name => $zone->derivedStatus()])
            ->all();

        $this->assertSame('ready', $statuses['Inkomhal']);
        $this->assertSame('blocked', $statuses['Vergaderzaal']);
        $this->assertSame('in_progress', $statuses['Gang gelijkvloers']);
        $this->assertSame('blocked', $statuses['Zaal 2.03']);

        // The blocker is the root cause (first failed check in enum order), not
        // the follow-up action that comes after it.
        $zone = KnxZone::query()->where('name', 'Vergaderzaal')->sole();
        $this->assertSame('function_approved', $zone->blockingCheck()?->key);
        $this->assertSame(
            'DALI-driver niet geleverd — kan verlichting niet testen',
            $zone->blockingCheck()?->note,
        );

        // Every zone carries all eight checks, and the model hands them back in
        // the contract's order even though the database does not guarantee it.
        $this->assertSame(
            KnxZoneCheck::keys(),
            $zone->orderedChecks()->pluck('key')->all(),
        );
    }

    public function test_it_seeds_the_conflict_workflow_mid_flight(): void
    {
        // k5 is the one the fixture shows already verified: its history is the
        // ETS worklist the module exists to produce.
        $verified = KnxConflict::query()->where('status', 'verified')->sole();

        $this->assertSame(
            ['reported', 'ets_listed', 'applied', 'downloaded', 'verified'],
            $verified->logs()->orderBy('at')->pluck('action')->all(),
        );

        // k1, k2 and k4 are 'open' and k3 is 'in_review' — the four the office
        // still has to work.
        $this->assertSame(4, KnxConflict::query()->open()->count());
    }

    public function test_it_seeds_the_superseded_document_revision(): void
    {
        // The document-review quick win needs a non-current revision to show.
        $superseded = KnxDocument::query()->where('is_current', false)->sole();

        $this->assertSame('C1618_ETS-export_0409.knxproj', $superseded->name);
        $this->assertSame('Rev. B', $superseded->revision);
        $this->assertNull($superseded->approvedBy);
    }

    public function test_it_seeds_a_failed_acceptance_test_with_its_issue(): void
    {
        $failed = KnxAcceptanceTest::query()->where('status', 'failed')->sole();

        $this->assertSame('iss-t-c1618-5', $failed->issue_id);
        $this->assertSame('S. Wouters', $failed->executor?->shortName());
        $this->assertTrue($failed->physical_check);
    }

    /**
     * The change that matters: a second run must NOT touch the tenant.
     *
     * This test discriminates between the old and the new behaviour. Before, the
     * seeder purged and recreated everything, so re-running succeeded and the totals
     * came out the same — the bug could hide behind equal counts. Now it must REFUSE,
     * and the ids have to be exactly the ones the first run created.
     */
    public function test_re_running_refuses_and_changes_nothing(): void
    {
        $projects = KnxProject::query()->orderBy('code')->pluck('code', 'id')->all();
        $boards = KnxBoard::query()->orderBy('id')->pluck('slug', 'id')->all();
        $devices = KnxDevice::query()->count();

        try {
            (new KnxDemoSeeder)->run();
            $this->fail('the seeder ran again over existing KNX projects');
        } catch (LogicException $e) {
            $this->assertStringContainsString('already has KNX projects', $e->getMessage());
        }

        // Same rows AND same ids: nothing was purged and recreated.
        $this->assertSame($projects, KnxProject::query()->orderBy('code')->pluck('code', 'id')->all());
        $this->assertSame($boards, KnxBoard::query()->orderBy('id')->pluck('slug', 'id')->all());
        $this->assertSame($devices, KnxDevice::query()->count());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must never run in production');

        (new KnxDemoSeeder)->run();
    }

    public function test_it_refuses_to_run_outside_the_test_suite_without_an_explicit_confirmation(): void
    {
        $this->app['env'] = 'local';
        config(['knx.demo_seed_confirm' => false]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Set KNX_DEMO_SEED_CONFIRM=true');

        (new KnxDemoSeeder)->run();
    }

    /**
     * The point of the guard is that it fires *before* anything is deleted — a
     * guard placed after `purge()` would pass the two tests above and still wipe
     * the tenant.
     *
     * Counting fixture rows would not prove that: without the guard the seeder
     * purges *and* recreates them, so the totals come out the same either way.
     * The sentinel is a row the fixture never creates, so it can only survive if
     * the guard really runs first.
     */
    public function test_the_refusal_happens_before_it_deletes_anything(): void
    {
        $sentinel = KnxProject::factory()->create(['code' => 'SENTINEL-1']);

        $this->app['env'] = 'local';
        config(['knx.demo_seed_confirm' => false]);

        try {
            (new KnxDemoSeeder)->run();
            $this->fail('the seeder wiped the tenant without an explicit confirmation');
        } catch (LogicException) {
            // Expected: refused before the purge.
        }

        $this->assertTrue(
            KnxProject::query()->whereKey($sentinel->getKey())->exists(),
            'the sentinel project is gone, so the guard ran after purge()',
        );
    }

    /**
     * The confirmation is what allows a run OUTSIDE the test suite; it is not a
     * licence to run over existing work. With projects present the seeder still refuses,
     * which is the whole point of the change.
     */
    public function test_an_explicit_confirmation_does_not_override_the_existing_projects_refusal(): void
    {
        $this->app['env'] = 'local';
        config(['knx.demo_seed_confirm' => true]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already has KNX projects');

        (new KnxDemoSeeder)->run();
    }
}
