<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Modules\Intelligence\Services\ManualDataSyncService;
use Tests\TestCase;

/**
 * CLA-439 — verifica que cada método del orquestador llama exactamente los
 * comandos artisan esperados, en el orden correcto (clients antes que
 * complexes, ver FO-013: complexes solo importa deliveries cuyo relation_id
 * ya resolvió a un FoClient sincronizado).
 */
class ManualDataSyncServiceTest extends TestCase
{
    public function test_sync_employees_calls_the_employee_sync_command(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('app:sync-employees');

        (new ManualDataSyncService())->syncEmployees();
    }

    public function test_sync_clients_refreshes_the_relation_mirror_then_the_fieldops_bridge(): void
    {
        $calls = [];
        Artisan::shouldReceive('call')
            ->twice()
            ->andReturnUsing(function (string $command, array $params = []) use (&$calls) {
                $calls[] = [$command, $params];

                return 0;
            });

        (new ManualDataSyncService())->syncClients();

        $this->assertSame(['intelligence:sync-mirror', ['--relations' => true]], $calls[0]);
        $this->assertSame(['fieldops:sync-clients-from-relations', []], $calls[1]);
    }

    public function test_sync_complexes_refreshes_the_delivery_mirror_then_the_fieldops_bridge(): void
    {
        $calls = [];
        Artisan::shouldReceive('call')
            ->twice()
            ->andReturnUsing(function (string $command, array $params = []) use (&$calls) {
                $calls[] = [$command, $params];

                return 0;
            });

        (new ManualDataSyncService())->syncComplexes();

        $this->assertSame(['intelligence:sync-mirror', ['--deliveries' => true]], $calls[0]);
        $this->assertSame(['fieldops:sync-complexes-from-relation-deliveries', []], $calls[1]);
    }

    public function test_sync_all_runs_employees_then_clients_then_complexes(): void
    {
        $calls = [];
        Artisan::shouldReceive('call')
            ->times(5)
            ->andReturnUsing(function (string $command, array $params = []) use (&$calls) {
                $calls[] = $command;

                return 0;
            });

        (new ManualDataSyncService())->syncAll();

        $this->assertSame([
            'app:sync-employees',
            'intelligence:sync-mirror',
            'fieldops:sync-clients-from-relations',
            'intelligence:sync-mirror',
            'fieldops:sync-complexes-from-relation-deliveries',
        ], $calls);
    }
}
