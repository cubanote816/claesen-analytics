<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

/**
 * A provider that answers from SYNTHETIC examples (Modules/Knx/resources/plan-analysis/synthetic):
 * it never leaves the server, costs nothing and only runs in local and testing.
 *
 * An example applies when every group id it names in `match.groupIds` is on the page.
 * Rooms and uncertain points name a text of the example; they are placed at that text
 * of the INPUT (its id and position), and skipped when the page has no such text, so
 * even the simulator never invents a name. Symbols and legend entries follow the same
 * rule: they appear only when the request has that symbol id and that text (plan-analysis/2). A page no example matches gets an empty
 * proposal with one uncertain point that says so.
 */
class SyntheticPlanAnalysisProvider implements PlanAnalysisProvider
{
    public const ID = 'synthetic';

    public function __construct(private readonly PlanAnalysisProviders $providers) {}

    public function id(): string
    {
        return self::ID;
    }

    public function analyze(PlanAnalysisInput $input, callable $maySend): PlanAnalysisResult
    {
        if (! $this->providers->isAvailable(self::ID)) {
            throw new PlanAnalysisFailed('unavailable', 'The synthetic plan-analysis provider only runs in local and testing.');
        }
        if (! $maySend()) {
            throw new PlanAnalysisFailed('not_authorized', 'This document is not authorized for this provider.');
        }

        $example = $this->exampleFor($input);
        if ($example === null) {
            return new PlanAnalysisResult(self::ID, 'synthetic-examples', [
                'planKind' => 'unknown',
                'groupRoles' => [],
                'symbols' => [],
                'legend' => [],
                'rooms' => [],
                'uncertain' => [['x' => 0.5, 'y' => 0.5, 'reason' => 'No synthetic example matches this page.']],
            ]);
        }

        $onPage = array_flip($input->groupIds());
        $groupRoles = array_values(array_filter(
            (array) $example['groupRoles'],
            fn (array $g): bool => isset($onPage[$g['groupId']]),
        ));

        $rooms = [];
        foreach ((array) $example['rooms'] as $room) {
            $text = $this->text($input, (string) $room['text']);
            if ($text !== null) {
                $rooms[] = [
                    'name' => $text['text'],
                    'textId' => $text['id'],
                    'x' => $text['x'],
                    'y' => $text['y'],
                    'kind' => (string) ($room['kind'] ?? 'other'),
                    'confidence' => (float) $room['confidence'],
                    'reason' => (string) $room['reason'],
                    'geometryNullReason' => null,
                ];
            }
        }

        $uncertain = [];
        foreach ((array) $example['uncertain'] as $point) {
            $text = $this->text($input, (string) $point['text']);
            if ($text !== null) {
                $uncertain[] = ['x' => $text['x'], 'y' => $text['y'], 'reason' => (string) $point['reason']];
            }
        }

        $symbolsOnPage = array_flip($input->symbolIds());
        $symbols = [];
        foreach ((array) ($example['symbols'] ?? []) as $symbol) {
            if (! isset($symbolsOnPage[$symbol['symbolId']])) {
                continue;
            }
            $label = isset($symbol['labelText']) ? $this->text($input, (string) $symbol['labelText']) : null;
            $symbols[] = [
                'symbolId' => $symbol['symbolId'],
                'role' => $symbol['role'],
                'deviceType' => $symbol['deviceType'] ?? null,
                'labelTextId' => $label['id'] ?? null,
                'confidence' => (float) $symbol['confidence'],
                'reason' => (string) $symbol['reason'],
            ];
        }

        $legend = [];
        foreach ((array) ($example['legend'] ?? []) as $entry) {
            $text = $this->text($input, (string) $entry['text']);
            if ($text !== null && (($entry['symbolId'] ?? null) === null || isset($symbolsOnPage[$entry['symbolId']]))) {
                $legend[] = ['symbolId' => $entry['symbolId'] ?? null, 'textId' => $text['id'], 'deviceType' => $entry['deviceType']];
            }
        }

        return new PlanAnalysisResult(self::ID, 'synthetic-examples', [
            'planKind' => (string) ($example['planKind'] ?? 'unknown'),
            'groupRoles' => $groupRoles,
            'symbols' => $symbols,
            'legend' => $legend,
            'rooms' => $rooms,
            'uncertain' => $uncertain,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function exampleFor(PlanAnalysisInput $input): ?array
    {
        $onPage = array_flip($input->groupIds());

        foreach (glob(module_path('Knx', 'resources/plan-analysis/synthetic/*.json')) ?: [] as $file) {
            $example = json_decode((string) file_get_contents($file), true);
            if (! is_array($example)) {
                continue;
            }
            $needed = (array) ($example['match']['groupIds'] ?? []);
            if ($needed !== [] && array_diff_key(array_flip($needed), $onPage) === []) {
                return $example;
            }
        }

        return null;
    }

    /**
     * The first input text equal to `$name` (trimmed, case-sensitive), or null.
     *
     * @return array{id: string, text: string, x: float, y: float}|null
     */
    private function text(PlanAnalysisInput $input, string $name): ?array
    {
        foreach ($input->texts as $text) {
            if (trim($text['text']) === $name) {
                return $text;
            }
        }

        return null;
    }
}
