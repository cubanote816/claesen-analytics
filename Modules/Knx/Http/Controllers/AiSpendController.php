<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use LogicException;
use Modules\Knx\Models\KnxAiSpendEntry;
use Modules\Knx\Models\KnxAiSpendSetting;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Services\AiSpend\AiSpendLedger;

/**
 * Administration of the daily USD limit of OpenAI calls (admins only, see
 * EnsureKnxAdmin). Only ids, amounts, models and statuses: never plan content.
 */
class AiSpendController extends Controller
{
    private const MAX_USD = 10000;

    public function __construct(private readonly AiSpendLedger $ledger) {}

    public function today(Request $request): array
    {
        return $this->summary($this->employee($request)->organization_id);
    }

    public function updateLimit(Request $request): array
    {
        $employee = $this->employee($request);
        $validated = $request->validate([
            'dailyLimitUsd' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_USD],
        ]);

        $this->ledger->underDayLock($employee->organization_id, fn () => KnxAiSpendSetting::query()->updateOrCreate(
            ['organization_id' => $employee->organization_id],
            ['daily_limit_usd' => round((float) $validated['dailyLimitUsd'], 6), 'updated_by_employee_id' => $employee->getKey()],
        ));
        Log::info('knx.ai_spend.limit_changed', [
            'org' => $employee->organization_id,
            'daily_limit_usd' => round((float) $validated['dailyLimitUsd'], 6),
            'employee_id' => $employee->getKey(),
        ]);

        return $this->summary($employee->organization_id);
    }

    public function resetLimit(Request $request): array
    {
        $employee = $this->employee($request);
        $this->ledger->underDayLock($employee->organization_id, fn () => KnxAiSpendSetting::query()->where('organization_id', $employee->organization_id)->delete());
        Log::info('knx.ai_spend.limit_reset', ['org' => $employee->organization_id, 'employee_id' => $employee->getKey()]);

        return $this->summary($employee->organization_id);
    }

    public function reconcile(Request $request, string $id): JsonResponse
    {
        $employee = $this->employee($request);
        $validated = $request->validate([
            'actualUsd' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_USD],
            'note' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $entry = ctype_digit($id)
            ? KnxAiSpendEntry::query()->where('organization_id', $employee->organization_id)->find((int) $id)
            : null;
        abort_if($entry === null, 404, 'Not found.');

        $notUncertain = response()->json([
            'message' => 'Only an uncertain entry can be reconciled.',
            'code' => 'ai_spend_not_uncertain',
            'errors' => (object) [],
        ], 409);
        // Uncertain entries, and reservations stranded long enough to never settle on their own.
        if ($entry->status !== KnxAiSpendEntry::UNCERTAIN && ! $this->ledger->isStranded($entry)) {
            return $notUncertain;
        }

        try {
            $this->ledger->reconcile($entry, (float) $validated['actualUsd'], (int) $employee->getKey(), $validated['note'] ?? null);
        } catch (LogicException) {
            return $notUncertain;
        }

        return response()->json($this->summary($employee->organization_id));
    }

    private function employee(Request $request): KnxEmployee
    {
        /** @var KnxEmployee */
        return $request->attributes->get('knx_employee');
    }

    /** @return array<string, mixed> */
    private function summary(int $orgId): array
    {
        $day = $this->ledger->budgetDay();
        $limit = $this->ledger->effectiveLimit($orgId);
        $base = KnxAiSpendEntry::query()->where('organization_id', $orgId)->where('day', $day);
        $sum = fn (string $status, string $column): float => round((float) (clone $base)->where('status', $status)->sum($column), 6);

        $spent = $sum(KnxAiSpendEntry::SETTLED, 'actual_usd');
        $pending = $sum(KnxAiSpendEntry::RESERVED, 'reserved_usd');
        $uncertain = $sum(KnxAiSpendEntry::UNCERTAIN, 'reserved_usd');

        $entries = (clone $base)->with('project:id,code')->orderByDesc('id')->limit(100)->get();
        $uncertainEntries = KnxAiSpendEntry::query()->where('organization_id', $orgId)
            ->where('status', KnxAiSpendEntry::UNCERTAIN)->with('project:id,code')->orderByDesc('id')->get();

        return [
            'day' => $day,
            'timezone' => (string) config('knx.ai_spend.timezone', 'Europe/Brussels'),
            'currency' => 'USD',
            'limitUsd' => $limit,
            'limitSource' => KnxAiSpendSetting::query()->where('organization_id', $orgId)->exists() ? 'override' : 'config',
            'spentUsd' => $spent,
            'pendingUsd' => $pending,
            'uncertainUsd' => $uncertain,
            'availableUsd' => round(max(0.0, $limit - $spent - $pending - $uncertain), 6),
            'resumesAt' => $this->ledger->resumesAt(),
            'entries' => $entries->map(fn (KnxAiSpendEntry $e): array => $this->present($e))->all(),
            'uncertain' => $uncertainEntries->map(fn (KnxAiSpendEntry $e): array => $this->present($e) + ['day' => $e->day->toDateString()])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function present(KnxAiSpendEntry $e): array
    {
        return [
            'id' => (string) $e->getKey(),
            'provider' => $e->provider,
            'operation' => $e->operation,
            'model' => $e->model,
            'status' => $e->status,
            'reservedUsd' => round((float) $e->reserved_usd, 6),
            'actualUsd' => $e->actual_usd === null ? null : round((float) $e->actual_usd, 6),
            'projectCode' => $e->project?->code,
            'page' => $e->page,
            'requestId' => $e->request_id,
            'reason' => $e->reason,
            'createdAt' => $e->created_at?->toIso8601String(),
            'settledAt' => $e->settled_at?->toIso8601String(),
        ];
    }
}
