<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Modules\Knx\Models\KnxPlanCleanProfile;

/**
 * Turns a provider's untrusted answer into a proposal that only refers to what was sent.
 *
 * - group roles: only group ids of the input and roles of the enum; first answer per group.
 * - rooms: a point on the page; a NAME only when `textId` is a text of the input, and then
 *   the name IS that text (never the model's wording). Without evidence the name is null.
 * - uncertain: points on the page with a reason.
 * Everything dropped is counted in `dropped`, so the review can see it.
 */
class PlanAnalysisValidator
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array{groupRoles: list<array<string, mixed>>, rooms: list<array<string, mixed>>, uncertain: list<array<string, mixed>>, dropped: array<string, int>}
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

        $rooms = [];
        foreach ($raw['rooms'] as $entry) {
            $point = is_array($entry) ? $this->point($entry) : null;
            if ($point === null) {
                $dropped['rooms']++;

                continue;
            }
            $textId = $entry['textId'] ?? null;
            $evidence = is_string($textId) && isset($texts[$textId]);
            $name = $entry['name'] ?? null;
            if (! $evidence && is_string($name) && trim($name) !== '') {
                $dropped['names']++;
            }
            $rooms[] = [
                'name' => $evidence ? trim($texts[$textId]) : null,
                'textId' => $evidence ? $textId : null,
                'x' => $point[0],
                'y' => $point[1],
                'confidence' => $this->confidence($entry['confidence'] ?? null),
                'reason' => $this->reason($entry['reason'] ?? null),
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

        return [
            'groupRoles' => array_values($groupRoles),
            'rooms' => $rooms,
            'uncertain' => $uncertain,
            'dropped' => $dropped,
        ];
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
