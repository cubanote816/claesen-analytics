<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxAiSpendEntry;
use Modules\Knx\Models\KnxAiSpendSetting;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\AiSpend\AiSpendContext;
use Modules\Knx\Services\AiSpend\AiSpendLedger;
use Modules\Knx\Services\AiSpend\AiSpendRefused;
use Tests\TestCase;

/**
 * KNX administration of the daily USD limit: the summary, the override, reconciliation.
 */
final class KnxAiSpendAdminTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private KnxEmployee $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // 2026-01-15 00:30 in Brussels: the previous UTC day, the next budget day.
        Carbon::setTestNow(Carbon::parse('2026-01-14 23:30:00', 'UTC'));
        $this->organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        config([
            'knx.ai_spend.daily_limit_usd' => 5.00,
            'knx.ai_spend.timezone' => 'Europe/Brussels',
            'knx.ai_spend.max_calls_per_processing' => 50,
            'knx.ai_spend.max_cost_per_processing_usd' => 100.00,
        ]);
        $this->seed(KnxDemoSeeder::class);
        $this->admin = $this->employeeOf('lien.smet@electrobertels.be');
        $this->admin->forceFill(['knx_role' => 'admin'])->save();
        $this->actingAs(User::query()->where('email', 'lien.smet@electrobertels.be')->sole(), 'sanctum');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function employeeOf(string $email): KnxEmployee
    {
        return KnxEmployee::query()->where('user_id', User::query()->where('email', $email)->value('id'))->sole();
    }

    /** @param array<string, mixed> $attributes */
    private function entry(string $status, float $reserved, ?float $actual = null, string $day = '2026-01-15', array $attributes = [], ?int $orgId = null): KnxAiSpendEntry
    {
        return KnxAiSpendEntry::query()->create($attributes + [
            'organization_id' => $orgId ?? $this->organization->getKey(),
            'day' => $day,
            'provider' => 'openai',
            'operation' => 'plan_analysis',
            'model' => 'm-test',
            'status' => $status,
            'reserved_usd' => $reserved,
            'actual_usd' => $actual,
            'processing_id' => (string) Str::uuid(),
            'page' => 1,
            'request_id' => 'req_'.Str::random(6),
            'reason' => $status === 'uncertain' ? 'timeout' : null,
            'settled_at' => $status === 'settled' ? now() : null,
        ]);
    }

    private function seedDay(): void
    {
        $project = KnxProject::query()->where('code', 'C1618')->sole();
        $this->entry('settled', 0.50, 0.30, attributes: ['project_id' => $project->getKey()]);
        $this->entry('settled', 0.40, 0.20);
        $this->entry('reserved', 0.25);
        $this->entry('uncertain', 0.60);
        $this->entry('released', 0.90);
        // The previous Brussels day: never part of today, but its uncertain entry must be reconciled.
        $this->entry('settled', 3.00, 3.00, '2026-01-14');
        $this->entry('uncertain', 0.70, null, '2026-01-14');
    }

    private function context(): AiSpendContext
    {
        return new AiSpendContext($this->organization->getKey(), (string) Str::uuid(), null, null, 1, null);
    }

    public function test_the_admin_sees_the_exact_summary_of_the_brussels_day(): void
    {
        $this->seedDay();

        $json = $this->getJson('/api/v1/knx/ai-spend/today')->assertOk()->json();

        $this->assertSame([
            'day', 'timezone', 'currency', 'limitUsd', 'limitSource', 'spentUsd', 'pendingUsd',
            'uncertainUsd', 'availableUsd', 'resumesAt', 'entries', 'uncertain',
        ], array_keys($json));
        $this->assertSame('2026-01-15', $json['day']);
        $this->assertSame('Europe/Brussels', $json['timezone']);
        $this->assertSame('USD', $json['currency']);
        $this->assertEquals(5.0, $json['limitUsd']);
        $this->assertSame('config', $json['limitSource']);
        $this->assertEquals(0.5, $json['spentUsd']);
        $this->assertEquals(0.25, $json['pendingUsd']);
        $this->assertEquals(0.6, $json['uncertainUsd']);
        $this->assertEquals(3.65, $json['availableUsd']);
        $this->assertSame('2026-01-16T00:00:00+01:00', $json['resumesAt']);
        $this->assertCount(5, $json['entries']);
        $this->assertSame('released', $json['entries'][0]['status'] === 'released' ? 'released' : $json['entries'][0]['status']);
        $ids = array_column($json['entries'], 'id');
        $sorted = $ids;
        rsort($sorted, SORT_NUMERIC);
        $this->assertSame($sorted, $ids);
        $this->assertSame([
            'id', 'provider', 'operation', 'model', 'status', 'reservedUsd', 'actualUsd',
            'projectCode', 'page', 'requestId', 'reason', 'createdAt', 'settledAt',
        ], array_keys($json['entries'][0]));
        $this->assertContains('C1618', array_column($json['entries'], 'projectCode'));

        $this->assertCount(2, $json['uncertain']);
        $this->assertSame(['2026-01-14', '2026-01-15'], array_column($json['uncertain'], 'day') === ['2026-01-14', '2026-01-15'] ? ['2026-01-14', '2026-01-15'] : array_reverse(array_column($json['uncertain'], 'day')));
        $this->assertSame([...array_keys($json['entries'][0]), 'day'], array_keys($json['uncertain'][0]));
    }

    public function test_available_never_goes_below_zero(): void
    {
        $this->entry('settled', 9.0, 9.0);

        $this->getJson('/api/v1/knx/ai-spend/today')->assertOk()->assertJsonPath('availableUsd', 0);
    }

    public function test_the_entries_are_capped_at_one_hundred(): void
    {
        for ($i = 0; $i < 101; $i++) {
            $this->entry('released', 0.01);
        }

        $this->assertCount(100, $this->getJson('/api/v1/knx/ai-spend/today')->json('entries'));
    }

    public function test_a_non_admin_office_user_is_refused_everywhere(): void
    {
        $uncertain = $this->entry('uncertain', 0.5);
        $this->admin->forceFill(['knx_role' => 'planner'])->save();

        foreach ([
            ['getJson', '/api/v1/knx/ai-spend/today', []],
            ['putJson', '/api/v1/knx/ai-spend/limit', ['dailyLimitUsd' => 9]],
            ['deleteJson', '/api/v1/knx/ai-spend/limit', []],
            ['postJson', "/api/v1/knx/ai-spend/entries/{$uncertain->getKey()}/reconcile", ['actualUsd' => 0.1]],
        ] as [$method, $url, $body]) {
            $this->{$method}($url, $body)->assertForbidden()->assertJsonPath('code', 'knx_admin_required');
        }
        $this->assertSame('uncertain', $uncertain->fresh()->status);
        $this->assertSame(0, KnxAiSpendSetting::query()->count());
    }

    public function test_guests_and_field_users_get_401(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/knx/ai-spend/today')->assertUnauthorized();
        $this->putJson('/api/v1/knx/ai-spend/limit', ['dailyLimitUsd' => 1])->assertUnauthorized();

        $this->actingAs(User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(), 'sanctum');
        $this->getJson('/api/v1/knx/ai-spend/today')->assertUnauthorized();
        $this->deleteJson('/api/v1/knx/ai-spend/limit')->assertUnauthorized();
        $this->postJson('/api/v1/knx/ai-spend/entries/1/reconcile', ['actualUsd' => 1])->assertUnauthorized();
    }

    public function test_the_limit_override_is_stored_and_deleted_and_changes_the_next_reservation(): void
    {
        $ledger = app(AiSpendLedger::class);
        $this->entry('settled', 1.0, 1.0);

        $this->putJson('/api/v1/knx/ai-spend/limit', ['dailyLimitUsd' => 0.5])
            ->assertOk()
            ->assertJsonPath('limitSource', 'override')
            ->assertJsonPath('limitUsd', 0.5)
            ->assertJsonPath('availableUsd', 0);
        $this->assertSame($this->admin->getKey(), KnxAiSpendSetting::query()->sole()->updated_by_employee_id);
        try {
            $ledger->reserve($this->context(), 'openai', 'plan_analysis', 'm-test', 0.01);
            $this->fail('A limit below the committed spend must refuse the next reservation.');
        } catch (AiSpendRefused $e) {
            $this->assertSame('daily_budget_exhausted', $e->reason);
        }

        $this->putJson('/api/v1/knx/ai-spend/limit', ['dailyLimitUsd' => 2.0])->assertOk()->assertJsonPath('availableUsd', 1);
        $entry = $ledger->reserve($this->context(), 'openai', 'plan_analysis', 'm-test', 0.5);
        $this->assertSame('reserved', $entry->status);

        $this->deleteJson('/api/v1/knx/ai-spend/limit')
            ->assertOk()
            ->assertJsonPath('limitSource', 'config')
            ->assertJsonPath('limitUsd', 5)
            ->assertJsonPath('pendingUsd', 0.5);
        $this->assertSame(0, KnxAiSpendSetting::query()->count());
    }

    public function test_reconciling_an_uncertain_entry_settles_it_and_counts_the_actual_amount(): void
    {
        $entry = $this->entry('uncertain', 0.8);
        Log::spy();

        $json = $this->postJson("/api/v1/knx/ai-spend/entries/{$entry->getKey()}/reconcile", ['actualUsd' => 0.1, 'note' => 'checked in the OpenAI dashboard'])
            ->assertOk()
            ->json();

        $fresh = $entry->fresh();
        $this->assertSame('settled', $fresh->status);
        $this->assertEquals(0.1, (float) $fresh->actual_usd);
        $this->assertSame('reconciled', $fresh->reason);
        $this->assertNotNull($fresh->settled_at);
        $this->assertSame($this->admin->getKey(), $fresh->reconciled_by_employee_id);
        $this->assertSame('checked in the OpenAI dashboard', $fresh->reconciled_note);
        $this->assertEquals(0.1, $json['spentUsd']);
        $this->assertEquals(0, $json['uncertainUsd']);
        $this->assertEquals(4.9, $json['availableUsd']);
        $this->assertSame([], $json['uncertain']);
        Log::shouldHaveReceived('info')->withArgs(fn (string $m, array $ctx = []): bool => $m === 'knx.ai_spend.reconciled'
            && array_keys($ctx) === ['entry_id', 'org', 'reserved_usd', 'actual_usd', 'employee_id'])->once();
    }

    public function test_only_an_uncertain_entry_can_be_reconciled(): void
    {
        foreach ([['settled', 0.5, 0.4], ['reserved', 0.5, null], ['released', 0.5, null]] as [$status, $reserved, $actual]) {
            $entry = $this->entry($status, $reserved, $actual);

            $this->postJson("/api/v1/knx/ai-spend/entries/{$entry->getKey()}/reconcile", ['actualUsd' => 0.01])
                ->assertStatus(409)
                ->assertJsonPath('code', 'ai_spend_not_uncertain');
            $fresh = $entry->fresh();
            $this->assertSame($status, $fresh->status);
            $this->assertNull($fresh->reconciled_by_employee_id);
            $this->assertEquals($actual, $fresh->actual_usd === null ? null : (float) $fresh->actual_usd);
        }
    }

    public function test_another_organizations_entry_is_a_404(): void
    {
        $other = Organization::factory()->create(['slug' => 'other']);
        $entry = $this->entry('uncertain', 0.5, null, '2026-01-15', [], $other->getKey());

        $this->postJson("/api/v1/knx/ai-spend/entries/{$entry->getKey()}/reconcile", ['actualUsd' => 0.01])->assertNotFound();
        $this->postJson('/api/v1/knx/ai-spend/entries/999999/reconcile', ['actualUsd' => 0.01])->assertNotFound();
        $this->assertSame('uncertain', $entry->fresh()->status);

        $this->entry('uncertain', 0.5, null, '2026-01-15', [], $other->getKey());
        $this->assertSame([], $this->getJson('/api/v1/knx/ai-spend/today')->json('uncertain'));
    }

    public function test_validation_rejects_negative_and_oversized_amounts(): void
    {
        $entry = $this->entry('uncertain', 0.5);
        $url = "/api/v1/knx/ai-spend/entries/{$entry->getKey()}/reconcile";

        foreach ([-1, 10000.01, 'abc', null] as $bad) {
            $this->putJson('/api/v1/knx/ai-spend/limit', ['dailyLimitUsd' => $bad])->assertStatus(422);
            $this->postJson($url, ['actualUsd' => $bad])->assertStatus(422);
        }
        $this->postJson($url, ['actualUsd' => 0.1, 'note' => str_repeat('x', 201)])->assertStatus(422);
        $this->assertSame(0, KnxAiSpendSetting::query()->count());
        $this->assertSame('uncertain', $entry->fresh()->status);

        $this->putJson('/api/v1/knx/ai-spend/limit', ['dailyLimitUsd' => 0])->assertOk();
        $this->putJson('/api/v1/knx/ai-spend/limit', ['dailyLimitUsd' => 10000])->assertOk();
    }

    public function test_responses_carry_no_plan_content(): void
    {
        $this->entry('uncertain', 0.5, null, '2026-01-15', ['usage' => ['inputTokens' => 10], 'reconciled_note' => 'secret note']);

        $body = $this->getJson('/api/v1/knx/ai-spend/today')->getContent();

        foreach (['usage', 'processing', 'room', 'prompt', 'image', 'secret note', 'inputTokens'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $body);
        }
    }

    public function test_the_employee_knows_whether_it_is_an_admin(): void
    {
        $this->assertTrue($this->admin->isAdmin());
        $this->assertFalse($this->employeeOf('pieter.aerts@electrobertels.be')->isAdmin());
    }
}
