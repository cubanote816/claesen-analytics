<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Seeders\FieldOpsDemoDataSeeder;
use Modules\FieldOps\Database\Seeders\QaTenantIsolationSeeder;
use Modules\FieldOps\Models\FoClient;
use Throwable;

/**
 * One-shot rebuild of the local dev/QA environment after a `migrate:fresh` against
 * the dev database (a routine, intentional part of this project's workflow — see
 * memory `feedback_sail_docker`). Restores base seeders + FieldOps catalogs always;
 * best-effort attempts the CAFCA/SQL Server sync chain (relations → deliveries →
 * clients → complexes → employees) and skips it gracefully when the ERP
 * (192.168.254.102) isn't reachable from the current network, instead of failing
 * the whole command.
 */
class QaResetEnvironmentCommand extends Command
{
    protected $signature = 'core:qa-reset-environment
        {--skip-cafca-sync : Skip the CAFCA/SQL Server sync chain entirely (useful when offline)}';

    protected $description = 'Rebuild the local dev/QA environment (seeders, FieldOps catalogs, CAFCA sync best-effort, QA test users) after a migrate:fresh.';

    /** @var array<int, array{0: string, 1: string}> */
    private array $summaryRows = [];

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Refusing to run outside local/testing environments.');

            return self::FAILURE;
        }

        $this->components->task('Base seeders (roles, permissions, FieldOps catalogs, maintenance types, demo users)', function () {
            Artisan::call('db:seed', ['--force' => true]);
        });

        if ($this->option('skip-cafca-sync')) {
            $this->summaryRows[] = ['CAFCA sync', '⚠️ skipped (--skip-cafca-sync)'];
        } else {
            $this->syncCafcaData();
        }

        $this->components->task('Re-checking FieldOps demo data (Stadion Bleukens hierarchy)', function () {
            Artisan::call('db:seed', ['--class' => FieldOpsDemoDataSeeder::class, '--force' => true]);
        });

        $this->createQaUsers();

        $this->components->task('Tenant isolation QA data (2nd client + complexes for both)', function () {
            Artisan::call('db:seed', ['--class' => QaTenantIsolationSeeder::class, '--force' => true]);
        });
        $this->summaryRows[] = ['qa.cliente2@claesen-verlichting.test', '✅ linked to QA Tenant B Sportclub (fake, relation_id=null)'];

        $this->newLine();
        $this->components->twoColumnDetail('<fg=white;options=bold>Summary</>', '');
        foreach ($this->summaryRows as [$label, $status]) {
            $this->components->twoColumnDetail($label, $status);
        }
        $this->newLine();

        return self::SUCCESS;
    }

    private function syncCafcaData(): void
    {
        $steps = [
            'relations (FoClient source)' => fn () => Artisan::call('intelligence:sync-mirror', ['--relations' => true]),
            'deliveries (Complex source)' => fn () => Artisan::call('intelligence:sync-mirror', ['--deliveries' => true]),
            'fieldops:sync-clients-from-relations' => fn () => Artisan::call('fieldops:sync-clients-from-relations'),
            'fieldops:sync-complexes-from-relation-deliveries' => fn () => Artisan::call('fieldops:sync-complexes-from-relation-deliveries'),
            'cafca:sync-employees' => fn () => Artisan::call('cafca:sync-employees'),
        ];

        foreach ($steps as $label => $step) {
            try {
                $step();
                $this->summaryRows[] = ["CAFCA sync: {$label}", '✅ ok'];
            } catch (Throwable $e) {
                $reason = substr($e->getMessage(), 0, 80);
                $this->summaryRows[] = ["CAFCA sync: {$label}", "⚠️ skipped ({$reason})"];
                $this->warn("CAFCA sync step [{$label}] failed, continuing: {$e->getMessage()}");

                // If the very first step (SQL Server connection itself) fails, the rest
                // will fail identically — stop wasting time on repeated timeouts.
                if (str_starts_with($label, 'relations')) {
                    $this->summaryRows[] = ['CAFCA sync: remaining steps', '⚠️ skipped (no SQL Server connectivity — run this command from a machine on the office LAN)'];

                    return;
                }
            }
        }
    }

    private function createQaUsers(): void
    {
        $backoffice = User::updateOrCreate(
            ['email' => 'qa.backoffice@claesen-verlichting.test'],
            [
                'name' => 'QA Backoffice',
                'password' => Hash::make('QaBackoffice123!'),
                'password_set_at' => now(),
                'is_active' => true,
            ]
        );
        $backoffice->syncRoles(['super_admin']);
        $this->summaryRows[] = ['qa.backoffice@claesen-verlichting.test', '✅ super_admin'];

        $tecnico = User::updateOrCreate(
            ['email' => 'qa.tecnico@claesen-verlichting.test'],
            [
                'name' => 'QA Técnico',
                'password' => Hash::make('QaTecnico123!'),
                'password_set_at' => now(),
                'is_active' => true,
                'employee_id' => '100',
            ]
        );
        $tecnico->syncRoles([]);
        $employeeExists = \Modules\Cafca\Models\Employee::query()->where('id', '100')->exists();
        $this->summaryRows[] = [
            'qa.tecnico@claesen-verlichting.test',
            $employeeExists ? '✅ employee_id=100 (linked)' : '⚠️ employee_id=100 set, but no local Employee row (needs cafca:sync-employees)',
        ];

        $foClient = FoClient::first();
        if ($foClient) {
            $cliente = User::updateOrCreate(
                ['email' => 'qa.cliente@claesen-verlichting.test'],
                [
                    'name' => 'QA Cliente',
                    'password' => Hash::make('QaCliente123!'),
                    'password_set_at' => now(),
                    'is_active' => true,
                ]
            );
            $cliente->syncRoles(['client']);
            $cliente->fieldOpsClients()->syncWithoutDetaching([
                $foClient->id => ['is_active' => true, 'can_view' => true, 'can_report' => true, 'can_manage_contacts' => false],
            ]);
            $this->summaryRows[] = ['qa.cliente@claesen-verlichting.test', "✅ linked to FoClient #{$foClient->id} ({$foClient->name})"];
        } else {
            $this->summaryRows[] = ['qa.cliente@claesen-verlichting.test', '⚠️ skipped — no FoClient synced yet (needs CAFCA sync)'];
        }
    }
}
