<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoMaintenanceFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_maintenance_record_pages_render(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $luminaire = Luminaire::factory()->create();
        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => FoMaintenanceType::factory()->preventive()->create()->id,
        ]);
        ElectricalBoard::factory()->create();

        $this->actingAs($user);

        $this->get('/fo-maintenance-records')->assertOk();
        $this->get('/fo-maintenance-records/create')->assertOk();
        $record = FoMaintenanceRecord::first();
        $this->get("/fo-maintenance-records/{$record->id}/edit")->assertOk();

        $this->get('/catalogs/fo-maintenance-types')->assertOk();
        $this->get('/catalogs/fo-maintenance-types/create')->assertOk();
    }
}
