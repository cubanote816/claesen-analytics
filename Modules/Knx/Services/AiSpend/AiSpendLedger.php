<?php

declare(strict_types=1);

namespace Modules\Knx\Services\AiSpend;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Modules\Knx\Models\KnxAiSpendEntry;
use Modules\Knx\Models\KnxAiSpendSetting;

/**
 * The daily USD ledger of OpenAI calls. A call is RESERVED before it is sent, inside a
 * transaction that holds a `FOR UPDATE` lock on the day row, so two simultaneous
 * requests cannot both pass the limit. Committed spend of a day = settled actual costs
 * + reservations still `reserved` + `uncertain` ones.
 *
 * A reservation is only RELEASED when the request was never sent. Anything that may have
 * reached OpenAI (timeout, 5xx, 4xx, missing usage, parse failure) stays as `uncertain`
 * and keeps counting until someone reconciles it.
 *
 * Logs carry ids, model, amounts, status, reason and request id: never plan content.
 */
class AiSpendLedger
{
    public function __construct(private readonly AiSpendEstimator $estimator) {}

    /** The budget day: always computed in the configured timezone (Europe/Brussels). */
    public function budgetDay(): string
    {
        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    /** Next midnight of the budget timezone, ISO 8601. */
    public function resumesAt(): string
    {
        return CarbonImmutable::now($this->timezone())->addDay()->startOfDay()->toIso8601String();
    }

    /** The admin override when present, else the configured limit (USD). */
    public function effectiveLimit(int $organizationId): float
    {
        $override = KnxAiSpendSetting::query()->where('organization_id', $organizationId)->value('daily_limit_usd');

        return round(max(0.0, $override !== null ? (float) $override : (float) config('knx.ai_spend.daily_limit_usd', 0)), 6);
    }

    /** Settled actual costs + reserved + uncertain reservations of today. */
    public function committedUsd(int $organizationId, ?string $day = null): float
    {
        $sum = KnxAiSpendEntry::query()
            ->where('organization_id', $organizationId)
            ->where('day', $day ?? $this->budgetDay())
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'settled' THEN COALESCE(actual_usd, 0) WHEN status IN ('reserved', 'uncertain') THEN reserved_usd ELSE 0 END), 0) AS committed")
            ->value('committed');

