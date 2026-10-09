<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * K6 — the Conflictencentrum (docs/BACKEND-API.md §4.5).
 *
 * Two rules carry the module:
 *   - the history is appended on every status change (it is the ETS worklist);
 *   - a proposal may not collide with an address already in use (409), while a
 *     conflict that already coincides with a device address must still be able to
 *     move through the workflow.
 */
final class KnxConflictsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze the clock mid-morning. This is the exact window that used to flip the
        // two critical conflicts (08:15 and 09:42): the seeded day-0 rows have to keep
        // their relative order at any hour, and pinning it here makes that a real
        // regression test rather than a wall-clock accident.
        Carbon::setTestNow(Carbon::parse('2026-10-08 08:30:00', 'Europe/Brussels'));

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function conflict(string $address): KnxConflict
    {
        return KnxConflict::query()->where('address', $address)->sole();
    }

    public function test_the_list_has_the_contract_shape_and_the_severity_order(): void
    {
        $conflicts = $this->getJson('/api/v1/knx/conflicts')->assertOk()->json();

        $this->assertCount(5, $conflicts);
        $this->assertSame(
            ['id', 'lifecycle', 'severity', 'type', 'address', 'projectCode', 'projectName', 'deviceExisting', 'deviceField',
                'reportedBy', 'reportedAt', 'note', 'photoUrl', 'status', 'symptom', 'proposal', 'takenAddresses', 'history'],
            array_keys($conflicts[0]),
        );

        // Rows raised by office/field reporting before KNX-56 are ETS conflicts.
        $this->assertSame('ets', $conflicts[0]['lifecycle']);
        $this->assertNull($conflicts[0]['symptom']);

        // critical (most recent first), then warning, then info.
        $this->assertSame(
            ['1.1.116', '1.1.108', '1.2.044', '1.2.021', '1.1.121'],
            array_column($conflicts, 'address'),
        );

        $this->assertSame(['critical', 'critical', 'warning', 'warning', 'info'], array_column($conflicts, 'severity'));
        $this->assertSame('J. Van Dyck', $conflicts[0]['reportedBy']);
        $this->assertNull($conflicts[0]['photoUrl']);
        $this->assertSame([['at' => $conflicts[0]['reportedAt'], 'action' => 'reported']], $conflicts[0]['history']);
    }

    public function test_the_list_filters_by_status_project_and_term(): void
    {
        // `active` is the office working set: open + in_review, not a stored status.
        $this->getJson('/api/v1/knx/conflicts?status=active')->assertOk()->assertJsonCount(4);
        $this->getJson('/api/v1/knx/conflicts?status=open')->assertOk()->assertJsonCount(3);
        $this->getJson('/api/v1/knx/conflicts?status=verified')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/knx/conflicts?status=all')->assertOk()->assertJsonCount(5);

        $this->getJson('/api/v1/knx/conflicts?project=C1618')->assertOk()->assertJsonCount(3);
        $this->getJson('/api/v1/knx/conflicts?project=NOPE')->assertOk()->assertJsonCount(0);

        $this->getJson('/api/v1/knx/conflicts?q=1.2.021')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/v1/knx/conflicts?q=Thermostaat')->assertOk()->assertJsonCount(1);

        $this->getJson('/api/v1/knx/conflicts?status=onzin')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_the_taken_addresses_are_the_projects_apparatuses_plus_the_other_proposals(): void
    {
        $conflict = $this->getJson('/api/v1/knx/conflicts?project=C1618&q=1.1.116')->json()[0];
        $taken = $conflict['takenAddresses'];

        // The project's devices are all taken…
        $this->assertContains('1.1.100', $taken);
        $this->assertContains('1.1.116', $taken);
        // …and so is the other conflict's proposal, but not this one's own.
        $this->assertContains('1.1.134', $taken);
        $this->assertNotContains('1.1.133', $taken);

        $this->assertSame($taken, array_values(array_unique($taken)));
    }

    public function test_a_status_change_is_appended_to_the_history(): void
    {
        $conflict = $this->conflict('1.1.116');

        $response = $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'in_review'])->assertOk();

        $this->assertSame('in_review', $response->json('status'));

        $history = $response->json('history');

        $this->assertCount(2, $history);
        $this->assertSame('reported', $history[0]['action']);
        // The line the office imports into ETS records the address in play.
        $this->assertSame('in_review', $history[1]['action']);
        $this->assertSame('1.1.133', $history[1]['address']);
    }

    public function test_sending_the_same_status_does_not_pollute_the_history(): void
    {
        $conflict = $this->conflict('1.1.116');

        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'in_review'])->assertOk();
        $response = $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'in_review'])->assertOk();

        $this->assertCount(2, $response->json('history'), 'a no-op must not add a worklist line');

        // The front sends the proposal along with the status in its "next step"
        // action; that must not add a line either.
        $response = $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['proposal' => '1.1.133'])->assertOk();

        $this->assertCount(2, $response->json('history'));
    }

    public function test_proposing_an_address_that_is_in_use_is_a_409(): void
    {
        $conflict = $this->conflict('1.1.116');

        // 1.1.134 is the proposal of another conflict still holding it.
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['proposal' => '1.1.134'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'address_in_use')
            ->assertJsonStructure(['message', 'code', 'errors' => ['proposal']]);

        // 1.1.100 is a registered apparatus.
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['proposal' => '1.1.100'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'address_in_use');

        // Nothing was written.
        $this->assertSame('1.1.133', $conflict->fresh()->proposal);
    }

    public function test_a_free_address_can_be_proposed_and_the_status_can_move_along_with_it(): void
    {
        $conflict = $this->conflict('1.1.116');

        $response = $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", [
            'status' => 'ets_listed',
            'proposal' => '1.1.140',
        ])->assertOk();

        $this->assertSame('ets_listed', $response->json('status'));
        $this->assertSame('1.1.140', $response->json('proposal'));

        $history = $response->json('history');

        $this->assertCount(2, $history);
        $this->assertSame('1.1.140', $history[1]['address'], 'the worklist line records the NEW address');

        // A conflict never sees its own proposal as taken — otherwise it could
        // never change it — so its own list carries neither address…
        $taken = $response->json('takenAddresses');

        $this->assertNotContains('1.1.140', $taken);
        $this->assertNotContains('1.1.133', $taken);

        // …while another conflict of the same project does see the new claim, and
        // the old one is free again.
        $other = $this->getJson('/api/v1/knx/conflicts?project=C1618&q=1.1.108')->json()[0];

        $this->assertContains('1.1.140', $other['takenAddresses']);
        $this->assertNotContains('1.1.133', $other['takenAddresses']);
    }

    public function test_a_conflict_whose_address_is_already_a_device_can_still_move(): void
    {
        // The fixture has one: 1.2.021 is both a registered apparatus and the
        // address/proposal of this conflict. Validating a proposal that does not
        // change anything would freeze it forever.
        $conflict = $this->conflict('1.2.021');

        $this->assertSame('1.2.021', $conflict->proposal);

        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'ets_listed'])->assertOk()->assertJsonPath('status', 'ets_listed');
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['proposal' => '1.2.021'])->assertOk()->assertJsonPath('proposal', '1.2.021');
    }

    public function test_the_whole_workflow_is_recorded_and_reversible(): void
    {
        $conflict = $this->conflict('1.1.116');
        $steps = ['in_review', 'ets_listed', 'applied', 'downloaded', 'verified', 'closed'];

        foreach ($steps as $status) {
            $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => $status])->assertOk();
        }

        // `reported` is the first line of every conflict; the workflow follows.
        $this->assertSame(['reported', ...$steps], array_column(
            $this->getJson("/api/v1/knx/conflicts/{$conflict->id}")->json('history'),
            'action',
        ));

        // No strict state machine: a mis-click must be undoable, and the undo is
        // recorded too.
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'open'])->assertOk();
        $this->assertSame('open', $conflict->fresh()->status);
    }

    public function test_a_closed_conflict_releases_its_proposed_address(): void
    {
        $keeper = $this->conflict('1.1.108');   // proposes 1.1.134
        $other = $this->conflict('1.1.116');

        $this->assertContains('1.1.134', $this->getJson("/api/v1/knx/conflicts/{$other->id}")->json('takenAddresses'));

        $this->patchJson("/api/v1/knx/conflicts/{$keeper->id}", ['status' => 'closed'])->assertOk();

        $this->assertNotContains('1.1.134', $this->getJson("/api/v1/knx/conflicts/{$other->id}")->json('takenAddresses'));

        // …and can now be proposed.
        $this->patchJson("/api/v1/knx/conflicts/{$other->id}", ['proposal' => '1.1.134'])->assertOk();
    }

    public function test_an_unknown_conflict_is_a_404_with_the_contract_envelope(): void
    {
        $this->getJson('/api/v1/knx/conflicts/999999')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->patchJson('/api/v1/knx/conflicts/999999', ['status' => 'open'])->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_the_conflict_endpoints_require_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/conflicts')->assertUnauthorized();
        $this->getJson('/api/v1/knx/conflicts/1')->assertUnauthorized();
        $this->patchJson('/api/v1/knx/conflicts/1', [])->assertUnauthorized();
    }

    private function project(string $code = 'C1618'): KnxProject
    {
        return KnxProject::query()->where('code', $code)->sole();
    }

    private function malfunction(string $address = '1.9.010'): KnxConflict
    {
        return KnxConflict::factory()->malfunction()->create([
            'project_id' => $this->project()->getKey(),
            'address' => $address,
        ]);
    }

    public function test_a_malfunction_walks_its_validated_cycle(): void
    {
        $conflict = $this->malfunction();

        // The contract's shape carries the lifecycle and the symptom.
        $this->getJson("/api/v1/knx/conflicts/{$conflict->id}")
            ->assertOk()
            ->assertJsonPath('lifecycle', 'malfunction')
            ->assertJsonPath('symptom', 'Gaat niet aan als de aanwezigheidsmelder aanslaat.');

        // Skipping the diagnosis is refused, in the field-error envelope.
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'resolved'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'code', 'errors' => ['status']]);

        $this->assertSame('open', $conflict->fresh()->status, 'a refused step writes nothing');

        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'diagnosing'])->assertOk();
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'resolved', 'note' => 'Kring doorgemeten.'])->assertOk();
        $response = $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'verified'])->assertOk();

        $this->assertSame('verified', $response->json('status'));

        $history = $response->json('history');

        $this->assertSame(['diagnosing', 'resolved', 'verified'], array_column($history, 'action'));
        // Who took the step, and the note they left behind.
        $this->assertSame('L. Smet', $history[0]['by']);
        $this->assertSame('Kring doorgemeten.', $history[1]['note']);

        // The step is a typed notice (KNX-54); it goes to the digest, not immediate.
        // One per step: the fixture's own notices are not touched.
        $notice = KnxNotification::query()
            ->where('kind', KnxNotification::KIND_MALFUNCTION_STATUS_CHANGED)
            ->where('summary', 'resolved → verified')
            ->sole();
        $this->assertSame('conflict', $notice->target_type);
        $this->assertSame((string) $conflict->id, $notice->target_id);
        $this->assertSame('resolved → verified', $notice->summary);
    }

    public function test_a_malfunction_can_be_rejected_from_any_live_state(): void
    {
        $conflict = $this->malfunction('1.9.011');

        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'diagnosing'])->assertOk();
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'rejected'])->assertOk()
            ->assertJsonPath('status', 'rejected');

        // Rejected is terminal: there is no way back out of it.
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'open'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_an_ets_conflict_keeps_its_free_and_reversible_cycle(): void
    {
        // The fixture's own conflict; ETS has no machine, so a jump straight to `closed`
        // (and back) is accepted — the office may undo a mis-click.
        $conflict = $this->conflict('1.1.116');

        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'closed'])->assertOk();
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'open'])->assertOk();

        // …but a malfunction-only state is not an ETS state.
        $this->patchJson("/api/v1/knx/conflicts/{$conflict->id}", ['status' => 'diagnosing'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_the_list_filters_by_lifecycle(): void
    {
        $this->malfunction('1.9.012');

        $this->getJson('/api/v1/knx/conflicts?lifecycle=malfunction')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.lifecycle', 'malfunction');

        $this->getJson('/api/v1/knx/conflicts?lifecycle=ets')->assertOk()->assertJsonCount(5);

        $this->getJson('/api/v1/knx/conflicts?lifecycle=onzin')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lifecycle']]);
    }
}
