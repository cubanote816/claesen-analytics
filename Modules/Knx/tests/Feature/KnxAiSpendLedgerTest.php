<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Modules\Core\Models\Organization;
use Modules\Knx\Models\KnxAiSpendEntry;
use Modules\Knx\Models\KnxAiSpendSetting;
use Modules\Knx\Services\AiSpend\AiSpendContext;
use Modules\Knx\Services\AiSpend\AiSpendLedger;
use Modules\Knx\Services\AiSpend\AiSpendRefused;
use Tests\TestCase;

/**
 * The daily USD ledger of OpenAI calls: Brussels day, reservations, locking, settlement.
 * No network: nothing here talks to OpenAI.
 */
final class KnxAiSpendLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-01-15 10:00:00', 'UTC'));
        $this->organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        config([
            'knx.ai_spend.daily_limit_usd' => 1.00,
            'knx.ai_spend.timezone' => 'Europe/Brussels',
            'knx.ai_spend.max_calls_per_processing' => 5,
            'knx.ai_spend.max_cost_per_processing_usd' => 10.00,
            'knx.ai_spend.data_residency_multiplier' => 1.0,
            'knx.ai_spend.models' => [
                'm-test' => ['input' => 1.00, 'cached_input' => 0.05, 'output' => 5.00, 'image_input_tokens_max' => 30000],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_nothing_is_read_before_the_day_lock(): void
    {
        // Under REPEATABLE READ the first plain SELECT fixes the snapshot: the limit, the
        // committed sum and the caps must all be read AFTER the day row is locked.
        $locked = false;
        $early = [];
        DB::listen(function ($query) use (&$locked, &$early): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'knx_ai_spend_days') && str_contains($sql, 'for update')) {
                $locked = true;
            }
            if (! $locked && str_starts_with(ltrim($sql), 'select')
                && (str_contains($sql, 'knx_ai_spend_settings') || str_contains($sql, 'knx_ai_spend_entries'))) {
                $early[] = $sql;
            }
        });

        $this->reserve(0.1);

        $this->assertTrue($locked);
        $this->assertSame([], $early);
    }

    public function test_the_daily_refusal_says_when_it_resumes_and_that_an_administrator_can_adjust_it(): void
    {
        config(['knx.ai_spend.daily_limit_usd' => 0.05]);

        $refused = $this->refusal(fn () => $this->reserve(0.1));

        $this->assertSame('daily_budget_exhausted', $refused->reason);
        $this->assertStringContainsString('administrator', $refused->getMessage());
        $this->assertStringContainsString('Europe/Brussels', $refused->getMessage());
    }

    private function ledger(): AiSpendLedger
    {
        return app(AiSpendLedger::class);
    }

    private function context(?string $processingId = null): AiSpendContext
    {
        return new AiSpendContext(
            organizationId: $this->organization->getKey(),
            processingId: $processingId ?? (string) Str::uuid(),
            projectId: null,
            documentId: null,
            page: 1,
            employeeId: null,
        );
    }

    private function reserve(float $usd, ?AiSpendContext $context = null): KnxAiSpendEntry
    {
        return $this->ledger()->reserve($context ?? $this->context(), 'openai', 'plan_analysis', 'm-test', $usd);
    }

    private function refusal(callable $call): AiSpendRefused
    {
        try {
            $call();
        } catch (AiSpendRefused $e) {
            return $e;
        }
        $this->fail('Expected AiSpendRefused.');
    }

    public function test_the_day_is_the_brussels_day_not_the_utc_day(): void
    {
        // 23:30 UTC in winter is 00:30 of the NEXT day in Brussels.
        Carbon::setTestNow(Carbon::parse('2026-01-15 23:30:00', 'UTC'));
        $entry = $this->reserve(0.1);
        $this->assertSame('2026-01-16', $entry->day->toDateString());

        // 22:30 UTC is still the same Brussels day.
        Carbon::setTestNow(Carbon::parse('2026-01-15 22:30:00', 'UTC'));
        $this->assertSame('2026-01-15', $this->reserve(0.1)->day->toDateString());

        // Summer: 21:30 UTC is 23:30 in Brussels (UTC+2), the same day.
        Carbon::setTestNow(Carbon::parse('2026-07-15 21:30:00', 'UTC'));
        $this->assertSame('2026-07-15', $this->reserve(0.1)->day->toDateString());
    }

    public function test_a_refusal_carries_the_next_brussels_midnight(): void
    {
        config(['knx.ai_spend.daily_limit_usd' => 0.05]);
        $refused = $this->refusal(fn () => $this->reserve(0.1));

        $this->assertSame('daily_budget_exhausted', $refused->reason);
        $this->assertSame(0.05, $refused->remainingUsd);
        $this->assertSame('2026-01-16T00:00:00+01:00', $refused->resumesAt);
    }

    public function test_settled_reserved_and_uncertain_all_count_against_the_limit(): void
    {
        $settled = $this->reserve(0.3);
        $this->ledger()->settle($settled, ['input_tokens' => 100000, 'output_tokens' => 20000], 'req_1'); // 0.1 + 0.1 = 0.2
        $uncertain = $this->reserve(0.3);
        $this->ledger()->markUncertain($uncertain, 'http_500', 'req_2');
        $this->reserve(0.3); // still reserved: pending

        // 0.2 + 0.3 + 0.3 = 0.8 committed; 0.2 left.
        $this->assertSame(0.8, $this->ledger()->committedUsd($this->organization->getKey()));
        $refused = $this->refusal(fn () => $this->reserve(0.25));
        $this->assertSame('daily_budget_exhausted', $refused->reason);
        $this->assertSame(0.2, $refused->remainingUsd);

        // Exactly the remainder fits.
        $this->reserve(0.2);
        $this->assertSame(1.0, $this->ledger()->committedUsd($this->organization->getKey()));
        $this->assertSame(0.0, $this->ledger()->remainingUsd($this->organization->getKey()));
    }

    public function test_a_rejected_reservation_writes_nothing(): void
    {
        $this->reserve(0.9);
        $before = KnxAiSpendEntry::query()->count();

        $this->refusal(fn () => $this->reserve(0.2));

        $this->assertSame($before, KnxAiSpendEntry::query()->count());
    }

    public function test_a_limit_of_zero_refuses_everything(): void
    {
        config(['knx.ai_spend.daily_limit_usd' => 0]);

        $refused = $this->refusal(fn () => $this->reserve(0.000001));

        $this->assertSame('daily_budget_exhausted', $refused->reason);
        $this->assertSame(0, KnxAiSpendEntry::query()->count());
    }

    public function test_settle_replaces_the_reservation_by_the_actual_cost(): void
    {
        $entry = $this->reserve(0.5);
        $this->assertSame(0.5, $this->ledger()->committedUsd($this->organization->getKey()));

        $settled = $this->ledger()->settle($entry, [
            'input_tokens' => 10000,
            'input_tokens_details' => ['cached_tokens' => 4000],
            'output_tokens' => 2000,
        ], 'req_ok');

        $this->assertSame('settled', $settled->status);
        $this->assertSame(0.0162, (float) $settled->actual_usd);
        $this->assertSame('req_ok', $settled->request_id);
        $this->assertNotNull($settled->settled_at);
        $this->assertSame(['inputTokens' => 10000, 'cachedTokens' => 4000, 'outputTokens' => 2000, 'costUsd' => 0.0162], $settled->usage);
        $this->assertSame(0.0162, $this->ledger()->committedUsd($this->organization->getKey()));
    }

    public function test_settle_without_usable_usage_keeps_the_reservation_as_uncertain(): void
    {
        $entry = $this->reserve(0.5);

        $result = $this->ledger()->settle($entry, [], 'req_nousage');

        $this->assertSame('uncertain', $result->status);
        $this->assertSame('cost_unknown', $result->reason);
        $this->assertSame(0.5, $this->ledger()->committedUsd($this->organization->getKey()));
    }

    public function test_uncertain_keeps_the_reservation_counting_and_can_be_reconciled_later(): void
    {
        $entry = $this->reserve(0.6);
        $this->ledger()->markUncertain($entry, 'connection_error', null);

        $entry->refresh();
        $this->assertSame('uncertain', $entry->status);
        $this->assertSame('connection_error', $entry->reason);
        $this->assertSame(0.6, $this->ledger()->committedUsd($this->organization->getKey()));
        $this->assertSame('daily_budget_exhausted', $this->refusal(fn () => $this->reserve(0.5))->reason);

        // Reconciliation with the real usage replaces it.
        $this->ledger()->settle($entry, ['input_tokens' => 1000, 'output_tokens' => 100], 'req_late');
        $this->assertSame(0.0015, $this->ledger()->committedUsd($this->organization->getKey()));
    }

    public function test_release_is_only_for_a_request_that_was_never_sent(): void
    {
        $never = $this->reserve(0.4);
        $this->ledger()->release($never, 'not_authorized');
        $this->assertSame('released', $never->refresh()->status);
        $this->assertSame(0.0, $this->ledger()->committedUsd($this->organization->getKey()));

        // A request that may have reached OpenAI is never released.
        $maybeSent = $this->reserve(0.4);
        $this->ledger()->markUncertain($maybeSent, 'http_500', null);
        try {
            $this->ledger()->release($maybeSent, 'oops');
            $this->fail('Expected LogicException.');
        } catch (LogicException) {
            $this->assertSame('uncertain', $maybeSent->refresh()->status);
            $this->assertSame(0.4, $this->ledger()->committedUsd($this->organization->getKey()));
        }
    }

    public function test_a_processing_is_limited_in_calls(): void
    {
        config(['knx.ai_spend.max_calls_per_processing' => 2]);
        $context = $this->context();
        $this->reserve(0.01, $context);
        $this->reserve(0.01, $context);

        $this->assertSame('processing_call_limit', $this->refusal(fn () => $this->reserve(0.01, $context))->reason);
        // Another processing is not affected.
        $this->reserve(0.01);
    }

    public function test_a_processing_is_limited_in_cost(): void
    {
        config(['knx.ai_spend.max_cost_per_processing_usd' => 0.30, 'knx.ai_spend.daily_limit_usd' => 100]);
        $context = $this->context();
        $first = $this->reserve(0.2, $context);

        $this->assertSame('processing_cost_limit', $this->refusal(fn () => $this->reserve(0.15, $context))->reason);

        // Once the first call settles for less, the processing has room again.
        $this->ledger()->settle($first, ['input_tokens' => 10000, 'output_tokens' => 1000], null); // 0.015
        $this->reserve(0.15, $context);
    }

    public function test_the_effective_limit_is_the_admin_override_when_present(): void
    {
        $orgId = $this->organization->getKey();
        $this->assertSame(1.0, $this->ledger()->effectiveLimit($orgId));

        KnxAiSpendSetting::query()->create(['organization_id' => $orgId, 'daily_limit_usd' => 0.25]);

        $this->assertSame(0.25, $this->ledger()->effectiveLimit($orgId));
        $this->assertSame('daily_budget_exhausted', $this->refusal(fn () => $this->reserve(0.3))->reason);
        $this->reserve(0.25);

        // An override of 0 stops everything even with a configured limit.
        KnxAiSpendSetting::query()->where('organization_id', $orgId)->update(['daily_limit_usd' => 0]);
        $this->assertSame(0.0, $this->ledger()->effectiveLimit($orgId));
    }

    public function test_the_day_row_snapshots_the_limit_and_is_unique_per_day(): void
    {
        $this->reserve(0.1);
        $this->reserve(0.1);

        $rows = DB::table('knx_ai_spend_days')->get();
        $this->assertCount(1, $rows);
        $this->assertSame(1.0, (float) $rows[0]->limit_usd);
        $this->assertSame('2026-01-15', $rows[0]->day);
    }

    public function test_reserve_locks_the_day_row_against_a_second_connection(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        $this->reserve(0.1);

        // The reservation selects the day row FOR UPDATE inside a transaction...
        $this->assertNotEmpty(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'knx_ai_spend_days') && str_contains($sql, 'for update')));

        // ...and (the test transaction is still open) a second connection cannot lock the same row.
        $default = config('database.default');
        config(['database.connections.knx_probe' => config("database.connections.{$default}")]);
        $probe = DB::connection('knx_probe');
        try {
            $probe->select('SELECT id FROM knx_ai_spend_days WHERE organization_id = ? AND day = ? FOR UPDATE NOWAIT', [$this->organization->getKey(), '2026-01-15']);
            $this->fail('The day row must be locked while the first transaction holds it.');
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/3572|NOWAIT|lock/i', $e->getMessage());
        } finally {
            DB::purge('knx_probe');
        }
    }

    public function test_the_ledger_logs_ids_and_amounts_only(): void
    {
        Log::spy();
        $entry = $this->reserve(0.1);
        $this->ledger()->settle($entry, ['input_tokens' => 10, 'output_tokens' => 10], 'req_log');

        Log::shouldHaveReceived('info')->atLeast()->once();
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []): bool {
            foreach ($context as $key => $value) {
                if (! in_array($key, ['entry_id', 'org', 'day', 'provider', 'operation', 'model', 'status', 'reason', 'request_id', 'processing_id', 'project_id', 'document_id', 'reserved_usd', 'actual_usd', 'remaining_usd', 'limit_usd'], true)) {
                    return false;
                }
                $this->assertIsScalar($value ?? '');
            }

            return true;
        });
    }
}
