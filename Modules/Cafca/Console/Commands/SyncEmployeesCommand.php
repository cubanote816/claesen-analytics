<?php

namespace Modules\Cafca\Console\Commands;

use Illuminate\Console\Command;
use Modules\Cafca\Services\Cafca\EmployeeSyncService;

class SyncEmployeesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cafca:sync-employees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize employees from Legacy SQL Server to local MySQL database.';

    /**
     * Execute the console command.
     */
    public function handle(EmployeeSyncService $syncService): int
    {
        $this->info(__('employees/resource.actions.sync.command.starting'));

        try {
            $stats = $syncService->sync();

            if ($stats['created'] === 0 && $stats['updated'] === 0 && $stats['errors'] === 0) {
                $this->comment(__('employees/resource.actions.sync.command.up_to_date'));
            } else {
                $this->table(
                    [
                        __('employees/resource.actions.sync.command.table.created'),
                        __('employees/resource.actions.sync.command.table.updated'),
                        __('employees/resource.actions.sync.command.table.errors'),
                    ],
                    [
                        [
                            $stats['created'],
                            $stats['updated'],
                            $stats['errors'],
                        ]
                    ]
                );

                $this->info(__('employees/resource.actions.sync.command.success'));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(__('employees/resource.actions.sync.command.failed', ['error' => $e->getMessage()]));
            return Command::FAILURE;
        }
    }
}
