<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use Modules\Prospects\DataSource\BrusselsCadastreSource;
use Modules\Prospects\Exceptions\DataSourceException;
use Modules\Prospects\Services\ClubPersister;
use Modules\Prospects\Traits\LogsSyncEvents;

class SyncBrusselsClubsCommand extends Command
{
    use LogsSyncEvents;

    protected $signature = 'prospects:sync-brussels-clubs {--user= : User ID who triggered the sync} {--history= : Existing sync history record ID}';

    protected $description = 'Sync Brussels-Capital sports infrastructure cadastre (auxiliary, venue-only coverage).';

    public function __construct(private BrusselsCadastreSource $source)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->guardedSync(function (): int {
            $this->startSyncLog($this->option('user'), $this->option('history'));
            $this->info('Starting Brussels cadastre synchronization...');

            try {
                $clubs = $this->source->fetchClubs();
            } catch (DataSourceException $exception) {
                $this->error($exception->getMessage());
                $this->failSyncLog($exception->getMessage());

                return self::FAILURE;
            }

            $tally = (new ClubPersister)->persistAll($clubs);
            $this->persistedCount = $tally['persisted'];
            $this->failedCount = $tally['failed'];

            if ($tally['failed'] > 0) {
                $this->logSyncEvent(
                    "{$tally['failed']} Brussels record(s) failed to persist.",
                    'error',
                    '❌',
                );
            }

            $this->info('Brussels cadastre synchronization completed.');
            $this->finishSyncLog($tally['persisted']);

            return self::SUCCESS;
        });
    }
}
