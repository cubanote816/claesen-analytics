<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Database\Seeders\QaFieldWorkerSeeder;
use Tests\TestCase;

class QaFieldWorkerSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_five_active_workers_and_links_qa_tecnico_idempotently(): void
    {
        $qaTechnician = UserFactory::new()->create([
            'email' => 'qa.tecnico@claesen-verlichting.test',
            'name' => 'QA Técnico',
            'employee_id' => null,
            'is_active' => false,
        ]);

        $this->seed(QaFieldWorkerSeeder::class);
        $this->seed(QaFieldWorkerSeeder::class);

        self::assertSame(5, Employee::query()->count());
        self::assertSame(5, Employee::query()->where('fl_active', true)->count());
        $this->assertDatabaseHas('employees', [
            'id' => '100',
            'name' => 'QA Técnico',
            'fl_active' => true,
        ]);
        self::assertSame('100', $qaTechnician->fresh()->employee_id);
        self::assertTrue((bool) $qaTechnician->fresh()->is_active);
    }
}
