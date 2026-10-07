<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxVisit;
use Tests\TestCase;

/**
 * The one race the field `clientId` cannot avoid (CLA-609).
 *
 * A queued offline write is retried while the original request is still being processed.
 * Both pass the "does this key exist already?" read, both try to insert, and the unique
 * index makes one of them lose. Losing must answer the same `200` with the winner's row:
 * a `500` is exactly what the field app retries, so a `500` here would loop forever — the
 * failure would feed itself.
 *
 * This class needs two things the rest of the suite does not:
 *
 *   - **No `RefreshDatabase`.** Its transaction would roll the loser's write back together
 *     with everything else, and the point of this test is a competitor whose row *survives*
 *     that rollback, because it belongs to another request. `DatabaseMigrations` rebuilds
 *     the schema per test instead of wrapping it.
 *   - **A second connection.** A row inserted on the loser's own connection cannot
 *     survive its rollback, so the competitor writes through `competitor`, a second
 *     connection to the same database — which is what another HTTP request would be.
 *
 * The three services share the pattern (devices, issues, visits), so all three are
 * checked here: fixing one and leaving the others would be a half fix.
 *
 * **It is skipped unless `RUN_CONCURRENCY_TESTS=1`.** It is the only test in the suite that
 * drops and rebuilds the schema, because committed rows are the point of it — and doing that
 * in the middle of a full run takes the database out from under the classes that come next
 * (they fail with "Table 'migrations' doesn't exist"). Run it on purpose, against its own
 * database:
 *
 *     RUN_CONCURRENCY_TESTS=1 php artisan test --filter=KnxFieldIdempotencyRaceTest
 */
final class KnxFieldIdempotencyRaceTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (getenv('RUN_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped(
                'Rebuilds the schema (it needs committed rows); run it on purpose with '
                .'RUN_CONCURRENCY_TESTS=1 against its own database.'
            );
        }

        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        // The same database, a different connection: a second client as far as MySQL is
        // concerned, which is what makes the competing write independent.
        $default = config('database.default');

        config(['database.connections.competitor' => array_merge(
            config("database.connections.{$default}"),
            ['database' => config("database.connections.{$default}.database")],
        )]);
    }

    /**
     * No schema rollback on teardown.
     *
     * `DatabaseMigrations` normally rolls the schema back after the test, and this
     * project's `organizations` migration deliberately refuses to drop itself while a
     * non-Claesen organization exists (ADR «Rollback Plan»: a `down()` never discards real
     * organization data). A race test needs a rebuilt schema to start from, not a
     * rollback — and `migrate:fresh` at the start of the next run drops everything anyway.
     */
    protected function beforeApplicationDestroyed(callable $callback): void
    {
        // Deliberately does not register the rollback.
    }

    /**
     * Put the schema back the way the rest of the suite expects to find it — empty.
     *
     * This class has to write **committed** rows (the competitor must survive the loser's
     * rollback), so it cannot leave them behind: every other test class assumes
     * `RefreshDatabase`'s empty-database contract. `migrate:fresh` drops tables directly
     * and never runs a `down()`, which is exactly what the organizations migration refuses
     * to do while a non-Claesen organization exists.
     */
    protected function tearDown(): void
    {
        // Only when the test actually ran: otherwise this would wipe the schema out from
        // under a suite that is still going.
        if (getenv('RUN_CONCURRENCY_TESTS') === '1') {
            $this->artisan('migrate:fresh');

            // And let the next class migrate for itself, like a cold start.
            RefreshDatabaseState::$migrated = false;
        }

        parent::tearDown();
    }

    private function user(): User
    {
        return User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole();
    }

    private function project(string $code = 'C1618'): KnxProject
    {
        return KnxProject::query()->where('code', $code)->sole();
    }

    private function organizationId(): int
    {
        return Organization::query()->where('slug', 'electro-bertels')->sole()->getKey();
    }

    /** The fixture plans Monday to Friday; today needs a row of its own. */
    private function planToday(): void
    {
        $employee = KnxEmployee::query()->where('name', 'Jan Van Dyck')->sole();

        KnxPlanningAssignment::updateOrCreate(
            ['employee_id' => $employee->getKey(), 'date' => now()->toDateString()],
            ['project_id' => $this->project()->getKey()],
        );
    }

    public function test_a_racing_retry_answers_the_winner_instead_of_failing(): void
    {
        $this->planToday();
        $this->actingAs($this->user(), 'sanctum');

        $project = $this->project();

        // ---------------------------------------------------------------- visits (V11.e)
        $winner = null;

        KnxVisit::creating(function (KnxVisit $visit) use (&$winner, $project): void {
            // The competitor wins between our read and our write: exactly the gap.
            $winner = DB::connection('competitor')->table('knx_visits')->insertGetId([
                'organization_id' => $this->organizationId(),
                'project_id' => $project->getKey(),
                'client_id' => $visit->client_id,
                'type' => $visit->type,
                'work_done' => $visit->work_done,
                'minutes' => $visit->minutes,
                'captured_at' => $visit->captured_at?->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->postJson('/api/v1/knx/field/projects/C1618/visits', [
            'clientId' => 'aaaa0000-1111-4222-8333-000000000001',
            'projectCode' => 'C1618',
            'room' => 'Vergaderzaal',
            'type' => 'visit_end',
            'workDone' => 'Gewerkt.',
            'capturedAt' => now()->subHour()->toIso8601String(),
        ])->assertOk()->json();

        KnxVisit::flushEventListeners();

        $this->assertSame((string) $winner, $response['id'], 'the answer is the winner’s row');
        $this->assertSame('aaaa0000-1111-4222-8333-000000000001', $response['clientId']);
        $this->assertSame(1, KnxVisit::query()->count(), 'the loser wrote nothing');

        // --------------------------------------------------------------- devices (V11.c)
        $winner = null;

        KnxDevice::creating(function (KnxDevice $device) use (&$winner, $project): void {
            $winner = DB::connection('competitor')->table('knx_devices')->insertGetId([
                'project_id' => $project->getKey(),
                'room_id' => $device->room_id,
                'board_id' => $device->board_id,
                'type' => $device->type,
                'address' => $device->address,
                'serial' => $device->serial,
                'source' => $device->source,
                'registered_by_employee_id' => $device->registered_by_employee_id,
                'registered_at' => $device->registered_at?->toDateTimeString(),
                'client_id' => $device->client_id,
                'captured_at' => $device->captured_at?->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->postJson('/api/v1/knx/field/projects/C1618/devices', [
            'clientId' => 'aaaa0000-1111-4222-8333-000000000002',
            'projectCode' => 'C1618',
            'address' => '1.1.150',
            'type' => 'Drukknop 4-voudig',
            'roomId' => (string) $project->rooms()->where('name', 'Vergaderzaal')->sole()->getKey(),
            'boardCode' => 'E10',
            'serial' => '00B2-4471',
            'capturedAt' => now()->subHour()->toIso8601String(),
        ])->assertOk()->json();

        KnxDevice::flushEventListeners();

        $this->assertSame((string) $winner, $response['id']);
        $this->assertSame(1, KnxDevice::query()->where('client_id', 'aaaa0000-1111-4222-8333-000000000002')->count());
        // A lost race is a replay, not a collision: the answer is a device, never a 409.
        $this->assertArrayNotHasKey('existing', $response);

        // ---------------------------------------------------------------- issues (V11.d)
        $winner = null;

        KnxConflict::creating(function (KnxConflict $conflict) use (&$winner, $project): void {
            $winner = DB::connection('competitor')->table('knx_conflicts')->insertGetId([
                'organization_id' => $this->organizationId(),
                'project_id' => $project->getKey(),
                'client_id' => $conflict->client_id,
                'severity' => $conflict->severity,
                'type' => $conflict->type,
                'address' => $conflict->address,
                'device_existing' => $conflict->device_existing,
                'device_field' => $conflict->device_field,
                'note' => $conflict->note,
                'status' => $conflict->status,
                'reported_at' => $conflict->reported_at?->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->postJson('/api/v1/knx/field/projects/C1618/issues', [
            'clientId' => 'aaaa0000-1111-4222-8333-000000000003',
            'projectCode' => 'C1618',
            'room' => 'Vergaderzaal',
            'deviceAddress' => '1.1.111',
            'kind' => 'damaged',
            'note' => 'Afdekraam gebarsten.',
            'capturedAt' => now()->subHour()->toIso8601String(),
        ])->assertOk()->json();

        KnxConflict::flushEventListeners();

        $this->assertSame((string) $winner, $response['id']);
        $this->assertSame('aaaa0000-1111-4222-8333-000000000003', $response['clientId']);
        $this->assertSame(1, KnxConflict::query()->where('client_id', 'aaaa0000-1111-4222-8333-000000000003')->count());
    }
}
