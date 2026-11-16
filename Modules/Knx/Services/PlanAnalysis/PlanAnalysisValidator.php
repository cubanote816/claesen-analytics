<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Modules\Knx\Models\KnxPlanCleanProfile;
use Modules\Knx\Models\KnxPlanDevice;

/**
 * Turns a provider's untrusted answer into a `plan-analysis/2` proposal that only refers to
 * what was sent. The model proposes; this class trims.
 *
 * - planKind: dwelling | other | unknown (anything else is `unknown`).
 * - group roles: only group ids of the input and roles of the enum; first answer per group.
 * - symbols: only symbol ids of the input, role and device type of the closed enums; first
 *   answer per symbol. A device carries the EXACT text of its `labelTextId` as label, and is
 *   kept with `needsReview` when its type has no valid legend entry (`no_legend_entry`) or
 *   its confidence is below 0.6 (`low_confidence`).
 * - legend: entries whose symbol (if any) and text were sent and whose type is in the enum.
 * - rooms: a point on the page and a kind; a NAME only when `textId` is a text of the input,
 *   and then the name IS that text (never the model's wording). Without evidence it is null.
 * - uncertain: points on the page with a reason.
 * Everything dropped is counted in `dropped`, so the review can see it. A v1-shaped answer
 * (no `planKind`/`symbols`/`legend`) is accepted: those sections are empty and uncounted.
 */
