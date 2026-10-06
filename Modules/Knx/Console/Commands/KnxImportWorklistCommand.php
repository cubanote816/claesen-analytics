<?php

declare(strict_types=1);

namespace Modules\Knx\Console\Commands;

use Illuminate\Console\Command;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\BoardWorklistImportService;

/**
 * `knx:import-worklist <directory> <code>` — load a governed KNX worklist into a
 * project's boards (KNX-3).
 *
 * The directory holds the workspace's `linking-worklist.csv` and
 * `group-addresses.csv` (see the front's `scripts/import-board-worklist.py`). The
 * command is what turns the office's Verdelers tab from a generated import into real
 * API data; it is idempotent, so running it again refreshes the structure.
 */
class KnxImportWorklistCommand extends Command
{
    protected $signature = 'knx:import-worklist {directory : Directory holding linking-worklist.csv and group-addresses.csv}
                                               {code : Project code the worklist belongs to (e.g. 000026)}';

    protected $description = 'Import a KNX worklist (boards, modules, channels, links) into a project';

    public function handle(BoardWorklistImportService $import): int
    {
        $directory = rtrim((string) $this->argument('directory'), '/');
        $code = (string) $this->argument('code');

        foreach (['linking-worklist.csv', 'group-addresses.csv'] as $file) {
            if (! is_file($directory.'/'.$file)) {
                $this->error("Missing [{$directory}/{$file}].");

                return self::FAILURE;
            }
        }

        $project = KnxProject::query()->where('code', $code)->first();

        if ($project === null) {
            $this->error("Unknown project [{$code}].");

            return self::FAILURE;
        }

        $stats = $import->import($project, $directory);

        $this->info(sprintf(
            'Imported %d boards, %d modules, %d channels and %d links into project %s.',
            $stats['boards'],
            $stats['modules'],
            $stats['channels'],
            $stats['links'],
            $code,
        ));

        return self::SUCCESS;
    }
}
