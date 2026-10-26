<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxAcceptanceTest;
use Modules\Knx\Models\KnxFunctionSpec;
use Modules\Knx\Models\KnxTestExecution;
use Tests\TestCase;

/**
 * K9 — functional specs and acceptance tests (BACKEND-API-ZONES.md §2 and §3).
 *
 * The rules that matter are all about not losing what happened: a failure without a
 * reason is unusable at delivery, a failure must leave an issue behind, and
 * re-running a corrected test must not erase the earlier failure from the evidence.
 */
final class KnxFunctionsAndTestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );
    }

    public function test_the_function_list_has_the_contract_shape(): void
    {
        $specs = $this->getJson('/api/v1/knx/projects/C1618/functions')->assertOk()->json();

        $this->assertCount(3, $specs);
        $this->assertSame(
            ['id', 'projectCode', 'zoneId', 'zoneName', 'name', 'objective', 'triggers', 'conditions', 'manualControls',
                'automations', 'timings', 'priorities', 'failureBehaviour', 'dependencies', 'acceptanceCriteria',
                'groupAddresses', 'dpts', 'knxObjects', 'status', 'version', 'author', 'approvedBy', 'approvedAt', 'updatedAt'],
            array_keys($specs[0]),
        );

        $lighting = collect($specs)->firstWhere('name', 'Verlichting vergaderzaal');

        // The agreed behaviour, straight from the fixture.
        $this->assertSame('approved', $lighting['status']);
        $this->assertSame(3, $lighting['version']);
        $this->assertSame('Vergaderzaal', $lighting['zoneName']);
        $this->assertSame('L. Smet', $lighting['author']);
        $this->assertSame('L. Smet', $lighting['approvedBy']);
        $this->assertIsArray($lighting['triggers']);
        $this->assertContains('Aanwezigheidsdetector', $lighting['triggers']);
        $this->assertContains('1/1/10', $lighting['groupAddresses']);
        $this->assertContains('DPT 1.001', $lighting['dpts']);

        // An empty list field is an array, never null: the front maps over it.
        $this->assertSame([], collect($specs)->firstWhere('name', 'Zonwering zuidgevel')['groupAddresses']);
    }

    public function test_changing_a_function_status_marks_who_approved_it(): void
    {
        $spec = KnxFunctionSpec::query()->where('name', 'Aanwezigheid + verlichting gang')->sole();
        $this->assertSame('proposed', $spec->status);
        $this->assertNull($spec->approved_at);

        $response = $this->patchJson("/api/v1/knx/functions/{$spec->getKey()}", ['status' => 'approved'])->assertOk();

        $this->assertSame('approved', $response->json('status'));
        $this->assertSame('L. Smet', $response->json('approvedBy'));
        $this->assertNotNull($response->json('approvedAt'));

        // Un-approving takes the name and the date away: "approved by X" on a draft
        // would be misleading.
        $response = $this->patchJson("/api/v1/knx/functions/{$spec->getKey()}", ['status' => 'changed'])->assertOk();

        $this->assertSame('changed', $response->json('status'));
        $this->assertNull($response->json('approvedBy'));
        $this->assertNull($response->json('approvedAt'));

        $this->patchJson("/api/v1/knx/functions/{$spec->getKey()}", ['status' => 'onzin'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);

        $this->patchJson('/api/v1/knx/functions/999999', ['status' => 'draft'])->assertNotFound();
    }

    public function test_the_test_list_has_the_contract_shape(): void
    {
        $tests = $this->getJson('/api/v1/knx/projects/C1618/tests')->assertOk()->json();

        $this->assertCount(5, $tests);
        $this->assertSame(
            ['id', 'projectCode', 'functionId', 'functionName', 'zoneName', 'action', 'expected', 'observed',
                'status', 'note', 'physicalCheck', 'executor', 'executedAt', 'evidenceUrls', 'issueId'],
            array_keys($tests[0]),
        );

        // A test knows its function and, through it, its zone: that is what lets the
        // office say "verified against which agreed behaviour".
        $this->assertSame('Verlichting vergaderzaal', $tests[0]['functionName']);
        $this->assertSame('Vergaderzaal', $tests[0]['zoneName']);
        $this->assertTrue($tests[0]['physicalCheck']);
        $this->assertSame([], $tests[0]['evidenceUrls']);

        $failed = collect($tests)->firstWhere('status', 'failed');

        $this->assertNotNull($failed);
        $this->assertNotNull($failed['issueId'], 'the fixture keeps the issue a failure opened');
        $this->assertSame('S. Wouters', $failed['executor']);
    }

    public function test_recorded_results_require_a_reason_and_leave_an_issue(): void
    {
        $test = KnxAcceptanceTest::query()->where('status', 'pending')->firstOrFail();

        foreach (['failed', 'blocked', 'not_applicable'] as $status) {
            $this->patchJson("/api/v1/knx/tests/{$test->getKey()}", ['status' => $status])
                ->assertStatus(422)
                ->assertJsonStructure(['errors' => ['note']]);
        }

        $response = $this->patchJson("/api/v1/knx/tests/{$test->getKey()}", [
            'status' => 'failed',
            'observed' => 'Licht gaat niet aan',
            'note' => 'DALI-driver ontbreekt',
        ])->assertOk();

        $this->assertSame('failed', $response->json('status'));
        $this->assertSame('L. Smet', $response->json('executor'));
        $this->assertNotNull($response->json('executedAt'));
        // The failure opened an issue, without losing the context: the test already
        // knows its function, its zone and the action that failed.
        $this->assertNotNull($response->json('issueId'));
        $this->assertStringStartsWith('iss-', $response->json('issueId'));
    }

    public function test_an_existing_issue_is_never_overwritten(): void
    {
        $failed = KnxAcceptanceTest::query()->where('status', 'failed')->sole();
        $original = $failed->issue_id;

        $this->patchJson("/api/v1/knx/tests/{$failed->getKey()}", [
            'status' => 'failed',
            'note' => 'Nog steeds hetzelfde probleem',
        ])->assertOk()->assertJsonPath('issueId', $original);
    }

    public function test_re_running_a_corrected_test_keeps_the_earlier_failure(): void
    {
        $test = KnxAcceptanceTest::query()->where('status', 'pending')->firstOrFail();
        $this->assertSame(0, KnxTestExecution::query()->count());

        $this->patchJson("/api/v1/knx/tests/{$test->getKey()}", [
            'status' => 'failed',
            'observed' => 'Geen reactie',
            'note' => 'Driver ontbreekt',
        ])->assertOk();

        $this->patchJson("/api/v1/knx/tests/{$test->getKey()}", [
            'status' => 'passed',
            'observed' => 'Licht aan binnen 1 s',
            'note' => 'Na plaatsing driver',
        ])->assertOk();

        // Two executions, in order, and the failure is still there: §3.2 forbids
        // overwriting it, and that history is part of the delivery evidence.
        $executions = KnxTestExecution::query()->orderBy('executed_at')->orderBy('id')->get();

        $this->assertCount(2, $executions);
        $this->assertSame(['failed', 'passed'], $executions->pluck('status')->all());
        $this->assertSame('Geen reactie', $executions->first()->observed);
        $this->assertSame('Driver ontbreekt', $executions->first()->note);

        // The test row itself carries the latest state.
        $this->assertSame('passed', $test->fresh()->status);
    }

    public function test_a_status_only_patch_does_not_invent_an_execution(): void
    {
        // Moving back to `pending` is a correction of the workflow, not a run: it
        // must not add evidence that never happened.
        $test = KnxAcceptanceTest::query()->where('status', 'pending')->firstOrFail();

        $this->patchJson("/api/v1/knx/tests/{$test->getKey()}", ['observed' => 'Nog niet getest'])->assertOk();

        $this->assertSame(0, KnxTestExecution::query()->count());
    }

    public function test_the_endpoints_require_a_token_and_a_known_project(): void
    {
        $this->getJson('/api/v1/knx/projects/NOPE/functions')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->getJson('/api/v1/knx/projects/NOPE/tests')->assertNotFound()->assertJsonPath('code', 'not_found');

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/projects/C1618/functions')->assertUnauthorized();
        $this->getJson('/api/v1/knx/projects/C1618/tests')->assertUnauthorized();
        $this->patchJson('/api/v1/knx/tests/1', ['status' => 'passed'])->assertUnauthorized();
    }
}
