<?php

namespace App\Console\Commands;

use App\Services\Cafca\EmployeeSyncService;
use Illuminate\Console\Command;

class SyncEmployeesCommand extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'app:sync-employees';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Synchronize employees from Legacy SQL Server to local MySQL';

    /**
     * Execute the console command.
     */
    public function handle(EmployeeSyncService $syncService): int
    {
        $this->info('Starting synchronization of technicians...');

        try {
            $stats = $syncService->sync();

            $this->table(
                ['Created', 'Updated', 'Errors'],
                [[$stats['created'], $stats['updated'], $stats['errors']]]
            );

            if ($stats['created'] === 0 && $stats['updated'] === 0) {
                $this->info('All records are already up to date.');
            } else {
                $this->info('Synchronization completed successfully.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Synchronization failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
