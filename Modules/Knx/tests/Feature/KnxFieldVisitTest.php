<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

    private const CLIENT_ID = 'feed0000-1111-4222-8333-444455556666';

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);
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

        $second = $this->close()->assertOk()->json();

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, KnxVisit::query()->count());
        // The lists are not written a second time either.
        $this->assertSame($itemsBefore, KnxVisitItem::query()->count());
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

    public function test_closing_a_visit_requires_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->close()->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
