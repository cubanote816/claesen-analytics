<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * V11.b — the project the field app works from, and its drawings (CLA-609).
 *
 * Two things are worth testing harder than the shape: the project scope (a
 * technician must not read a project they are not on today) and the plan list
 * (it feeds an offline cache, so it may only contain files the app can really
 * download and render).
 */
final class KnxFieldProjectTest extends TestCase
{
    use RefreshDatabase;

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

    private function officeUser(): User
    {
        return User::query()->where('email', 'lien.smet@electrobertels.be')->sole();
    }

    private function technician(string $name = 'Jan Van Dyck'): KnxEmployee
    {
        return KnxEmployee::query()->where('name', $name)->sole();
    }

    private function planToday(KnxEmployee $technician, string $projectCode): void
    {
        KnxPlanningAssignment::updateOrCreate(
            ['employee_id' => $technician->getKey(), 'date' => now()->toDateString()],
            ['project_id' => KnxProject::query()->where('code', $projectCode)->sole()->getKey()],
        );
    }

    public function test_the_project_payload_has_the_contract_shape(): void
    {
        $this->planToday($this->technician(), 'C1618');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $payload = $this->getJson('/api/v1/knx/field/projects/C1618')->assertOk()->json();

        $this->assertSame(
            [
                'code', 'name', 'clientName', 'clientAddress', 'clientContact', 'clientPhone',
                'city', 'devicesPlanned', 'devicesDone', 'photos', 'openConflicts',
                'floors', 'rooms', 'boards', 'deviceTypes',
            ],
            array_keys($payload),
        );

        $this->assertSame('C1618', $payload['code']);
        $this->assertSame('UV Campus · Gelijkvloers', $payload['name']);
        $this->assertSame('UV Vastgoed', $payload['clientName']);
        // Where the job is and who to ring, from `knx_clients` — the technician
        // needs both from site and neither was exposed before CLA-635.
        $this->assertSame('Kapeldreef 60, 3001 Heverlee', $payload['clientAddress']);
        $this->assertSame('Dirk Maes', $payload['clientContact']);
        $this->assertSame('0476 12 34 56', $payload['clientPhone']);
        $this->assertSame('Heverlee', $payload['city']);
        // The office's own header numbers, from the same source as
        // `ProjectStatsResource`: the columns it already maintains plus the live
        // conflict count. C1618 has two conflicts in `open`/`in_review` and one
        // already `verified`, which must not count.
        $this->assertSame(24, $payload['devicesPlanned']);
        $this->assertSame(17, $payload['devicesDone']);
        $this->assertSame(38, $payload['photos']);
        $this->assertSame(2, $payload['openConflicts']);

        // Rooms carry an id, because that is what the app sends back when it
        // registers a device.
        $this->assertSame(['id', 'name', 'floor'], array_keys($payload['rooms'][0]));
        $this->assertSame('Inkomhal', $payload['rooms'][0]['name']);
        $this->assertIsString($payload['rooms'][0]['id']);

        $this->assertSame(['code', 'name'], array_keys($payload['boards'][0]));
        $this->assertContains('E10', array_column($payload['boards'], 'code'));
    }

    public function test_the_floors_come_from_the_rooms_in_the_order_they_were_modelled(): void
    {
        $this->planToday($this->technician(), 'C1618');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $floors = $this->getJson('/api/v1/knx/field/projects/C1618')->json('floors');

        // The fixture models every room of C1618 on one level, so the list is the
        // distinct floor labels — not one entry per room.
        $this->assertSame(['Gelijkvloers'], $floors);
    }

    public function test_the_type_list_is_derived_from_the_projects_own_devices(): void
    {
        $this->planToday($this->technician(), 'C1618');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $types = $this->getJson('/api/v1/knx/field/projects/C1618')->json('deviceTypes');

        // There is no catalogue table: the list is what the project already uses,
        // so it is never empty for a project with devices and never invented.
        $this->assertNotEmpty($types);
        $this->assertContains('Drukknop 4-voudig', $types);
        $this->assertSame(array_values(array_unique($types)), $types, 'no duplicates');
    }

