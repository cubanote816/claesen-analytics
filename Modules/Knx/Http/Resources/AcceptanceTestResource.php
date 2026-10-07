<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxAcceptanceTest;

/**
 * `AcceptanceTest` of BACKEND-API-ZONES.md §3.
 *
 * A test hangs off a **function**, which is what lets the office answer "verified
 * against which agreed behaviour"; `functionName` and `zoneName` are derived, and
 * `physicalCheck` keeps the difference between "the KNX state changed" and "the
 * lamp actually came on" explicit.
 *
 * @property-read KnxAcceptanceTest $resource
 */
class AcceptanceTestResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        $test = $this->resource;

        return [
            'id' => (string) $test->getKey(),
            'projectCode' => $test->project?->code,
            'functionId' => $test->function_id === null ? null : (string) $test->function_id,
            'functionName' => $test->function?->name,
            'zoneName' => $test->function?->zone?->name,
            'action' => $test->action,
            'expected' => $test->expected,
            'observed' => $test->observed,
            'status' => $test->status,
            'note' => $test->note,
            'physicalCheck' => $test->physical_check,
            'executor' => $test->executor?->shortName(),
            'executedAt' => $test->executed_at?->toIso8601ZuluString(),
            'evidenceUrls' => $test->evidence_urls ?? [],
            'issueId' => $test->issue_id,
        ];
    }
}
