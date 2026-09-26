<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Tests\TestCase;

/**
 * K3 — the project dossier: devices and activity (docs/BACKEND-API.md §4.3).
 *
 * The device list is where the domain's two halves meet: the ETS plan and what
 * was actually registered on site, with the field registrations first and the
 * still-unconfirmed ones flagged. The activity feed is composed from the rows the
 * domain already keeps, so these tests also pin which kinds are derivable today.
 */
final class KnxProjectDossierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The tenant has to exist before the fixture runs (KnxTenant refuses to
        // invent one), and it is the fixture that creates the office account:
        // seeding *after* creating our own person would delete it, because the
        // demo seed replaces the tenant's rows.
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );

    }

    public function test_the_device_list_has_the_contract_shape(): void
    {
        $devices = $this->getJson('/api/v1/knx/projects/C1618/devices')->assertOk()->json();

        $this->assertSame(
            ['id', 'address', 'type', 'room', 'board', 'serial', 'registeredBy', 'source', 'isNew', 'hasConflict'],
            array_keys($devices[0]),
        );

        $this->assertIsString($devices[0]['id']);
        $this->assertNotNull($devices[0]['serial']);
        $this->assertContains($devices[0]['source'], ['ets', 'field']);
        $this->assertIsBool($devices[0]['isNew']);
        $this->assertIsBool($devices[0]['hasConflict']);
        // `room` is a name, never a row id.
        $this->assertIsString($devices[0]['room']);
    }

    public function test_the_field_registrations_come_before_the_ets_plan(): void
    {
        $devices = $this->getJson('/api/v1/knx/projects/C1618/devices')->assertOk()->json();

        $sources = array_column($devices, 'source');
        $firstEts = array_search('ets', $sources, true);

        $this->assertNotFalse($firstEts, 'the fixture has ETS devices');
        $this->assertNotContains('field', array_slice($sources, $firstEts), 'no field device may come after an ETS one');
    }

    public function test_the_dossier_includes_field_registrations_the_office_has_not_confirmed(): void
    {
        $devices = $this->getJson('/api/v1/knx/projects/C1618/devices')->assertOk()->json();

        // The fixture's pending notification (1.1.131) is a device the office has
        // never confirmed — that is exactly what `isNew` marks, and why it appears
        // in the dossier at all.
        $pending = collect($devices)->firstWhere('isNew', true);

        $this->assertNotNull($pending);
        $this->assertSame('1.1.131', $pending['address']);
        $this->assertSame('field', $pending['source']);

        // Everything else in the fixture is already acknowledged.
        $this->assertCount(1, collect($devices)->where('isNew', true));
    }

    public function test_an_address_with_an_open_conflict_is_flagged(): void
    {
        $devices = collect(
            $this->getJson('/api/v1/knx/projects/C1618/devices')->assertOk()->json(),
        );

        // k1 (1.1.116) and k2 (1.1.108) are `open`; k5 (1.1.121) is `verified`.
        $this->assertSame(
            ['1.1.108', '1.1.116'],
            $devices->where('hasConflict', true)->pluck('address')->sort()->values()->all(),
        );

        $verified = $devices->firstWhere('address', '1.1.121');

        if ($verified !== null) {
            $this->assertFalse($verified['hasConflict'], 'a verified conflict is no longer a collision');
        }
    }

    public function test_the_activity_feed_is_structured_and_most_recent_first(): void
    {
        $activity = $this->getJson('/api/v1/knx/projects/C1618/activity')->assertOk()->json();

        $this->assertNotEmpty($activity);

        foreach ($activity as $entry) {
            // The front composes the sentence from kind + params; the backend never
            // sends translated text.
            $this->assertArrayHasKey('at', $entry);
            $this->assertArrayHasKey('kind', $entry);
            $this->assertArrayHasKey('who', $entry);
            $this->assertContains($entry['kind'], ['conflict_reported', 'devices_registered', 'plan_uploaded']);
        }

        $timestamps = array_column($activity, 'at');
        $sorted = $timestamps;
        rsort($sorted);

        $this->assertSame($sorted, $timestamps, 'the feed must be most recent first');
    }

    public function test_the_activity_feed_reports_conflicts_and_batches_of_registrations(): void
    {
        $activity = collect(
            $this->getJson('/api/v1/knx/projects/C1618/activity')->assertOk()->json(),
        );

        $conflict = $activity->firstWhere('kind', 'conflict_reported');

        $this->assertNotNull($conflict);
        $this->assertArrayHasKey('conflictType', $conflict);
        $this->assertArrayHasKey('address', $conflict);

        $batch = $activity->firstWhere('kind', 'devices_registered');

        $this->assertNotNull($batch);
        // One entry per person and day, with the count — not one per device.
        $this->assertGreaterThanOrEqual(1, $batch['count']);
    }

    public function test_an_unknown_project_answers_404_on_both_dossier_endpoints(): void
    {
        foreach (['devices', 'activity'] as $endpoint) {
            $this->getJson("/api/v1/knx/projects/NOPE/{$endpoint}")
                ->assertNotFound()
                ->assertJsonPath('code', 'not_found');
        }
    }
}