    public function test_the_device_list_answers_the_field_device_shape_without_leaking_other_projects(): void
    {
        $this->planToday($this->technician(), 'C1618');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $project = KnxProject::query()->where('code', 'C1618')->sole();
        $devices = $this->getJson('/api/v1/knx/field/projects/C1618/devices')->assertOk()->json();

        $this->assertNotEmpty($devices);

        // The same shape the registration answers with, so the app has one device
        // shape and not two.
        $this->assertSame(
            ['id', 'address', 'type', 'roomName', 'boardCode', 'serial', 'registeredBy', 'registeredAt'],
            array_keys($devices[0]),
        );

        // Every address belongs to this project. The list is what the app draws its
        // progress from, so one foreign device would silently inflate the count.
        $own = KnxDevice::query()->where('project_id', $project->getKey())->pluck('address')->all();
        $this->assertSame([], array_values(array_diff(array_column($devices, 'address'), $own)));

        // Field registrations first, then the ETS plan — the office's own order, so
        // the app's "latest registrations" block is not buried under the plan.
        //
        // The claim is a property of the whole list and not of its head: checking
        // that `$devices[0]` is a field device would still pass with the plan
        // interleaved between the registrations.
        $ids = array_column($devices, 'id');

        $fieldIds = KnxDevice::query()
            ->where('project_id', $project->getKey())
            ->where('source', KnxDevice::SOURCE_FIELD)
            ->pluck('id')
            ->all();

        $planIds = KnxDevice::query()
            ->where('project_id', $project->getKey())
            ->where('source', KnxDevice::SOURCE_ETS)
            ->pluck('id')
            ->all();

        $this->assertNotEmpty($fieldIds, 'the fixture registers devices from site on C1618');
        $this->assertNotEmpty($planIds, 'the fixture also carries the ETS plan on C1618');

        $fieldPositions = array_keys(array_intersect($ids, $fieldIds));
        $planPositions = array_keys(array_intersect($ids, $planIds));

        $this->assertGreaterThan(
            max($fieldPositions),
            min($planPositions),
            'every device registered from site comes before the ones from the plan',
        );

        // And the two groups are the whole list: a device left out of both would
        // mean the list is answering with something the project does not own.
        $this->assertCount(
            count($ids),
            array_merge($fieldPositions, $planPositions),
            'every device in the list is either a field registration or part of the plan',
        );
    }

    public function test_the_device_list_respects_the_same_scope_as_the_project(): void
    {
        // Mira is planned on 239870 today, never on C1618.
        $this->planToday($this->technician('Mira Claes'), '239870');
        $this->actingAs($this->fieldUser('mira.claes@electrobertels.be'), 'sanctum');

        $this->getJson('/api/v1/knx/field/projects/C1618/devices')->assertForbidden();
        $this->getJson('/api/v1/knx/field/projects/NOPE-999/devices')->assertNotFound();
    }

    public function test_a_project_the_technician_is_not_on_today_is_forbidden_not_hidden(): void
    {
        $this->actingAs($this->fieldUser(), 'sanctum');

        // Jan's day is C1618; 239870 is Mira's. The project exists, so this is a
        // permission answer (403) and not a missing one (404).
        $this->getJson('/api/v1/knx/field/projects/239870')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->getJson('/api/v1/knx/field/projects/239870/plans')->assertForbidden();
    }

    public function test_a_project_the_technician_does_not_have_today_is_forbidden(): void
    {
        // Assigned yesterday, not today: the scope is the day, not the project.
        KnxPlanningAssignment::query()
            ->where('employee_id', $this->technician()->getKey())
            ->whereDate('date', now()->toDateString())
            ->delete();

        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->getJson('/api/v1/knx/field/projects/C1618')->assertForbidden();
    }

