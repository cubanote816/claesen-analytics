<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
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
            ['code', 'name', 'clientName', 'city', 'floors', 'rooms', 'boards', 'deviceTypes'],
            array_keys($payload),
        );

        $this->assertSame('C1618', $payload['code']);
        $this->assertSame('UV Campus · Gelijkvloers', $payload['name']);
        $this->assertSame('UV Vastgoed', $payload['clientName']);
        $this->assertSame('Heverlee', $payload['city']);

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
    }

    public function test_the_project_endpoints_require_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/field/projects/C1618')->assertUnauthorized();
        $this->getJson('/api/v1/knx/field/projects/C1618/plans')->assertUnauthorized();
    }
}