class PlanAnalysisValidator
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array{planKind: string, groupRoles: list<array<string, mixed>>, symbols: list<array<string, mixed>>, legend: list<array<string, mixed>>, rooms: list<array<string, mixed>>, uncertain: list<array<string, mixed>>, dropped: array<string, int>}
     *
     * @throws PlanAnalysisFailed `invalid_output` when the answer is not a proposal at all
     */
    public function validate(PlanAnalysisInput $input, array $raw): array
    {
        foreach (['groupRoles', 'rooms', 'uncertain'] as $key) {
            if (! isset($raw[$key]) || ! is_array($raw[$key]) || ! array_is_list($raw[$key])) {
                throw new PlanAnalysisFailed('invalid_output', "The answer has no `{$key}` list.");
            }
        }
        foreach (['symbols', 'legend'] as $key) {
            if (isset($raw[$key]) && (! is_array($raw[$key]) || ! array_is_list($raw[$key]))) {
                throw new PlanAnalysisFailed('invalid_output', "The answer's `{$key}` is not a list.");
            }
        }

        $dropped = ['groupRoles' => 0, 'rooms' => 0, 'uncertain' => 0, 'names' => 0];
        $groups = array_flip($input->groupIds());
        $texts = $input->textsById();

        $groupRoles = [];
        foreach ($raw['groupRoles'] as $entry) {
            $id = is_array($entry) ? ($entry['groupId'] ?? null) : null;
            $role = is_array($entry) ? ($entry['role'] ?? null) : null;
            if (! is_string($id) || ! isset($groups[$id]) || isset($groupRoles[$id])
                || ! is_string($role) || ! in_array($role, KnxPlanCleanProfile::ROLES, true)) {
                $dropped['groupRoles']++;

                continue;
            }
            $groupRoles[$id] = [
                'groupId' => $id,
                'role' => $role,
                'confidence' => $this->confidence($entry['confidence'] ?? null),
                'reason' => $this->reason($entry['reason'] ?? null),
            ];
        }

        $legend = [];
        if (isset($raw['legend'])) {
            $dropped['legend'] = 0;
            $legend = $this->legend($input, $texts, $raw['legend'], $dropped);
        }

        $symbols = [];
        if (isset($raw['symbols'])) {
            $dropped['symbols'] = 0;
            $symbols = $this->symbols($input, $texts, $raw['symbols'], $legend, $dropped);
        }

        $rooms = [];
        foreach ($raw['rooms'] as $entry) {
            $point = is_array($entry) ? $this->point($entry) : null;
            $kind = is_array($entry) ? ($entry['kind'] ?? 'other') : null;
            if ($point === null || ! is_string($kind) || ! in_array($kind, PlanAnalysisSchema::ROOM_KINDS, true)) {
                $dropped['rooms']++;

                continue;
            }
            $textId = $entry['textId'] ?? null;
            $evidence = is_string($textId) && isset($texts[$textId]);
            $name = $entry['name'] ?? null;
            if (! $evidence && is_string($name) && trim($name) !== '') {
                $dropped['names']++;
            }
            $geometryNullReason = $entry['geometryNullReason'] ?? null;
            $rooms[] = [
                'name' => $evidence ? trim($texts[$textId]) : null,
                'textId' => $evidence ? $textId : null,
                'x' => $point[0],
                'y' => $point[1],
                'kind' => $kind,
                'confidence' => $this->confidence($entry['confidence'] ?? null),
                'reason' => $this->reason($entry['reason'] ?? null),
                'geometryNullReason' => is_string($geometryNullReason) && trim($geometryNullReason) !== '' ? $this->reason($geometryNullReason) : null,
            ];
        }

        $uncertain = [];
        foreach ($raw['uncertain'] as $entry) {
            $point = is_array($entry) ? $this->point($entry) : null;
            if ($point === null) {
                $dropped['uncertain']++;

                continue;
            }
            $uncertain[] = ['x' => $point[0], 'y' => $point[1], 'reason' => $this->reason($entry['reason'] ?? null)];
        }

        $planKind = $raw['planKind'] ?? null;

        return [
            'planKind' => is_string($planKind) && in_array($planKind, PlanAnalysisSchema::PLAN_KINDS, true) ? $planKind : 'unknown',
            'groupRoles' => array_values($groupRoles),
            'symbols' => $symbols,
            'legend' => $legend,
            'rooms' => $rooms,
            'uncertain' => $uncertain,
            'dropped' => $dropped,
        ];
    }

    /**
     * What THIS plan's legend says. First answer per symbol (or per text, when the entry has no symbol).
     *
     * @param  array<string, string>  $texts
     * @param  list<mixed>  $entries
     * @param  array<string, int>  $dropped
     * @return list<array{symbolId: ?string, textId: string, text: string, deviceType: string}>
     */
    private function legend(PlanAnalysisInput $input, array $texts, array $entries, array &$dropped): array
    {
        $symbols = array_flip($input->symbolIds());
        $legend = [];
        $seen = [];

        foreach ($entries as $entry) {
            $symbolId = is_array($entry) ? ($entry['symbolId'] ?? null) : null;
            $textId = is_array($entry) ? ($entry['textId'] ?? null) : null;
            $type = is_array($entry) ? ($entry['deviceType'] ?? null) : null;
            $key = is_string($symbolId) ? 'symbol:'.$symbolId : 'text:'.(is_string($textId) ? $textId : '');

            if (($symbolId !== null && (! is_string($symbolId) || ! isset($symbols[$symbolId])))
                || ! is_string($textId) || ! isset($texts[$textId])
                || ! is_string($type) || ! in_array($type, KnxPlanDevice::TYPES, true)
                || isset($seen[$key])) {
                $dropped['legend']++;

                continue;
            }
            $seen[$key] = true;
            $legend[] = ['symbolId' => $symbolId, 'textId' => $textId, 'text' => trim($texts[$textId]), 'deviceType' => $type];
        }

        return $legend;
    }

    /**
     * @param  array<string, string>  $texts
     * @param  list<mixed>  $entries
     * @param  list<array{symbolId: ?string, textId: string, text: string, deviceType: string}>  $legend
     * @param  array<string, int>  $dropped
     * @return list<array<string, mixed>>
     */
    private function symbols(PlanAnalysisInput $input, array $texts, array $entries, array $legend, array &$dropped): array
    {
        $onPage = array_flip($input->symbolIds());
        $explained = array_flip(array_column($legend, 'deviceType'));
        $inLegend = $this->symbolsInLegend($input);
        $symbols = [];

        foreach ($entries as $entry) {
            $id = is_array($entry) ? ($entry['symbolId'] ?? null) : null;
            $role = is_array($entry) ? ($entry['role'] ?? null) : null;
            $type = is_array($entry) ? ($entry['deviceType'] ?? null) : null;
            if (! is_string($id) || ! isset($onPage[$id]) || isset($symbols[$id])
                || ! is_string($role) || ! in_array($role, PlanAnalysisSchema::SYMBOL_ROLES, true)
                || ($role === 'device' && (! is_string($type) || ! in_array($type, KnxPlanDevice::TYPES, true)))) {
                $dropped['symbols']++;

                continue;
            }

            // A symbol inside the legend region is the legend's sample: it explains the plan, it is
            // not installed, so it is never a device (S3: the legend is a source, not the plan).
            if ($role === 'device' && isset($inLegend[$id])) {
                $role = 'hidden';
            }
            $confidence = $this->confidence($entry['confidence'] ?? null);
            $device = $role === 'device';
            $labelTextId = $entry['labelTextId'] ?? null;
            $evidence = $device && is_string($labelTextId) && isset($texts[$labelTextId]);
            if ($device && $labelTextId !== null && ! $evidence) {
                $dropped['names']++;
            }

            $doubts = [];
            if ($device && ! isset($explained[$type])) {
                $doubts[] = 'no_legend_entry';
            }
            if ($device && $confidence < PlanAnalysisSchema::REVIEW_CONFIDENCE) {
                $doubts[] = 'low_confidence';
            }

            $symbols[$id] = [
                'symbolId' => $id,
                'role' => $role,
                'deviceType' => $device ? $type : null,
                'labelTextId' => $evidence ? $labelTextId : null,
                'label' => $evidence ? trim($texts[$labelTextId]) : null,
                'confidence' => $confidence,
                'reason' => $this->reason($entry['reason'] ?? null),
                'needsReview' => $doubts !== [],
                'doubts' => $doubts,
            ];
        }

        return array_values($symbols);
    }

    /**
     * Ids of the sent symbols whose centre lies inside the sent legend region.
     *
     * @return array<string, true>
     */
    private function symbolsInLegend(PlanAnalysisInput $input): array
    {
        $legend = $input->legend;
        if (! is_array($legend)) {
            return [];
        }
        $inside = [];
        foreach ($input->symbols as $symbol) {
            $cx = (float) $symbol['x'] + (float) $symbol['w'] / 2;
            $cy = (float) $symbol['y'] + (float) $symbol['h'] / 2;
            if ($cx >= (float) $legend['x'] && $cx <= (float) $legend['x'] + (float) $legend['w']
                && $cy >= (float) $legend['y'] && $cy <= (float) $legend['y'] + (float) $legend['h']) {
                $inside[(string) $symbol['id']] = true;
            }
        }

        return $inside;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{0: float, 1: float}|null
     */
    private function point(array $entry): ?array
    {
        $x = $entry['x'] ?? null;
        $y = $entry['y'] ?? null;
        if (! is_int($x) && ! is_float($x) || ! is_int($y) && ! is_float($y)) {
            return null;
        }
        if ($x < 0 || $x > 1 || $y < 0 || $y > 1) {
            return null;
        }

        return [(float) $x, (float) $y];
    }

    private function confidence(mixed $value): float
    {
        return is_int($value) || is_float($value) ? max(0.0, min(1.0, (float) $value)) : 0.0;
    }

    private function reason(mixed $value): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, 500) : '';
    }
}
