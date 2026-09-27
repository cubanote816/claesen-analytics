<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * V11.d — incidents reported from site (CLA-609).
 *
 * An incident is only useful if it keeps its context: where, on which apparatus and
 * on which channel. And it is only worth storing if the office can act on it, so what
 * lands in the Conflictencentrum is what these tests look at.
 */
final class KnxFieldIssueTest extends TestCase
{
    use RefreshDatabase;

    /** Taken in the fixture: a device sits at this address in C1618. */
    private const KNOWN_ADDRESS = '1.1.111';

    private const CLIENT_ID = 'beef0000-1111-4222-8333-444455556666';

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

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
            'projectName' => 'UV Campus · Gelijkvloers',
            'room' => 'Vergaderzaal',
            'deviceAddress' => self::KNOWN_ADDRESS,
            'boardCode' => 'E10',
            'channel' => 'rij 4, kanaal 2',
            'kind' => 'damaged',
            'note' => 'Afdekraam gebarsten, toestel werkt.',
            'photoDataUrl' => null,
            'capturedAt' => now()->subMinutes(10)->toIso8601String(),
        ], $overrides);
    }

    private function report(array $overrides = [], string $code = 'C1618')
    {
        return $this->postJson("/api/v1/knx/field/projects/{$code}/issues", $this->payload($overrides));
    }

    /**
     * The conflict this test created.
     *
     * Always by `client_id` and never by `sole()` alone: the demo fixture ships five
     * conflicts of its own, so an unfiltered query is ambiguous by design.
     */
    private function storedConflict(string $clientId = self::CLIENT_ID): KnxConflict
    {
        return KnxConflict::query()->where('client_id', $clientId)->sole();
    }

    public function test_a_reported_incident_reaches_the_office_as_a_conflict(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $payload = $this->report()->assertCreated()->json();

        // The contract's ReportIssueResult: the id plus the app's own key, so the app
        // can mark its queued item as delivered.
        $this->assertSame(['id', 'clientId'], array_keys($payload));
        $this->assertSame(self::CLIENT_ID, $payload['clientId']);

        $conflict = $this->storedConflict();

        $this->assertSame('damaged', $conflict->type);
        $this->assertSame('info', $conflict->severity);
        $this->assertSame('open', $conflict->status);
        $this->assertSame(self::KNOWN_ADDRESS, $conflict->address);
        $this->assertSame($this->project()->getKey(), $conflict->project_id);
        $this->assertSame('Jan Van Dyck', $conflict->reportedBy->name);
        $this->assertSame('Afdekraam gebarsten, toestel werkt.', $conflict->note);

        // The context the contract insists on: place, apparatus and channel all survive.
        $this->assertSame('Vergaderzaal · 1.1.111 · E10 · rij 4, kanaal 2', $conflict->device_field);

        // The history opens with the report itself.
        $this->assertSame(['reported'], $conflict->logs()->pluck('action')->all());
    }

    public function test_the_incident_is_anchored_to_the_device_that_is_registered_there(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');
        $this->report()->assertCreated();

        $device = KnxDevice::query()
            ->where('project_id', $this->project()->getKey())
            ->where('address', self::KNOWN_ADDRESS)
            ->sole();

        $conflict = $this->storedConflict();

        // Anchoring it is what turns the report into something the ETS list can be
        // checked against.
        $this->assertSame($device->getKey(), $conflict->device_id);
        $this->assertStringContainsString($device->type, (string) $conflict->device_existing);
        $this->assertStringContainsString($device->room->name, (string) $conflict->device_existing);
        $this->assertStringNotContainsString('niet gevonden', (string) $conflict->device_existing);
    }

    public function test_an_incident_about_an_address_nothing_is_registered_at_says_so(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->report(['deviceAddress' => '1.9.999'])->assertCreated();

        $conflict = $this->storedConflict();

        // The office reads "what ETS says is there" against "what the technician found":
        // for an address nothing is registered at, saying so is the honest answer.
        $this->assertNull($conflict->device_id);
        $this->assertStringContainsString('niet gevonden', (string) $conflict->device_existing);
    }

    public function test_the_incident_that_does_not_mention_an_address_still_keeps_its_place(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        // A problem with a space, not with an apparatus: the address column is not
        // nullable, so it stays empty rather than being filled with an invention.
        $this->report(['deviceAddress' => null, 'channel' => null, 'kind' => 'plan_mismatch'])->assertCreated();

        $conflict = $this->storedConflict();

        $this->assertSame('', $conflict->address);
        $this->assertSame('Vergaderzaal · E10', $conflict->device_field);
        $this->assertSame('plan_mismatch', $conflict->type);
    }

    public function test_each_kind_maps_to_the_office_type_that_means_the_same_thing(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $expected = [
            'damaged' => ['damaged', 'info'],
            'missing' => ['missing_device', 'warning'],
            'plan_mismatch' => ['plan_mismatch', 'warning'],
        ];

        foreach ($expected as $kind => [$type, $severity]) {
            $this->report([
                'clientId' => 'beef0000-1111-4222-8333-44445555666'.$kind[0],
                'kind' => $kind,
            ])->assertCreated();

            $conflict = KnxConflict::query()
                ->where('client_id', 'beef0000-1111-4222-8333-44445555666'.$kind[0])
                ->sole();
            $this->assertSame($type, $conflict->type, "kind {$kind}");
            // The severity the office fixture already uses for that type.
            $this->assertSame($severity, $conflict->severity, "kind {$kind}");
        }
    }

    public function test_the_kind_other_is_refused_because_the_office_has_no_such_type(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        // Declared gap: the office's ConflictType has four values and its label map has
        // no fallback, so storing "other" would render as `undefined` in the
        // Conflictencentrum. Mislabeling the finding as one of the three would be worse
        // than refusing it, so the refusal is explicit and names the alternatives.
        $this->report(['kind' => 'other'])
            ->assertStatus(422)
            ->assertJsonPath('errors.kind.0', __('knx::field.kind_unsupported'));

        $this->assertSame(0, KnxConflict::query()->where('client_id', self::CLIENT_ID)->count());
    }

    public function test_a_kind_outside_the_contract_is_a_plain_field_error(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->report(['kind' => 'exploded'])->assertStatus(422)->assertJsonStructure(['errors' => ['kind']]);
    }

    public function test_the_same_client_id_never_reports_the_incident_twice(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $first = $this->report()->assertCreated()->json();
        $second = $this->report()->assertOk()->json();

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, KnxConflict::query()->where('client_id', self::CLIENT_ID)->count());
    }

    public function test_a_client_id_reused_in_another_project_is_rejected(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');
        $this->report()->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->planToday('Mira Claes', '239870');
        $this->actingAs($this->fieldUser('mira.claes@electrobertels.be'), 'sanctum');

        $this->report(['projectCode' => '239870'], '239870')
            ->assertStatus(422)
            ->assertJsonPath('errors.clientId.0', __('knx::field.client_id_reused'));
    }

    public function test_the_photo_becomes_the_evidence_of_the_incident(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->report([
            'photoDataUrl' => 'data:image/jpeg;base64,'.base64_encode('fake-jpeg-bytes'),
        ])->assertCreated();

        $conflict = $this->storedConflict();

        $this->assertSame('knx/devices/'.self::CLIENT_ID.'.jpg', $conflict->photo_path);
        Storage::disk('local')->assertExists($conflict->photo_path);
    }

    public function test_the_office_sees_the_incident_in_its_conflict_list(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');
        $this->report()->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->officeUser(), 'sanctum');

        $conflicts = $this->getJson('/api/v1/knx/conflicts')->assertOk()->json();

        // Asked by address and type: the fixture has a `damaged` conflict of its own.
        $mine = collect($conflicts)
            ->first(fn (array $c): bool => $c['type'] === 'damaged' && $c['address'] === self::KNOWN_ADDRESS);

        $this->assertNotNull($mine, 'the incident shows up in the Conflictencentrum');
        $this->assertSame('open', $mine['status']);
        $this->assertStringContainsString('Vergaderzaal', (string) $mine['deviceField']);
    }

    public function test_a_project_code_that_does_not_match_the_url_is_rejected(): void
    {
        $this->planToday('Jan Van Dyck');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->report(['projectCode' => '239870'])
            ->assertStatus(422)
            ->assertJsonPath('errors.projectCode.0', __('knx::field.project_mismatch'));
    }

    public function test_an_incident_cannot_be_reported_in_a_project_that_is_not_mine_today(): void
    {
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->report(['projectCode' => '239870'], '239870')->assertForbidden();

        $this->assertSame(0, KnxConflict::query()->where('client_id', self::CLIENT_ID)->count());
    }

    public function test_the_office_cannot_report_incidents_through_the_field_endpoint(): void
    {
        $this->actingAs($this->officeUser(), 'sanctum');

        $this->report()->assertUnauthorized();
    }

    public function test_reporting_an_incident_requires_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->report()->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
}
