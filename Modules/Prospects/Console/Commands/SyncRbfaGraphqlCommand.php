<?php

namespace Modules\Prospects\Console\Commands;

use Illuminate\Console\Command;
use Modules\Prospects\DataSource\RbfaGraphqlSource;
use Modules\Prospects\Exceptions\DataSourceException;
use Modules\Prospects\Services\ClubPersister;
use Modules\Prospects\Traits\LogsSyncEvents;
use Throwable;

class SyncRbfaGraphqlCommand extends Command
{
    use LogsSyncEvents;

    protected $signature = 'prospects:sync-rbfa-graphql
                            {--province=all : Sync a specific province or all}
                            {--limit= : Limit the number of clubs to sync}
                            {--user= : User ID who triggered the sync}
                            {--history= : Existing sync history record ID}';

    protected $description = 'Sync RBFA GraphQL data for the Prospects Module (independent of CAFCA ERP)';

    public function __construct(private RbfaGraphqlSource $source, private ClubPersister $persister)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return $this->guardedSync(function (): int {
            $this->startSyncLog($this->option('user'), $this->option('history'));
            $this->info('Starting RBFA synchronization...');

            try {
                $limit = $this->option('limit');
                $source = $this->source->selected(
                    (string) $this->option('province'),
                    $limit !== null ? (int) $limit : null,
                );
                $clubs = $source->fetchClubs();
            } catch (DataSourceException $exception) {
                $this->error($exception->getMessage());
                $this->failSyncLog($exception->getMessage());

                return self::FAILURE;
            }

            foreach ($clubs as $club) {
                try {
                    $this->persister->persist($club);
                    $this->markPersisted();
                } catch (Throwable $exception) {
                    $this->markFailed();
                    $this->logSyncEvent(
                        "Error persisting RBFA club {$club->externalId}: {$exception->getMessage()}",
                        'error',
                        '❌',
                    );
                }
            }

            $failures = $source->failures();
            foreach ($failures as $failure) {
                $this->markFailed();
                $this->logSyncEvent(
                    "Series {$failure['series']} ({$failure['province']}) failed: {$failure['status']}",
                    'error',
                    '❌',
                );
            }

            if ($failures !== []) {
                $this->syncHistory?->update(['records_count' => $this->persistedCount]);
                $this->failSyncLog('RBFA source completed with '.count($failures).' failure(s).');

                return self::FAILURE;
            }

            $this->finishSyncLog($this->persistedCount);
            $this->info('RBFA synchronization completed.');

            return self::SUCCESS;
        });
    }
}
