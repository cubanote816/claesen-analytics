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
    protected $description = 'Synchronizeer techniekers vanuit Legacy SQL Server naar lokale MySQL';

    /**
     * Execute the console command.
     */
    public function handle(EmployeeSyncService $syncService): int
    {
        $this->info(__('employees/resource.actions.sync.command.starting'));

        try {
            $stats = $syncService->sync();

            $this->table(
                [
                    __('employees/resource.actions.sync.command.table.created'),
                    __('employees/resource.actions.sync.command.table.updated'),
                    __('employees/resource.actions.sync.command.table.errors'),
                ],
                [[$stats['created'], $stats['updated'], $stats['errors']]]
            );

            if ($stats['created'] === 0 && $stats['updated'] === 0) {
                $this->info(__('employees/resource.actions.sync.command.up_to_date'));
            } else {
                $this->info(__('employees/resource.actions.sync.command.success'));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(__('employees/resource.actions.sync.command.failed', ['error' => $e->getMessage()]));
            return Command::FAILURE;
        }
    }
}
