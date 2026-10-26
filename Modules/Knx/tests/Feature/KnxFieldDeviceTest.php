<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Tests\TestCase;

/**
 * V11.c — registering an apparatus from site (CLA-609).
 *
 * The app works offline, so the same registration can arrive twice or arrive after
 * somebody else took the address. Those two situations are what this endpoint is
 * really about, and they are what these tests exercise.
 */
final class KnxFieldDeviceTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'c0ffee00-1111-4222-8333-444455556666';

    /** Free in the fixture: C1618's 17 devices run from 1.1.100 to 1.1.116. */
    private const FREE_ADDRESS = '1.1.150';

    /** Taken in the fixture (device index 11). */
    private const TAKEN_ADDRESS = '1.1.111';

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        // The photos are files: a fake disk keeps them out of the working tree while
        // still letting the test assert what was written.
        Storage::fake('local');
    }

    private function fieldUser(string $email = 'jan.van.dyck@electrobertels.be'): User
    {
        return User::query()->where('email', $email)->sole();
    }

    private function officeUser(): User
    {
        return User::query()->where('email', 'lien.smet@electrobertels.be')->sole();
    }

    private function project(string $code = 'C1618'): KnxProject
    {
        return KnxProject::query()->where('code', $code)->sole();
    }

    private function roomId(string $name = 'Vergaderzaal', string $code = 'C1618'): string
    {
        return (string) KnxProjectRoom::query()
            ->where('project_id', $this->project($code)->getKey())
            ->where('name', $name)
            ->sole()
            ->getKey();
    }

    /** The fixture plans Monday to Friday; today needs a row of its own. */
    private function planToday(string $technician, string $code = 'C1618'): void
    {
        $employee = KnxEmployee::query()->where('name', $technician)->sole();

        KnxPlanningAssignment::updateOrCreate(
            ['employee_id' => $employee->getKey(), 'date' => now()->toDateString()],
            ['project_id' => $this->project($code)->getKey()],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'clientId' => self::CLIENT_ID,
            'projectCode' => 'C1618',
            'address' => self::FREE_ADDRESS,
            'type' => 'Drukknop 4-voudig',
            'roomId' => $this->roomId(),
            'roomName' => 'Vergaderzaal',
            'floor' => 'Gelijkvloers',
            'boardCode' => 'E10',
            'serial' => '00B2-4471',
            'note' => null,
            'photoDataUrl' => null,
            'capturedAt' => now()->subMinutes(5)->toIso8601String(),
        ], $overrides);
    }

    private function register(array $overrides = [])
    {
        return $this->postJson('/api/v1/knx/field/projects/C1618/devices', $this->payload($overrides));
    }

    public function test_registering_a_device_returns_the_contract_shape_and_creates_it(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $capturedAt = now()->subMinutes(5);

        $payload = $this->register(['capturedAt' => $capturedAt->toIso8601String()])
            ->assertCreated()
            ->json();

        $this->assertSame(
            ['id', 'address', 'type', 'roomName', 'boardCode', 'serial', 'registeredBy', 'registeredAt'],
            array_keys($payload),
        );
        $this->assertSame(self::FREE_ADDRESS, $payload['address']);
        $this->assertSame('Drukknop 4-voudig', $payload['type']);
        $this->assertSame('Vergaderzaal', $payload['roomName']);
        $this->assertSame('E10', $payload['boardCode']);
        $this->assertSame('00B2-4471', $payload['serial']);
        $this->assertSame('J. Van Dyck', $payload['registeredBy']);

        $device = KnxDevice::query()->where('client_id', self::CLIENT_ID)->sole();

        $this->assertSame(KnxDevice::SOURCE_FIELD, $device->source);
        $this->assertSame(self::FREE_ADDRESS, $device->address);
        // Not acknowledged: that is what makes the office dossier show it as new.
        $this->assertNull($device->acknowledged_at);
        $this->assertSame('Jan Van Dyck', $device->registeredBy->name);
        $this->assertSame($capturedAt->format('Y-m-d H:i'), $device->captured_at->format('Y-m-d H:i'));

        // The registration is also the event the office's field inbox has to confirm.
        $this->assertTrue(
            KnxNotification::query()->where('device_id', $device->getKey())->exists(),
            'a field registration creates the notification the inbox shows',
        );
    }

    public function test_the_registered_device_shows_up_in_the_office_dossier_as_new(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');
        $this->register()->assertCreated();

        // The office sees the same row, flagged as a field registration waiting for
        // confirmation. This is the join between the two apps.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->officeUser(), 'sanctum');

        $devices = $this->getJson('/api/v1/knx/projects/C1618/devices')->assertOk()->json();
        $fresh = collect($devices)->firstWhere('address', self::FREE_ADDRESS);

        $this->assertNotNull($fresh);
        $this->assertTrue($fresh['isNew']);
        $this->assertSame('field', $fresh['source']);
        $this->assertSame('J. Van Dyck', $fresh['registeredBy']);
    }

    public function test_the_same_client_id_never_creates_a_second_device(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $first = $this->register()->assertCreated()->json();

        $devicesBefore = KnxDevice::query()->count();
        $notificationsBefore = KnxNotification::query()->count();

        // The app retried after a dropped connection: same key, same answer, and 200
        // rather than 201 because nothing new was created.
        $second = $this->register()->assertOk()->json();

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame($devicesBefore, KnxDevice::query()->count());
        $this->assertSame($notificationsBefore, KnxNotification::query()->count());
    }

    public function test_an_address_already_in_use_answers_409_with_the_existing_device(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $response = $this->register(['address' => self::TAKEN_ADDRESS])->assertStatus(409);

        $response->assertJsonPath('code', 'address_in_use');
        $response->assertJsonPath('existing.address', self::TAKEN_ADDRESS);

        // The field client reads `details` = `errors ?? payload`, so the device has to
        // be inside `errors` to be found; the contract's own example also puts it at
        // the top level. Both readings work.
        $this->assertSame(self::TAKEN_ADDRESS, $response->json('errors.existing.address'));

        $this->assertSame(
            ['id', 'address', 'type', 'roomName', 'boardCode', 'serial', 'registeredBy', 'registeredAt'],
            array_keys($response->json('existing')),
        );

        // Nothing was created…
        $this->assertFalse(KnxDevice::query()->where('client_id', self::CLIENT_ID)->exists());

        // …but the attempt is not lost: the office gets a conflict to work through.
        $conflict = KnxConflict::query()->where('client_id', self::CLIENT_ID)->sole();

        $this->assertSame('duplicate_address', $conflict->type);
        $this->assertSame('critical', $conflict->severity);
        $this->assertSame(self::TAKEN_ADDRESS, $conflict->address);
        $this->assertSame('open', $conflict->status);
        $this->assertSame($this->project()->getKey(), $conflict->project_id);
        $this->assertSame('Jan Van Dyck', $conflict->reportedBy->name);

        // Both descriptions are human text the Conflictencentrum displays: what is
        // registered, and what came from site.
        $this->assertStringContainsString('Vergaderzaal', (string) $conflict->device_existing);
        $this->assertStringContainsString('Drukknop 4-voudig', (string) $conflict->device_field);

        // The history opens with the report itself.
        $this->assertSame(['reported'], $conflict->logs()->pluck('action')->all());
    }

    public function test_a_retried_collision_is_recorded_once(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->register(['address' => self::TAKEN_ADDRESS])->assertStatus(409);
        $this->register(['address' => self::TAKEN_ADDRESS])->assertStatus(409);

        // The office's worklist must not fill up with the same attempt repeated.
        $this->assertSame(1, KnxConflict::query()->where('client_id', self::CLIENT_ID)->count());
    }

    public function test_the_photo_is_stored_once_and_kept_as_evidence_of_the_attempt(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        // The two encodings the app can produce: what a camera gives through a canvas…
        $png = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

        $this->register(['photoDataUrl' => $png])->assertCreated();

        Storage::disk('local')->assertExists('knx/devices/'.self::CLIENT_ID.'.png');

        // …and the inline SVG its own fixture generates.
        $svg = 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg"/>');

        $this->register(['clientId' => 'aaaa1111-2222-4333-8444-555566667777', 'address' => '1.1.160', 'photoDataUrl' => $svg])
            ->assertCreated();

        Storage::disk('local')->assertExists('knx/devices/aaaa1111-2222-4333-8444-555566667777.svg');
    }

    public function test_a_photo_of_the_losing_attempt_is_kept_with_the_conflict(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->register([
            'address' => self::TAKEN_ADDRESS,
            'photoDataUrl' => 'data:image/png;base64,'.base64_encode('fake-png-bytes'),
        ])->assertStatus(409);

        // The photo of a device that could not be registered is the evidence the office
        // needs to decide who is right, so it belongs to the conflict, not nowhere.
        $conflict = KnxConflict::query()->where('client_id', self::CLIENT_ID)->sole();

        $this->assertSame('knx/devices/'.self::CLIENT_ID.'.png', $conflict->photo_path);
        Storage::disk('local')->assertExists($conflict->photo_path);
    }

    public function test_a_client_id_reused_in_another_project_is_rejected(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->register()->assertCreated();

        // The key identifies a registration everywhere, so answering with the device
        // from the other project would hand this one its apparatus.
        $this->app['auth']->forgetGuards();
        $this->planToday('Mira Claes', '239870');
        $this->actingAs($this->fieldUser('mira.claes@electrobertels.be'), 'sanctum');

        $this->postJson('/api/v1/knx/field/projects/239870/devices', $this->payload([
            'projectCode' => '239870',
            'roomId' => $this->roomId('Vergaderzaal', '239870'),
            'address' => '1.9.150',
        ]))->assertStatus(422)->assertJsonPath('errors.clientId.0', __('knx::field.client_id_reused'));
    }

    public function test_a_room_that_is_not_part_of_the_project_is_rejected(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        // The app builds its list from the project payload, so an unknown id means a
        // stale list — guessing from the free-text name would place apparatus wrongly.
        $this->register(['roomId' => $this->roomId('Vergaderzaal', '239870')])
            ->assertStatus(422)
            ->assertJsonPath('errors.roomId.0', __('knx::field.room_unknown'));

        $this->assertFalse(KnxDevice::query()->where('client_id', self::CLIENT_ID)->exists());
    }

    public function test_a_project_code_that_does_not_match_the_url_is_rejected(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->register(['projectCode' => '239870'])
            ->assertStatus(422)
            ->assertJsonPath('errors.projectCode.0', __('knx::field.project_mismatch'));
    }

    public function test_a_board_the_office_has_not_modelled_is_created_without_a_name(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $payload = $this->register(['boardCode' => 'E99'])->assertCreated()->json();

        $this->assertSame('E99', $payload['boardCode']);

        // The technician read that code off a real cabinet, so it is data. It is kept
        // without inventing a name for it: we know the code, not what the office calls
        // that board.
        $board = KnxBoard::query()
            ->where('project_id', $this->project()->getKey())
            ->where('code', 'E99')
            ->sole();

        $this->assertNull($board->name);
    }

    public function test_a_photo_that_is_not_an_image_is_rejected(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->register(['photoDataUrl' => 'data:text/plain;base64,'.base64_encode('nope')])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['photoDataUrl']]);

        $this->assertFalse(KnxDevice::query()->where('client_id', self::CLIENT_ID)->exists());
    }

    public function test_a_device_cannot_be_registered_in_a_project_that_is_not_mine_today(): void
    {
        // Jan's day is C1618. Mira's project is a stranger to him, so the write is
        // refused before the payload is even looked at.
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->postJson('/api/v1/knx/field/projects/239870/devices', $this->payload([
            'projectCode' => '239870',
            'roomId' => $this->roomId('Vergaderzaal', '239870'),
        ]))->assertForbidden();

        $this->assertFalse(KnxDevice::query()->where('client_id', self::CLIENT_ID)->exists());
    }

    public function test_the_office_cannot_register_devices_through_the_field_endpoint(): void
    {
        $this->actingAs($this->officeUser(), 'sanctum');

        $this->register()->assertUnauthorized();
    }

    public function test_registration_requires_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->register()->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
