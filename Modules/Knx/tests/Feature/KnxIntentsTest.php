<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxIntent;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\KnxNoticeMailer;
use Tests\TestCase;

/**
 * KNX-55 — la intención por elemento, del lado de oficina.
 *
 * La identidad del elemento es el `target`, así que el `PUT` es un upsert sin id. Lo que
 * se prueba aquí es lo que hace útil el historial: cada cambio es una versión nueva, la
 * anterior no se reescribe, y una escritura que no cambia nada no ensucia el historial.
 */
final class KnxIntentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );
    }

    private function project(string $code = 'C1618'): KnxProject
    {
        return KnxProject::query()->where('code', $code)->sole();
    }

    private function board(): KnxBoard
    {
        return KnxBoard::query()
            ->where('project_id', $this->project()->getKey())
            ->orderBy('id')
            ->firstOrFail();
    }

    private function boardId(): string
    {
        return (string) ($this->board()->slug ?? $this->board()->code);
    }

    private function device(): KnxDevice
    {
        return KnxDevice::query()
            ->where('project_id', $this->project()->getKey())
            ->where('address', '1.1.111')
            ->sole();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function writeIntent(array $overrides = [], string $code = 'C1618')
    {
        $payload = array_merge([
            'target' => ['type' => 'module', 'boardId' => $this->boardId(), 'slot' => 3],
            'text' => 'Schakelt de gang met aanwezigheid en dooft na 5 min.',
        ], $overrides);

        return $this->putJson("/api/v1/knx/projects/{$code}/intents", $payload);
    }

    public function test_a_module_intent_is_written_and_read_back(): void
    {
        $payload = $this->writeIntent()->assertOk()->json();

        $this->assertSame('module', $payload['target']['type']);
        $this->assertSame($this->boardId(), $payload['target']['boardId']);
        $this->assertSame(3, $payload['target']['slot']);
        $this->assertSame(1, $payload['version']);
        $this->assertSame('L. Smet', $payload['author']['name']);
        $this->assertSame('Schakelt de gang met aanwezigheid en dooft na 5 min.', $payload['text']);

        $list = $this->getJson('/api/v1/knx/projects/C1618/intents?boardId='.urlencode($this->boardId()).'&slot=3')
            ->assertOk()
            ->json();

        $this->assertCount(1, $list);
        $this->assertSame($payload['id'], $list[0]['id']);
    }

    public function test_changing_the_text_creates_a_version_and_keeps_the_previous_one(): void
    {
        $first = $this->writeIntent()->assertOk()->json();
        $second = $this->writeIntent(['text' => 'Schakelt de gang en de inkomhal.'])->assertOk()->json();

        $this->assertSame(2, $second['version']);
        $this->assertNotSame($first['id'], $second['id'], 'cada versión es su propia fila');

        $versions = $this->getJson("/api/v1/knx/intents/{$second['id']}/versions")->assertOk()->json();

        $this->assertCount(2, $versions);
        // La última primero, y el texto viejo sigue ahí tal como se leyó.
        $this->assertSame([2, 1], array_column($versions, 'version'));
        $this->assertSame('Schakelt de gang met aanwezigheid en dooft na 5 min.', $versions[1]['text']);

        // El listado solo devuelve la vigente.
        $list = $this->getJson('/api/v1/knx/projects/C1618/intents')->assertOk()->json();
        $this->assertCount(1, $list);
        $this->assertSame(2, $list[0]['version']);
    }

    public function test_writing_the_same_text_again_does_not_add_a_version(): void
    {
        $first = $this->writeIntent()->assertOk()->json();
        $again = $this->writeIntent()->assertOk()->json();

        $this->assertSame($first['id'], $again['id']);
        $this->assertSame(1, $again['version']);
        $this->assertSame(1, KnxIntent::query()->where('project_id', $this->project()->getKey())->count());
    }

    public function test_each_change_leaves_a_typed_notice(): void
    {
        $this->writeIntent()->assertOk();

        $notice = KnxNotification::query()
            ->where('kind', KnxNotification::KIND_INTENT_CHANGED)
            ->sole();

        $this->assertSame('intent', $notice->target_type);
        $this->assertSame('L. Smet', $notice->reportedBy->shortName());
        $this->assertStringContainsString('module', (string) $notice->summary);

        // Al resumen, no al correo inmediato: la oficina ya sabe qué escribió.
        $this->assertFalse(app(KnxNoticeMailer::class)->isImmediate($notice));
    }

    public function test_a_channel_and_a_device_are_their_own_targets(): void
    {
        $module = $this->writeIntent()->assertOk()->json();

        $channel = $this->writeIntent([
            'target' => [
                'type' => 'channel',
                'boardId' => $this->boardId(),
                'slot' => 3,
                'channel' => 'kanaal 2',
            ],
        ])->assertOk()->json();

        $device = $this->writeIntent([
            'target' => ['type' => 'device', 'deviceId' => $this->device()->getKey()],
            'text' => 'Pulsgever bedient de vergaderzaal.',
        ])->assertOk()->json();

        // Tres elementos distintos, tres intenciones vigentes.
        $this->assertNotSame($module['id'], $channel['id']);
        $this->assertNotSame($module['id'], $device['id']);
        $this->assertSame('channel', $channel['target']['type']);
        $this->assertSame('device', $device['target']['type']);
        $this->assertSame((string) $this->device()->getKey(), $device['target']['deviceId']);

        $this->getJson('/api/v1/knx/projects/C1618/intents')->assertOk()->assertJsonCount(3);
        $this->getJson('/api/v1/knx/projects/C1618/intents?deviceId='.$this->device()->getKey())
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.target.type', 'device');
    }

    public function test_the_target_fields_are_required_per_type(): void
    {
        $this->putJson('/api/v1/knx/projects/C1618/intents', [
            'target' => ['type' => 'module', 'boardId' => $this->boardId()],
            'text' => 'Sin slot.',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['target.slot']]);

        $this->putJson('/api/v1/knx/projects/C1618/intents', [
            'target' => ['type' => 'channel', 'boardId' => $this->boardId(), 'slot' => 1],
            'text' => 'Sin canal.',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['target.channel']]);

        $this->putJson('/api/v1/knx/projects/C1618/intents', [
            'target' => ['type' => 'device'],
            'text' => 'Sin aparato.',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['target.deviceId']]);

        $this->putJson('/api/v1/knx/projects/C1618/intents', [
            'target' => ['type' => 'nope'],
            'text' => 'Tipo desconocido.',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['target.type']]);
    }

    public function test_a_device_from_another_project_is_a_field_error(): void
    {
        $other = KnxDevice::query()->where('project_id', '!=', $this->project()->getKey())->firstOrFail();

        $this->writeIntent([
            'target' => ['type' => 'device', 'deviceId' => $other->getKey()],
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['target.deviceId']]);
    }

    public function test_an_intent_of_another_organization_is_a_404(): void
    {
        $other = Organization::factory()->create(['slug' => 'otra-organizacion']);

        $intent = KnxIntent::factory()->create([
            'organization_id' => $other->id,
            'project_id' => $this->project()->getKey(),
        ]);

        $this->getJson("/api/v1/knx/intents/{$intent->id}/versions")->assertNotFound();
    }

    public function test_the_room_sheet_shows_the_intents_of_its_elements(): void
    {
        $device = $this->device();

        $this->writeIntent([
            'target' => ['type' => 'device', 'deviceId' => $device->getKey()],
            'roomId' => $device->room_id,
            'text' => 'Pulsgever bedient de vergaderzaal.',
        ])->assertOk();

        $sheet = $this->getJson("/api/v1/knx/projects/C1618/rooms/{$device->room_id}/sheet")
            ->assertOk()
            ->json();

        $this->assertCount(1, $sheet['intents']);
        $this->assertSame('device', $sheet['intents'][0]['target']['type']);
        $this->assertSame('Pulsgever bedient de vergaderzaal.', $sheet['intents'][0]['text']);
    }

    public function test_the_intent_endpoints_require_a_token_and_are_office_only(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/projects/C1618/intents')->assertUnauthorized();

        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );

        $this->putJson('/api/v1/knx/projects/C1618/intents', [
            'target' => ['type' => 'module', 'boardId' => $this->boardId(), 'slot' => 1],
            'text' => 'Un técnico no escribe intenciones.',
        ])->assertUnauthorized();
    }
}
