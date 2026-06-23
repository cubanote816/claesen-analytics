<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;

class LinkUsersToEmployeesCommand extends Command
{
    protected $signature = 'core:link-users-to-employees
        {--dry-run : Preview matches without writing to the database (default)}
        {--apply  : Actually write employee_id to matched users}';

    protected $description = 'Back-fill users.employee_id by matching users.email to employees.email (safe, one-to-one only).';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $this->info($apply ? 'Running in APPLY mode.' : 'Running in DRY-RUN mode (pass --apply to write).');

        // Index all employee emails → employee records (detect duplicates in Employee table).
        $employeesByEmail = Employee::whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->groupBy(fn (Employee $e) => strtolower(trim($e->email)));

        $users = User::whereNull('employee_id')
            ->whereNotNull('email')
            ->get();

        $linked     = 0;
        $ambiguous  = 0;
        $noMatch    = 0;

        foreach ($users as $user) {
            $key = strtolower(trim($user->email));

            if (! isset($employeesByEmail[$key])) {
                $this->line("  NO MATCH   {$user->email}");
                $noMatch++;
                continue;
            }

            $matches = $employeesByEmail[$key];

            if ($matches->count() > 1) {
                $this->warn("  AMBIGUOUS  {$user->email} — {$matches->count()} employees share this email, skipping.");
                $ambiguous++;
                continue;
            }

            $employee = $matches->first();

            // Skip if another user already holds this employee_id.
            if (User::where('employee_id', $employee->id)->exists()) {
                $this->warn("  CONFLICT   employee {$employee->id} already linked to another user, skipping {$user->email}.");
                $ambiguous++;
                continue;
            }

            $this->line("  LINK       {$user->email} → employee {$employee->id} ({$employee->name})");

            if ($apply) {
                $user->forceFill(['employee_id' => $employee->id])->saveQuietly();
            }

            $linked++;
        }

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Would link' . ($apply ? 'd' : ''), $linked],
                ['Ambiguous / skipped', $ambiguous],
                ['No employee match', $noMatch],
            ]
        );

        if (! $apply && $linked > 0) {
            $this->comment('Re-run with --apply to persist these links.');
        }

        return self::SUCCESS;
    }
}
