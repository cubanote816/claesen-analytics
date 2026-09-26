<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxAcceptanceTest;
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
        $devices = KnxDevice::query()
            ->where('project_id', KnxProject::query()->where('code', 'C1618')->sole()->id)
            ->orderBy('address')
            ->get();

        $this->assertCount(17, $devices);
        $this->assertSame('1.1.100', $devices->first()->address);
        $this->assertSame('Aanwezigheidsdetector', $devices->get(1)->type);
        $this->assertSame(KnxDevice::SOURCE_ETS, $devices->first()->source);
        // i % 4 === 0 → ets, everything else comes from the field.
        $this->assertSame(KnxDevice::SOURCE_FIELD, $devices->get(1)->source);
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

    public function test_re_running_replaces_the_demo_data_instead_of_duplicating_it(): void
    {
        $before = [
            KnxProject::query()->count(),
            KnxDevice::query()->count(),
            KnxZoneCheck::query()->count(),
            KnxConflict::query()->count(),
        ];

        $this->seed(KnxDemoSeeder::class);

        $this->assertSame($before, [
            KnxProject::query()->count(),
            KnxDevice::query()->count(),
            KnxZoneCheck::query()->count(),
            KnxConflict::query()->count(),
        ]);
    }
}
