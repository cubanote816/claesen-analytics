<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxIntent;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * KNX-55 — el campo LEE las intenciones del proyecto (también sin red).
 *
 * Escribir desde obra sigue pendiente de decisión, así que aquí se prueba lo que sí
 * existe: el listado y la ficha de habitación, con el alcance de "asignado hoy".
 */
final class KnxFieldIntentTest extends TestCase
{
    use RefreshDatabase;

    /** Taken in the fixture: a device sits at this address in C1618. */
    private const KNOWN_ADDRESS = '1.1.111';

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);
    }

    private function fieldUser(string $email = 'jan.van.dyck@electrobertels.be'): User
    {
        return User::query()->where('email', $email)->sole();
    }

    private function project(string $code = 'C1618'): KnxProject
    {
        return KnxProject::query()->where('code', $code)->sole();
    }

    private function device(): KnxDevice
    {
        return KnxDevice::query()
            ->where('project_id', $this->project()->getKey())
            ->where('address', self::KNOWN_ADDRESS)
            ->sole();
    }

    private function planToday(string $technician, string $code = 'C1618'): void
    {
        $employee = KnxEmployee::query()->where('name', $technician)->sole();

        KnxPlanningAssignment::updateOrCreate(
            ['employee_id' => $employee->getKey(), 'date' => now()->toDateString()],
            ['project_id' => $this->project($code)->getKey()],
        );
    }

    private function intentForTheDevice(): KnxIntent
    {
        $device = $this->device();

        return KnxIntent::create([
            'project_id' => $this->project()->getKey(),
            'target_type' => KnxIntent::TARGET_DEVICE,
            'target_key' => 'device:'.$device->getKey(),
            'device_id' => $device->getKey(),
            'room_id' => $device->room_id,
            'text' => 'Pulsgever bedient de vergaderzaal.',
            'version' => 1,
            'is_current' => true,
        ]);
    }

    public function test_the_field_reads_the_project_intents(): void
    {
        $intent = $this->intentForTheDevice();

        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $list = $this->getJson('/api/v1/knx/field/projects/C1618/intents')->assertOk()->json();

        $this->assertCount(1, $list);
        $this->assertSame((string) $intent->getKey(), $list[0]['id']);
        $this->assertSame('device', $list[0]['target']['type']);
        $this->assertSame('Pulsgever bedient de vergaderzaal.', $list[0]['text']);
    }

    public function test_the_field_room_sheet_shows_the_intent_of_its_device(): void
    {
        $device = $this->device();
        $this->intentForTheDevice();

        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $rooms = $this->getJson('/api/v1/knx/field/projects/C1618/rooms')->assertOk()->json();

        $sheet = collect($rooms)->firstWhere('room.id', (string) $device->room_id);

        $this->assertNotNull($sheet, 'la ficha de la habitación está en el listado');
        $this->assertCount(1, $sheet['intents']);
        $this->assertSame('Pulsgever bedient de vergaderzaal.', $sheet['intents'][0]['text']);
    }

    public function test_the_field_intents_are_scoped_to_today(): void
    {
        $this->actingAs($this->fieldUser(), 'sanctum');

        // No planned on 239870 today: the scope rule answers 403, not 404.
        $this->getJson('/api/v1/knx/field/projects/239870/intents')->assertForbidden();
    }

    public function test_writing_intents_from_the_field_is_not_implemented(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        // La ruta `pending-decision` no existe en el backend: el front no debe inventarla.
        $this->putJson('/api/v1/knx/field/projects/C1618/intents/write', [
            'target' => ['type' => 'device', 'deviceId' => $this->device()->getKey()],
            'text' => 'No debería existir.',
        ])->assertNotFound();
    }

    public function test_the_field_intents_require_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/field/projects/C1618/intents')->assertUnauthorized();
    }
}
