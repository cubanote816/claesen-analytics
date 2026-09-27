<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxAcceptanceTest;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Models\KnxVisit;
use Modules\Knx\Models\KnxVisitItem;
use Tests\TestCase;

/**
 * V11.e — closing a visit from site (CLA-609).
 *
 * The three phases are what roadmap §3.1 is about: today a visit is signed off with a
 * progress number and an incident, and the point of the change is that "I finished the
 * day", "I handed over part of the scope" and "the client accepted it" stop being the
 * same act.
 *
 * What a closure must NOT do is just as important as what it must: it does not flip the
 * project's status, it does not turn a free-text pending list into conflicts, and it
 * does not close acceptance tests. Those are office decisions about the office's own
 * records, and there is a test for each.
 */
final class KnxFieldVisitTest extends TestCase
{
    use RefreshDatabase {
        beginDatabaseTransaction as traitBeginDatabaseTransaction;
    }

    private const CLIENT_ID = 'feed0000-1111-4222-8333-444455556666';

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);
    }

    /**
     * The insert-race tests stage a retry whose row commits while this class's
     * wrapping test transaction is already open, and the service's recovery read
     * happens inside that transaction. Under MySQL's default REPEATABLE READ that
     * read would replay a snapshot taken before the winner existed and the race
     * recovery could not be tested behind RefreshDatabase at all. Everything here
     * is single-threaded, so READ COMMITTED changes nothing else.
     *
     * The statement belongs here and not earlier: migrate:fresh purges the
     * connection, so a setting applied before it would be lost.
     */
    public function beginDatabaseTransaction()
    {
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');

        $this->traitBeginDatabaseTransaction();
    }

    private function fieldUser(string $email = 'jan.van.dyck@electrobertels.be'): User
    {
        return User::query()->where('email', $email)->sole();
    }

    private function officeUser(): User
    {
        return User::query()->where('email', 'lien.smet@electrobertels.be')->sole();
    }

    private function project(string $code = 'C1618'): KnxProject
    {
        return KnxProject::query()->where('code', $code)->sole();
    }

    /** The fixture plans Monday to Friday; today needs a row of its own. */
    private function planToday(string $technician, string $code = 'C1618'): void
    {
        $employee = KnxEmployee::query()->where('name', $technician)->sole();

        KnxPlanningAssignment::updateOrCreate(
            ['employee_id' => $employee->getKey(), 'date' => now()->toDateString()],
            ['project_id' => $this->project($code)->getKey()],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'clientId' => self::CLIENT_ID,
            'projectCode' => 'C1618',
            'projectName' => 'UV Campus · Gelijkvloers',
            'room' => 'Vergaderzaal',
            'type' => 'visit_end',
            'workDone' => 'Alle toestellen van de vergaderzaal geregistreerd.',
            'minutes' => 180,
            'pending' => ['DALI-driver vergaderzaal'],
            'reservations' => [],
            'verifiedFunctions' => [],
            'documents' => [],
            'signedBy' => null,
            'capturedAt' => now()->subHours(2)->toIso8601String(),
        ], $overrides);
    }

    private function close(array $overrides = [], string $code = 'C1618')
    {
        return $this->postJson("/api/v1/knx/field/projects/{$code}/visits", $this->payload($overrides));
    }

    private function storedVisit(string $clientId = self::CLIENT_ID): KnxVisit
    {
        return KnxVisit::query()->where('client_id', $clientId)->sole();
    }

    /**
     * The losing side of an insert race: a concurrent retry with the same
     * `clientId` whose row commits between our pre-check read and our INSERT.
     *
     * The race is staged where the database feels it, not mocked: the winner is
     * written through a second connection as its own committed transaction, the
     * service's pre-check genuinely runs and genuinely misses, and the INSERT
     * then fails on the real unique index with a real duplicate-key error.
     *
     * @param  array<string, mixed>  $row
     */
    private function aConcurrentRetryWinsFirst(array $row): void
    {
        config(['database.connections.knx_race' => config('database.connections.mysql')]);

        $dispatcher = KnxVisit::getEventDispatcher();
        $event = 'eloquent.creating: '.KnxVisit::class;

        // A model listener is global to the process, so this one has to be removed
        // again or it would stage the same collision inside every later test that
        // creates a closure — the other race test first of all. One shot on purpose:
        // after the winner's insert the collision is staged, and firing again would
        // try to insert the same `clientId` twice.
        $dispatcher->listen($event, function () use ($row, $dispatcher, $event): void {
            $dispatcher->forget($event);

            // The wrapping test transaction holds exclusive locks on the parent
            // rows (the seeder created them on the same connection), so the
            // winner's insert could never take its foreign-key share locks
            // without waiting forever. The values below are valid on purpose;
            // only the lock is unwanted.
            $race = DB::connection('knx_race');
            $race->statement('SET FOREIGN_KEY_CHECKS=0');
            $race->table('knx_visits')->insert($row);
        });

        // The winner commits outside RefreshDatabase's wrapping transaction, so
        // only an explicit delete keeps it from surviving into the next tests. The
        // listener is dropped here too, in case a test never reached the insert.
        $this->beforeApplicationDestroyed(function () use ($dispatcher, $event): void {
            $dispatcher->forget($event);
            DB::connection('knx_race')->table('knx_visits')->where('client_id', self::CLIENT_ID)->delete();
        });
    }

    public function test_closing_a_visit_returns_the_contract_shape_and_creates_it(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $capturedAt = now()->subHours(2);

        $payload = $this->close(['capturedAt' => $capturedAt->toIso8601String()])
            ->assertCreated()
            ->json();

        // The contract's CloseVisitResult: the id and the app's own key.
        $this->assertSame(['id', 'clientId'], array_keys($payload));

        $visit = $this->storedVisit();

        $this->assertSame('visit_end', $visit->type);
        $this->assertSame('Alle toestellen van de vergaderzaal geregistreerd.', $visit->work_done);
        $this->assertSame(180, $visit->minutes);
        $this->assertSame('Jan Van Dyck', $visit->closedBy->name);
        $this->assertSame($capturedAt->format('Y-m-d H:i'), $visit->captured_at->format('Y-m-d H:i'));

        // The carried-over item is kept, with the order it was written in.
        $this->assertSame(['DALI-driver vergaderzaal'], $visit->itemsOfKind(KnxVisit::ITEM_PENDING));
    }

    public function test_the_location_resolves_to_the_offices_own_room_when_the_name_matches(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->close()->assertCreated();

        $visit = $this->storedVisit();
        $room = KnxProjectRoom::query()
            ->where('project_id', $this->project()->getKey())
            ->where('name', 'Vergaderzaal')
            ->sole();

        // The room row is the authority when it exists, so the label is not stored
        // twice: two copies of a name are two things that can disagree.
        $this->assertSame($room->getKey(), $visit->room_id);
        $this->assertNull($visit->room_label);
        $this->assertSame('Vergaderzaal', $visit->location());
    }

    public function test_a_place_the_office_has_not_modelled_keeps_the_technicians_own_word(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->close(['room' => 'Zolder boven de keuken'])->assertCreated();

        $visit = $this->storedVisit();

        // Nothing the technician saw is dropped, and no room is invented for it.
        $this->assertNull($visit->room_id);
        $this->assertSame('Zolder boven de keuken', $visit->room_label);
        $this->assertSame('Zolder boven de keuken', $visit->location());
    }

    public function test_a_final_acceptance_stores_its_own_lists_in_order(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->close([
            'type' => 'final',
            'pending' => [],
            'reservations' => ['DALI-driver vergaderzaal', 'Kabelgoot zaal 1.02'],
            'verifiedFunctions' => ['Verlichting vergaderzaal', 'Aanwezigheid gang'],
            'documents' => ['Installatiedossier', 'ETS-export'],
            'signedBy' => 'D. Maes',
        ])->assertCreated();

        $visit = $this->storedVisit();

        $this->assertSame('final', $visit->type);
        $this->assertSame('D. Maes', $visit->signed_by);

        // Order matters: a list read back in another order is a different list to the
        // person who wrote it.
        $this->assertSame(
            ['DALI-driver vergaderzaal', 'Kabelgoot zaal 1.02'],
            $visit->itemsOfKind(KnxVisit::ITEM_RESERVATION),
        );
        $this->assertSame(
            ['Verlichting vergaderzaal', 'Aanwezigheid gang'],
            $visit->itemsOfKind(KnxVisit::ITEM_VERIFIED_FUNCTION),
        );
        $this->assertSame(
            ['Installatiedossier', 'ETS-export'],
            $visit->itemsOfKind(KnxVisit::ITEM_DOCUMENT),
        );
        $this->assertSame([], $visit->itemsOfKind(KnxVisit::ITEM_PENDING));
    }

    public function test_a_closure_with_no_reported_time_is_still_a_closure(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->close(['minutes' => null, 'type' => 'partial'])->assertCreated();

        // `minutes` is nullable in the contract, and a closure nobody timed is a real
        // closure: the hours report shows it with empty time instead of losing it.
        $this->assertNull($this->storedVisit()->minutes);
    }

    public function test_the_same_client_id_never_closes_the_visit_twice(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $first = $this->close()->assertCreated()->json();

        $itemsBefore = KnxVisitItem::query()->count();

        $second = $this->close(['workDone' => 'Een tweede poging met andere woorden'])->assertOk()->json();

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, KnxVisit::query()->count());
        // The lists are not written a second time either.
        $this->assertSame($itemsBefore, KnxVisitItem::query()->count());
        // Nor is the stored report replaced by what the retry happened to carry.
        $this->assertSame('Alle toestellen van de vergaderzaal geregistreerd.', $this->storedVisit()->work_done);
    }

    public function test_a_client_id_reused_in_another_project_is_rejected(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');
        $this->close()->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->planToday('Mira Claes', '239870');
        $this->actingAs($this->fieldUser('mira.claes@electrobertels.be'), 'sanctum');

        $this->close(['projectCode' => '239870'], '239870')
            ->assertStatus(422)
            ->assertJsonPath('errors.clientId.0', __('knx::field.client_id_reused'));
    }

    public function test_a_retry_that_loses_the_insert_race_answers_with_the_row_that_won(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $project = $this->project();
        $itemsBefore = KnxVisitItem::query()->count();

        $this->aConcurrentRetryWinsFirst([
            'organization_id' => $project->organization_id,
            'project_id' => $project->getKey(),
            'client_id' => self::CLIENT_ID,
            'type' => 'visit_end',
            'work_done' => 'Written by the request that won the race.',
            'captured_at' => now()->toDateTimeString(),
        ]);

        // The field app queues closures offline and retries by design, so the
        // retry that loses the race is a replay: 200 with the winning row, the
        // same answer an ordinary second request gets — never a 500.
        $response = $this->close();
        $payload = $response->assertOk()->json();
        $this->assertSame(['id', 'clientId'], array_keys($payload));

        $this->assertSame(1, KnxVisit::query()->where('client_id', self::CLIENT_ID)->count());
        // The loser's own write was rolled back: its lists were not stored either.
        $this->assertSame($itemsBefore, KnxVisitItem::query()->count());
        $this->assertSame('Written by the request that won the race.', $this->storedVisit()->work_done);
    }

    public function test_a_retry_that_won_the_race_in_another_project_is_rejected(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $other = KnxProject::query()->where('code', '239870')->sole();
        $this->aConcurrentRetryWinsFirst([
            'organization_id' => $other->organization_id,
            'project_id' => $other->getKey(),
            'client_id' => self::CLIENT_ID,
            'type' => 'visit_end',
            'work_done' => 'Written by a closure for another project.',
            'captured_at' => now()->toDateTimeString(),
        ]);

        // Recovery shares the ordinary replay's guard: a reused key must not hand
        // one project's closure to a request about another, race or no race.
        $this->close()
            ->assertStatus(422)
            ->assertJsonPath('errors.clientId.0', __('knx::field.client_id_reused'));
    }

    public function test_closing_a_visit_changes_nothing_else_in_the_office_records(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $project = $this->project();
        $statusBefore = $project->status;
        $conflictsBefore = KnxConflict::query()->count();
        $testsBefore = KnxAcceptanceTest::query()->pluck('status', 'id')->all();

        // A `final` with verified functions and pending items would be the tempting
        // place to "help" the office: close the project, open conflicts, mark the tests.
        $this->close([
            'type' => 'final',
            'pending' => ['DALI-driver vergaderzaal'],
            'verifiedFunctions' => ['Verlichting vergaderzaal'],
            'documents' => ['Installatiedossier'],
            'signedBy' => 'D. Maes',
        ])->assertCreated();

        $this->assertSame($statusBefore, $project->refresh()->status, 'the project status is not ours to change');
        $this->assertSame($conflictsBefore, KnxConflict::query()->count(), 'a free-text list does not open conflicts');
        $this->assertSame($testsBefore, KnxAcceptanceTest::query()->pluck('status', 'id')->all(), 'verified functions are labels, not test results');
    }

    public function test_the_office_reads_the_closures_of_a_project_newest_first(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->close(['capturedAt' => now()->subDays(2)->toIso8601String()])->assertCreated();
        $this->close([
            'clientId' => 'feed0000-2222-4333-8444-444455556666',
            'type' => 'final',
            'minutes' => 60,
            'pending' => [],
            'verifiedFunctions' => ['Verlichting vergaderzaal'],
            'capturedAt' => now()->subHours(1)->toIso8601String(),
        ])->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->officeUser(), 'sanctum');

        $visits = $this->getJson('/api/v1/knx/projects/C1618/visits')->assertOk()->json();

        $this->assertCount(2, $visits);
        $this->assertSame(
            ['id', 'projectCode', 'type', 'room', 'workDone', 'minutes', 'signedBy', 'capturedAt', 'closedBy', 'pending', 'reservations', 'verifiedFunctions', 'documents'],
            array_keys($visits[0]),
        );

        // Newest first, which is how the office reads a history.
        $this->assertSame('final', $visits[0]['type']);
        $this->assertSame('visit_end', $visits[1]['type']);

        $this->assertSame('Vergaderzaal', $visits[0]['room']);
        $this->assertSame(60, $visits[0]['minutes']);
        $this->assertSame(['Verlichting vergaderzaal'], $visits[0]['verifiedFunctions']);
        $this->assertSame('J. Van Dyck', $visits[0]['closedBy']);
        $this->assertSame(['DALI-driver vergaderzaal'], $visits[1]['pending']);
    }

    public function test_the_visit_history_belongs_to_the_office_app_only(): void
    {
        $this->actingAs($this->fieldUser(), 'sanctum');

        // The field app sends closures and never reads them back.
        $this->getJson('/api/v1/knx/projects/C1618/visits')->assertUnauthorized();
    }

    public function test_a_project_code_that_does_not_match_the_url_is_rejected(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->close(['projectCode' => '239870'])
            ->assertStatus(422)
            ->assertJsonPath('errors.projectCode.0', __('knx::field.project_mismatch'));
    }

    public function test_a_type_outside_the_contract_is_a_field_error(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        // The three phases are the contract; a fourth is not a phase.
        $this->close(['type' => 'afscheid'])->assertStatus(422)->assertJsonStructure(['errors' => ['type']]);
    }

    public function test_a_visit_cannot_be_closed_in_a_project_that_is_not_mine_today(): void
    {
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->close(['projectCode' => '239870'], '239870')->assertForbidden();

        $this->assertSame(0, KnxVisit::query()->count());
    }

    public function test_an_unknown_project_code_is_not_found(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        // Scope runs before validation: an unknown code is a 404 with the module's
        // envelope, not a mismatch error about the body's projectCode.
        $this->close([], 'ZZ999')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->assertSame(0, KnxVisit::query()->count());
    }

    public function test_closing_a_visit_requires_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->close()->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
