<?php

namespace Modules\Knx\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Knx\Models\KnxAcceptanceTest;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxTestExecution;

/**
 * Recording the result of an acceptance test (§3.2).
 *
 * Three rules, all of them about not losing what happened:
 *
 *   1. `failed`, `blocked` and `not_applicable` **must carry a note**. A failed
 *      test with no reason is unusable at delivery time.
 *   2. A failure **creates (or links) an issue** while keeping the context: the test
 *      already carries function + zone + action, so the issue reference is what was
 *      missing. The id is generated here because this domain has no separate issue
 *      entity yet — an existing `issueId` is never overwritten.
 *   3. Every recorded run is **appended** to the execution history. That is the
 *      point of the table: correcting a failure and re-running the test leaves two
 *      executions, so the earlier failure does not disappear from the evidence.
 */
class AcceptanceTestService
{
    /** Statuses that mean "executed" and therefore get an execution row. */
    private const RECORDED_STATUSES = ['passed', 'failed', 'blocked', 'not_applicable'];

    private const NOTE_REQUIRED = ['failed', 'blocked', 'not_applicable'];

    /**
     * @param  array{status?: string, observed?: string|null, note?: string|null}  $patch
     */
    public function record(KnxAcceptanceTest $test, array $patch, ?KnxEmployee $by): KnxAcceptanceTest
    {
        $status = $patch['status'] ?? $test->status;

        if (in_array($status, self::NOTE_REQUIRED, true) && blank($patch['note'] ?? $test->note)) {
            throw ValidationException::withMessages([
                'note' => __('knx::tests.note_required'),
            ]);
        }

        $test->fill(array_filter([
            'status' => $patch['status'] ?? null,
            'observed' => $patch['observed'] ?? null,
            'note' => $patch['note'] ?? null,
        ], static fn ($value, $key): bool => array_key_exists($key, $patch), ARRAY_FILTER_USE_BOTH));

        if (in_array($status, self::RECORDED_STATUSES, true)) {
            $test->executor_employee_id = $by?->getKey();
            $test->executed_at = now();
        }

        if ($status === 'failed' && blank($test->issue_id)) {
            // Keeps the context by construction: the issue is the test, which already
            // knows its function, its zone and the action that failed.
            $test->issue_id = 'iss-'.$test->getKey().'-'.Str::lower(Str::random(6));
        }

        $test->save();

        if (in_array($status, self::RECORDED_STATUSES, true)) {
            $this->appendExecution($test, $by);
        }

        return $test->refresh()->load(['project', 'function.zone', 'executor']);
    }

    private function appendExecution(KnxAcceptanceTest $test, ?KnxEmployee $by): void
    {
        KnxTestExecution::create([
            'acceptance_test_id' => $test->getKey(),
            'status' => $test->status,
            'observed' => $test->observed,
            'note' => $test->note,
            'executor_employee_id' => $by?->getKey(),
            'executed_at' => $test->executed_at ?? now(),
        ]);
    }
}
