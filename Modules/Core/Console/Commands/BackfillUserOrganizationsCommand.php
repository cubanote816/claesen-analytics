<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;

/**
 * F1/P2 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Assigns every existing user to the Claesen organization. Idempotent (only
 * ever touches organization_id IS NULL) and resumable (safe to interrupt and
 * re-run; chunked so a large table doesn't need a single unbounded query).
 *
 * Rollback: `UPDATE users SET organization_id = NULL WHERE organization_id = ?`
 * with Claesen's id — safe because nothing reads users.organization_id yet
 * (enforcement lands in phase P5, gated by config('organizations.enforce')).
 */
class BackfillUserOrganizationsCommand extends Command
{
    protected $signature = 'core:backfill-user-organizations
        {--dry-run : Preview counts without writing to the database (default)}
        {--apply   : Actually write organization_id to unassigned users}';

    protected $description = 'Back-fill users.organization_id, assigning every unassigned user to the Claesen organization.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Running in APPLY mode.' : 'Running in DRY-RUN mode (pass --apply to write).');

        $claesenId = Organization::claesenId();

        $totalUsers = User::query()->count();
        $unassignedBefore = User::query()->whereNull('organization_id')->count();

        if ($unassignedBefore === 0) {
            $this->comment('No users without an organization — nothing to do.');
            $this->table(
                ['Result', 'Count'],
                [
                    ['Total users', $totalUsers],
                    ['Without organization (before)', 0],
                    ['Without organization (after)', 0],
                ]
            );

            return self::SUCCESS;
        }

        $assigned = 0;

        if ($apply) {
            User::query()
                ->whereNull('organization_id')
                ->chunkById(500, function ($users) use ($claesenId, &$assigned): void {
                    foreach ($users as $user) {
                        $user->forceFill(['organization_id' => $claesenId])->saveQuietly();
                        $assigned++;
                    }
                });
        } else {
            $assigned = $unassignedBefore;
        }

        $unassignedAfter = $apply ? User::query()->whereNull('organization_id')->count() : $unassignedBefore;

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Total users', $totalUsers],
                ['Without organization (before)', $unassignedBefore],
                ['Would assign'.($apply ? 'ed' : ''), $assigned],
                ['Without organization (after)', $unassignedAfter],
            ]
        );

        if (! $apply) {
            $this->comment('Re-run with --apply to persist these assignments.');
        } elseif ($unassignedAfter > 0) {
            $this->warn("{$unassignedAfter} user(s) still have no organization — re-run this command to resume.");
        } else {
            $this->info('All users are now assigned to an organization.');
        }

        return self::SUCCESS;
    }
}