        return round((float) $sum, 6);
    }

    public function remainingUsd(int $organizationId): float
    {
        return round(max(0.0, $this->effectiveLimit($organizationId) - $this->committedUsd($organizationId)), 6);
    }

    /**
     * @throws AiSpendRefused `daily_budget_exhausted`, `processing_call_limit`, `processing_cost_limit`
     */
    public function reserve(AiSpendContext $context, string $provider, string $operation, string $model, float $usd): KnxAiSpendEntry
    {
        $entry = DB::transaction(function () use ($context, $provider, $operation, $model, $usd): KnxAiSpendEntry {
            $orgId = $context->organizationId;
            $day = $this->budgetDay();
            $limit = $this->effectiveLimit($orgId);
            $usd = round($usd, 6);

            $this->lockDay($orgId, $day, $limit);

            $committed = $this->committedUsd($orgId, $day);
            if (round($committed + $usd, 6) > $limit) {
                $remaining = round(max(0.0, $limit - $committed), 6);
                $this->logRefusal('daily_budget_exhausted', $context, $provider, $operation, $model, $usd, $remaining);
                throw new AiSpendRefused('daily_budget_exhausted', 'The daily OpenAI spend limit is reached.', $remaining, $this->resumesAt());
            }

            $processing = KnxAiSpendEntry::query()
                ->where('organization_id', $orgId)
                ->where('processing_id', $context->processingId)
                ->where('status', '!=', KnxAiSpendEntry::RELEASED);
            if ($processing->count() >= max(0, (int) config('knx.ai_spend.max_calls_per_processing', 2))) {
                $this->logRefusal('processing_call_limit', $context, $provider, $operation, $model, $usd, null);
                throw new AiSpendRefused('processing_call_limit', 'This processing used up its OpenAI calls.');
            }
            $spent = (float) (clone $processing)
                ->selectRaw("COALESCE(SUM(CASE WHEN status = 'settled' THEN COALESCE(actual_usd, 0) ELSE reserved_usd END), 0) AS spent")
                ->value('spent');
            if (round($spent + $usd, 6) > round((float) config('knx.ai_spend.max_cost_per_processing_usd', 0.5), 6)) {
                $this->logRefusal('processing_cost_limit', $context, $provider, $operation, $model, $usd, null);
                throw new AiSpendRefused('processing_cost_limit', 'This processing would exceed its maximum OpenAI cost.');
            }

            return KnxAiSpendEntry::query()->create([
                'organization_id' => $orgId,
                'day' => $day,
                'provider' => $provider,
                'operation' => $operation,
                'model' => $model,
                'status' => KnxAiSpendEntry::RESERVED,
                'reserved_usd' => $usd,
                'project_id' => $context->projectId,
                'document_id' => $context->documentId,
                'page' => $context->page,
                'processing_id' => $context->processingId,
                'created_by_employee_id' => $context->employeeId,
            ]);
        });

        $this->log('reserved', $entry);

        return $entry;
    }

    /**
     * The call returned: replace the reservation by the real cost. When it cannot be
     * computed (no usage, no price) the reservation stays as `uncertain`.
     *
     * @param  array<string, mixed>  $responseUsage  the Responses `usage` object (maybe empty)
     */
    public function settle(KnxAiSpendEntry $entry, array $responseUsage, ?string $requestId): KnxAiSpendEntry
    {
        $breakdown = $this->estimator->breakdown($entry->model, $responseUsage);

        $this->transition($entry, [KnxAiSpendEntry::RESERVED, KnxAiSpendEntry::UNCERTAIN], function (KnxAiSpendEntry $fresh) use ($breakdown, $requestId): void {
            $fresh->fill([
                'usage' => $breakdown,
                'request_id' => $requestId ?? $fresh->request_id,
                'status' => $breakdown['costUsd'] === null ? KnxAiSpendEntry::UNCERTAIN : KnxAiSpendEntry::SETTLED,
                'actual_usd' => $breakdown['costUsd'],
                'reason' => $breakdown['costUsd'] === null ? 'cost_unknown' : null,
                'settled_at' => $breakdown['costUsd'] === null ? null : now(),
            ])->save();
        });
        $this->log($entry->status, $entry);

        return $entry;
    }

    /** The request may have been sent: keep the reservation counting until reconciled. */
    public function markUncertain(KnxAiSpendEntry $entry, string $reason, ?string $requestId): KnxAiSpendEntry
    {
        $this->transition($entry, [KnxAiSpendEntry::RESERVED, KnxAiSpendEntry::UNCERTAIN], function (KnxAiSpendEntry $fresh) use ($reason, $requestId): void {
            $fresh->fill([
                'status' => KnxAiSpendEntry::UNCERTAIN,
                'reason' => substr($reason, 0, 64),
                'request_id' => $requestId ?? $fresh->request_id,
            ])->save();
        });
        $this->log('uncertain', $entry);

        return $entry;
    }

    /**
     * ONLY for a request that was never sent. An uncertain or settled entry cannot be released.
     */
    public function release(KnxAiSpendEntry $entry, string $reason): KnxAiSpendEntry
    {
        $this->transition($entry, [KnxAiSpendEntry::RESERVED], function (KnxAiSpendEntry $fresh) use ($reason): void {
            $fresh->fill(['status' => KnxAiSpendEntry::RELEASED, 'reason' => substr($reason, 0, 64)])->save();
        });
        $this->log('released', $entry);

        return $entry;
    }

    /**
     * Creates the day row if needed and locks it (`SELECT ... FOR UPDATE`) until commit.
     */
    private function lockDay(int $organizationId, string $day, float $limit): void
    {
        DB::table('knx_ai_spend_days')->insertOrIgnore([
            'organization_id' => $organizationId,
            'day' => $day,
            'limit_usd' => $limit,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('knx_ai_spend_days')
            ->where('organization_id', $organizationId)
            ->where('day', $day)
            ->lockForUpdate()
            ->first();
        DB::table('knx_ai_spend_days')
            ->where('organization_id', $organizationId)
            ->where('day', $day)
            ->update(['limit_usd' => $limit, 'updated_at' => now()]);
    }

    /**
     * @param  list<string>  $from
     * @param  callable(KnxAiSpendEntry): void  $apply
     */
    private function transition(KnxAiSpendEntry $entry, array $from, callable $apply): void
    {
        DB::transaction(function () use ($entry, $from, $apply): void {
            $this->lockDay($entry->organization_id, $entry->day->toDateString(), $this->effectiveLimit($entry->organization_id));
            $entry->refresh();
            if (! in_array($entry->status, $from, true)) {
                throw new LogicException("A ledger entry that is `{$entry->status}` cannot take this transition.");
            }
            $apply($entry);
        });
    }

    private function timezone(): string
    {
        return (string) config('knx.ai_spend.timezone', 'Europe/Brussels');
    }

    private function log(string $event, KnxAiSpendEntry $entry): void
    {
        Log::info('knx.ai_spend.'.$event, [
            'entry_id' => $entry->getKey(),
            'org' => $entry->organization_id,
            'day' => $entry->day->toDateString(),
            'provider' => $entry->provider,
            'operation' => $entry->operation,
            'model' => $entry->model,
            'status' => $entry->status,
            'reason' => $entry->reason,
            'request_id' => $entry->request_id,
            'processing_id' => $entry->processing_id,
            'project_id' => $entry->project_id,
            'document_id' => $entry->document_id,
            'reserved_usd' => (float) $entry->reserved_usd,
            'actual_usd' => $entry->actual_usd === null ? null : (float) $entry->actual_usd,
        ]);
    }

    private function logRefusal(string $reason, AiSpendContext $context, string $provider, string $operation, string $model, float $usd, ?float $remaining): void
    {
        Log::info('knx.ai_spend.refused', [
            'org' => $context->organizationId,
            'day' => $this->budgetDay(),
            'provider' => $provider,
            'operation' => $operation,
            'model' => $model,
            'reason' => $reason,
            'processing_id' => $context->processingId,
            'project_id' => $context->projectId,
            'document_id' => $context->documentId,
            'reserved_usd' => $usd,
            'remaining_usd' => $remaining,
        ]);
    }
}