    public function test_an_unknown_project_code_is_not_found(): void
    {
        $this->planToday($this->technician(), 'C1618');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->getJson('/api/v1/knx/field/projects/ZZ999')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_the_plans_are_the_projects_drawings_with_a_downloadable_url(): void
    {
        $this->planToday($this->technician(), 'C1618');
        $this->actingAs($this->fieldUser(), 'sanctum');

        $plans = $this->getJson('/api/v1/knx/field/projects/C1618/plans')->assertOk()->json();

        $this->assertCount(1, $plans);

        $plan = $plans[0];

        $this->assertSame(
            ['id', 'projectCode', 'name', 'kind', 'revision', 'mimeType', 'url', 'sizeBytes', 'pages'],
            array_keys($plan),
        );
        $this->assertSame('UV_C1618_Gelijkvloers_Wayfinding.pdf', $plan['name']);
        $this->assertSame('C1618', $plan['projectCode']);
        $this->assertSame('Plan', $plan['kind']);
        $this->assertSame('Rev. C', $plan['revision']);

        // The office never stores a content type; it comes from the extension.
        $this->assertSame('application/pdf', $plan['mimeType']);

        // The size is the number the office fixture declares as "4,2 MB", and the
        // file on disk is that size too.
        $this->assertSame(4404019, $plan['sizeBytes']);
        $this->assertSame(1, $plan['pages']);

        // The URL is the point of this endpoint: the app caches the file itself.
        $path = parse_url($plan['url'], PHP_URL_PATH).'?'.parse_url($plan['url'], PHP_URL_QUERY);

        $this->app['auth']->forgetGuards();

        $response = $this->get($path)->assertOk();

        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_a_plan_that_cannot_be_opened_is_not_offered(): void
    {
        $this->planToday($this->technician(), 'C1618');
        $this->actingAs($this->fieldUser(), 'sanctum');

        // Metadata with no file behind it: the app would download a 404 and cache
        // nothing, which is worse than not offering the plan at all.
        KnxDocument::create([
            'project_id' => KnxProject::query()->where('code', 'C1618')->sole()->getKey(),
            'name' => 'Gepland_maar_niet_aangeleverd.pdf',
            'kind' => 'Plan',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'pages' => 1,
            'path' => 'knx/documents/does-not-exist.pdf',
            'revision' => 'Rev. A',
            'is_current' => true,
            'uploaded_at' => now()->toDateString(),
        ]);

        $names = array_column($this->getJson('/api/v1/knx/field/projects/C1618/plans')->json(), 'name');

        $this->assertSame(['UV_C1618_Gelijkvloers_Wayfinding.pdf'], $names);
    }

    public function test_only_drawings_are_in_the_plan_list(): void
    {
        $this->actingAs($this->fieldUser(), 'sanctum');

        // A project whose documents are a report and a photo archive: nothing to
        // draw on, so an empty list — not a document browser.
        $mira = $this->technician('Mira Claes');
        KnxPlanningAssignment::query()->where('employee_id', $mira->getKey())->whereDate('date', now()->toDateString())->delete();
        $this->planToday($mira, '240512');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->fieldUser('mira.claes@electrobertels.be'), 'sanctum');

        $this->getJson('/api/v1/knx/field/projects/240512/plans')->assertOk()->assertJsonCount(0);
    }

    public function test_the_project_endpoints_belong_to_the_field_app_only(): void
    {
        $this->actingAs($this->officeUser(), 'sanctum');

        $this->getJson('/api/v1/knx/field/projects/C1618')->assertUnauthorized();
        $this->getJson('/api/v1/knx/field/projects/C1618/plans')->assertUnauthorized();
        $this->getJson('/api/v1/knx/field/projects/C1618/devices')->assertUnauthorized();
    }

    public function test_the_project_endpoints_require_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/field/projects/C1618')->assertUnauthorized();
        $this->getJson('/api/v1/knx/field/projects/C1618/plans')->assertUnauthorized();
        $this->getJson('/api/v1/knx/field/projects/C1618/devices')->assertUnauthorized();
    }
}
