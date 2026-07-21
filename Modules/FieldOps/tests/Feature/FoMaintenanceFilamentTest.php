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
        $this->get("/fo-maintenance-records/{$record->id}")->assertOk();
        $this->get("/fo-maintenance-records/{$record->id}/edit")->assertOk();

        $this->get('/catalogs/fo-maintenance-types')->assertOk();
        $this->get('/catalogs/fo-maintenance-types/create')->assertOk();
    }

    public function test_view_page_renders_emergency_client_reported_record(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $board = ElectricalBoard::factory()->create();
        $record = FoMaintenanceRecord::factory()->forMaintainable($board)->clientReported()->create();

        $this->get("/fo-maintenance-records/{$record->id}")->assertOk();
    }

    public function test_view_page_renders_resolved_preventive_record_without_incident_section(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create();
        $record = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => FoMaintenanceType::factory()->preventive()->create()->id,
            'problem_reported_at' => null,
            'problem_solved_at' => null,
        ]);

        $this->get("/fo-maintenance-records/{$record->id}")->assertOk();
    }

    public function test_luminaire_scoped_history_and_create_navigation_render(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $target = Luminaire::factory()->create();
        $other = Luminaire::factory()->create();
        $targetType = FoMaintenanceType::factory()->preventive()->create();
        $targetType->setTranslation('name', 'en', 'Target inspection')->save();
        $otherType = FoMaintenanceType::factory()->corrective()->create();
        $otherType->setTranslation('name', 'en', 'Other inspection')->save();

        FoMaintenanceRecord::factory()->forMaintainable($target)->create(['fo_maintenance_type_id' => $targetType->id]);
        FoMaintenanceRecord::factory()->forMaintainable($other)->create(['fo_maintenance_type_id' => $otherType->id]);

        $this->withHeader('Accept-Language', 'en-US')->get("/fo-maintenance-records?luminaire={$target->id}")
            ->assertOk()
            ->assertSee('Target inspection')
            ->assertDontSee('Other inspection')
            ->assertSee('Back to luminaire');

        $createUrl = '/fo-maintenance-records/create?'.http_build_query([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $target->id,
            'return_luminaire' => $target->id,
        ]);

        $this->withHeader('Accept-Language', 'en-US')->get($createUrl)
            ->assertOk()
            ->assertSee($target->serial_number);
    }
}
