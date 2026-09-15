<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use Modules\Prospects\DataSource\AfttPdfSource;
use Modules\Prospects\Exceptions\DataSourceException;
use Modules\Prospects\Services\ClubPersister;
use Modules\Prospects\Traits\LogsSyncEvents;
use Throwable;

class SyncAftClubsCommand extends Command
{
    use LogsSyncEvents;

    protected $signature = 'prospects:sync-aft-clubs {--user= : User ID who triggered the sync} {--history= : Existing sync history record ID}';

    // AFTT (table tennis) covers this command's real data. AFPadel (Wallonia
    // padel, afpadel.be) has no adapter yet and is a documented gap (Slice F),
    // not fabricated data.
    protected $description = 'Synchronize AFTT table tennis clubs (Wallonia). Padel (AFPadel) coverage is a documented gap.';

    public function __construct(private AfttPdfSource $source)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->guardedSync(function (): int {
            $this->startSyncLog($this->option('user'), $this->option('history'));
            $this->info('Starting AFTT clubs synchronization...');

            try {
                $clubs = $this->source->fetchClubs();
            } catch (DataSourceException $exception) {
                $this->error($exception->getMessage());
                $this->failSyncLog($exception->getMessage());

                return self::FAILURE;
            }

            $persister = new ClubPersister;

            foreach ($clubs as $club) {
                try {
                    $persister->persist($club);
                    $this->markPersisted();
                } catch (Throwable $exception) {
                    $this->markFailed();
                    $this->logSyncEvent(
                        "Error persisting AFTT club {$club->externalId}: {$exception->getMessage()}",
                        'error',
                        '❌',
                    );
                }
            }

            $this->info('AFTT synchronization completed.');
            $this->finishSyncLog($this->persistedCount);

            return self::SUCCESS;
        });
    }
}
