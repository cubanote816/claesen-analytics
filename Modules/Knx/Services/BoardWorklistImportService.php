<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxProject;

/**
 * Imports a governed KNX worklist into a project's boards (KNX-3).
 *
 * The worklist is the project's own truth — it lives in the governed KNX workspace as
 * `linking-worklist.csv` (one row per object/group-address link) plus
 * `group-addresses.csv` (which carries each group address's machine `Role`). The
 * front used to import it into a generated TypeScript file; the boards endpoint needs
 * the same data in the database.
 *
 * Two derivations, both measured against the real 160-row worklist:
 *
 *   - **Board identity** is the slug of the box name (`Str::slug` gives exactly the
 *     string the front's own importer produced), so a marker's `boardId` points at
 *     the same board.
 *   - **Module instance identity** is `slot`: the 1-based position in the board, in
 *     worklist order (which follows the plan of the cabinet). A channel that repeats
 *     for the same order number is the KNX signal that a *second* physical module of
 *     that type started, so the series splits there. In the project's own worklist no
 *     channel repeats, so each order number is one module — the 13 the measurement
 *     counted.
 *
 * The import is idempotent: the board row (and its id) is reused, its modules are
 * replaced whole. Running it twice leaves the same structure.
 */
class BoardWorklistImportService
{
    /**
     * The level the box name carries as a suffix: (GLV) ground floor, (V01) first,
     * (V02) second. The worklist has no explicit floor column, so it is derived —
     * the same derivation the front's import script documents.
     */
    private const FLOOR_BY_SUFFIX = [
        'GLV' => 'gelijkvloers',
        'V01' => '1e verdieping',
        'V02' => '2e verdieping',
    ];

    /**
     * @return array{boards: int, modules: int, channels: int, links: int}
     */
    public function import(KnxProject $project, string $directory): array
    {
        $directory = rtrim($directory, '/');

        $worklist = $this->readCsv($directory.'/linking-worklist.csv');

        $roles = [];

        foreach ($this->readCsv($directory.'/group-addresses.csv') as $row) {
            $address = trim((string) ($row['Address'] ?? ''));

            if ($address !== '') {
                $roles[$address] = trim((string) ($row['Role'] ?? ''));
            }
        }

        $boards = $this->group($worklist, $roles);

        return DB::transaction(function () use ($project, $boards): array {
            $stats = ['boards' => 0, 'modules' => 0, 'channels' => 0, 'links' => 0];
            $usedCodes = [];

            foreach ($boards as $board) {
                $model = KnxBoard::updateOrCreate(
                    ['project_id' => $project->getKey(), 'slug' => $board['slug']],
                    [
                        'code' => $this->uniqueCode($board['name'], $usedCodes),
                        'name' => $board['name'],
                        'floor' => $board['floor'],
                    ],
                );

                // The board row is reused (so a device pointing at it keeps doing so)
                // and its structure is replaced: that is what makes a re-import safe.
                $model->modules()->delete();

                foreach ($board['modules'] as $index => $module) {
                    $created = $model->modules()->create([
                        'device' => $module['device'],
                        'slot' => $index + 1,
                    ]);

                    foreach ($module['links'] as $position => $link) {
                        $created->links()->create($link + ['position' => $position]);
                    }

                    $stats['modules']++;
                    $stats['links'] += count($module['links']);
                    $stats['channels'] += count(array_unique(array_column($module['links'], 'channel')));
                }

                $stats['boards']++;
            }

            return $stats;
        });
    }

    /**
     * Group the worklist rows into boards, modules and links.
     *
     * @param  array<int, array<string, string>>  $worklist
     * @param  array<string, string>  $roles
     * @return array<int, array{slug: string, name: string, floor: string|null, modules: array<int, array{device: string, links: array<int, array<string, string>>}>}>
     */
    private function group(array $worklist, array $roles): array
    {
        $boards = [];
        $boardIndex = [];
        // "slug|device" → the module currently open for that order number on that
        // board, and the highest channel letter seen on it.
        $open = [];

        foreach ($worklist as $row) {
            $box = trim((string) ($row['Box'] ?? ''));
            $device = trim((string) ($row['Device'] ?? ''));
            $channel = trim((string) ($row['Channel'] ?? ''));

            if ($box === '' || $device === '' || $channel === '') {
                continue;
            }

            $slug = Str::slug($box);

            if (! isset($boardIndex[$slug])) {
                $boardIndex[$slug] = count($boards);
                $boards[] = [
                    'slug' => $slug,
                    'name' => $box,
                    'floor' => $this->floorOf($box),
                    'modules' => [],
                ];
            }

            $board = $boardIndex[$slug];
            $key = $slug.'|'.$device;
            $state = $open[$key] ?? null;

            // The worklist lists a module's channels in order (A, A, B, C…; the repeat
            // is the channel's second communication object). A channel that goes
            // *backwards* means a second physical module of the same order number
            // started, so the series splits and a new slot begins.
            if ($state === null || strcmp($channel, (string) $state['max']) < 0) {
                $boards[$board]['modules'][] = ['device' => $device, 'links' => []];
                $state = ['index' => count($boards[$board]['modules']) - 1, 'max' => $channel];
            } elseif (strcmp($channel, (string) $state['max']) > 0) {
                $state['max'] = $channel;
            }

            $ga = trim((string) ($row['GA'] ?? ''));

            $boards[$board]['modules'][$state['index']]['links'][] = [
                'channel' => $channel,
                'room' => trim((string) ($row['Room'] ?? '')),
                'object' => trim((string) ($row['Object'] ?? '')),
                // The Role lives in the group-address list, not in the worklist; a
                // missing one is `unknown`, which is a real answer, not a crash.
                'role' => $roles[$ga] ?? 'unknown',
                'ga' => $ga,
                'ga_name' => trim((string) ($row['GA_Name'] ?? '')),
                'dpt' => trim((string) ($row['DPT'] ?? '')),
            ];

            $open[$key] = $state;
        }

        return $boards;
    }

    /**
     * The board's short code, which is NOT NULL and unique per project: the first
     * segment of the box name ("Caja 1 - …" → "caja-1"), uniquified when two boxes
     * share it. The slug stays the identity; the code is only the short label.
     *
     * @param  array<string, int>  $used
     */
    private function uniqueCode(string $box, array &$used): string
    {
        $base = Str::slug(explode(' - ', $box)[0]);
        $base = mb_substr($base === '' ? 'bord' : $base, 0, 32);

        $used[$base] = ($used[$base] ?? 0) + 1;

        return $used[$base] === 1 ? $base : mb_substr($base, 0, 29).'-'.$used[$base];
    }

    private function floorOf(string $box): ?string
    {
        foreach (self::FLOOR_BY_SUFFIX as $suffix => $floor) {
            if (str_ends_with(rtrim($box), '('.$suffix.')')) {
                return $floor;
            }
        }

        return null;
    }

    /**
     * Read a worklist CSV. The governed workspace writes a UTF-8 BOM, which would
     * otherwise become part of the first header ("\u{FEFF}Box") and make every lookup
     * miss in silence.
     *
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];

        if ($lines === []) {
            return [];
        }

        $header = array_map('trim', str_getcsv((string) array_shift($lines)));

        if ($header === []) {
            return [];
        }

        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);

            if (count($values) < count($header)) {
                continue;
            }

            $rows[] = array_combine($header, array_slice($values, 0, count($header))) ?: [];
        }

        return $rows;
    }
}
