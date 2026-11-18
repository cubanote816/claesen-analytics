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
 * even the simulator never invents a name. A page no example matches gets an empty
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
                'groupRoles' => [],
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
                    'confidence' => (float) $room['confidence'],
                    'reason' => (string) $room['reason'],
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

        return new PlanAnalysisResult(self::ID, 'synthetic-examples', [
            'groupRoles' => $groupRoles,
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
