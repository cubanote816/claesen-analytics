<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceWorkOrderStatus;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\MaintenanceWorkOrders\Pages\CreateMaintenanceWorkOrder;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenancePlan;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceWorkOrderFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($mock) => $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_global_queue_and_contextual_creation_render(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create([
            'status' => MaintenanceWorkOrderStatus::AWAITING_VALIDATION,
        ]);
        $this->actingAs($user);

        $this->withHeader('Accept-Language', 'en-US')
            ->get(FoMaintenanceWorkOrderResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Work orders')
            ->assertDontSee('New work order');

        $this->withHeader('Accept-Language', 'en-US')
            ->get(FoMaintenanceWorkOrderResource::getUrl('create', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $luminaire->id,
            ]))->assertOk()->assertSee($luminaire->serial_number);

        $this->withHeader('Accept-Language', 'en-US')
            ->get(FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('Return for correction')
            ->assertSee('Operational timeline');
        $this->get('/fo-maintenance-plans')->assertOk();
    }

    public function test_luminaire_page_links_to_contextual_work_order_instead_of_direct_history_creation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $order = FoMaintenanceWorkOrder::factory()->forMaintainable($luminaire)->create();
        $this->actingAs($user);

        $expected = FoMaintenanceWorkOrderResource::getUrl('create', [
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
        ]);

        $this->withHeader('Accept-Language', 'en-US')
            ->get("/luminaires/{$luminaire->id}")
            ->assertOk()
            ->assertSee('Schedule maintenance')
            ->assertSee('Open work orders')
            ->assertSee($expected, false)
            ->assertSee(FoMaintenanceWorkOrderResource::getUrl('view', ['record' => $order]), false);
    }

    public function test_contextual_filament_form_creates_one_order_and_recurring_plan(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $luminaire = $this->luminaireWithClientContext();
        $type = FoMaintenanceType::factory()->preventive()->create();
        $this->actingAs($user);

        Livewire::withQueryParams([
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $luminaire->id,
        ])->test(CreateMaintenanceWorkOrder::class)
            ->fillForm([
                'fo_maintenance_type_id' => $type->id,
                'scheduled_for' => now()->addWeek()->startOfHour(),
                'priority' => 'medium',
                'instructions' => 'Inspect optics and clean the luminaire.',
                'recurrence_unit' => 'months',
                'recurrence_interval' => 6,
            ])->call('create')->assertHasNoFormErrors();

        $order = FoMaintenanceWorkOrder::firstOrFail();
        self::assertSame($luminaire->id, $order->maintainable_id);
        self::assertSame($luminaire->luminaire_position_id, $order->luminaire_position_id);
        self::assertSame(1, FoMaintenancePlan::count());
        self::assertSame(FoMaintenancePlan::firstOrFail()->id, $order->maintenance_plan_id);
        $this->get('/fo-maintenance-plans')->assertOk()->assertSee('6 month(s)');
    }

    private function luminaireWithClientContext(): Luminaire
    {
        $client = FoClient::factory()->create();
        $complex = Complex::factory()->create(['client_id' => $client->id]);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure);

        return Luminaire::factory()->create(['luminaire_frame_id' => $frame->id]);
    }
}
