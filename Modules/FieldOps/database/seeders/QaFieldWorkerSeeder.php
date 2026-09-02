<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;

/**
 * Local QA employees for exercising FieldOps work-order assignment without
 * depending on the read-only CAFCA employee sync. IDs use a QA namespace,
 * except 100, which is the established QA Técnico employee link.
 */
class QaFieldWorkerSeeder extends Seeder
{
    /** @var array<string, array{name: string, function: string}> */
    private const WORKERS = [
        '100' => ['name' => 'QA Técnico', 'function' => 'Field technician'],
        'QA-FIELD-002' => ['name' => 'QA Field Worker 2', 'function' => 'Field technician'],
        'QA-FIELD-003' => ['name' => 'QA Field Worker 3', 'function' => 'Electrical technician'],
        'QA-FIELD-004' => ['name' => 'QA Field Worker 4', 'function' => 'Maintenance technician'],
        'QA-FIELD-005' => ['name' => 'QA Field Worker 5', 'function' => 'Field technician'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            Log::info('QaFieldWorkerSeeder: refusing to run outside local/testing.');

            return;
        }

        foreach (self::WORKERS as $id => $worker) {
            Employee::query()->updateOrCreate(
                ['id' => $id],
                [
                    'name' => $worker['name'],
                    'function' => $worker['function'],
                    'fl_active' => true,
                ],
            );
        }

        User::query()
            ->where('email', 'qa.tecnico@claesen-verlichting.test')
            ->update([
                'employee_id' => '100',
                'is_active' => true,
            ]);
    }
}
