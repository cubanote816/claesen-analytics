<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxPlanAnalysis;
use Modules\Knx\Models\KnxPlanDevice;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * The KNX devices of a plan page (KNX-48, plano IA v2, provisional contract §4): GET the
 * page's set, PUT replaces it atomically, scoped to organization, project and document.
 */
final class KnxPlanDevicesTest extends TestCase
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

    private function document(): KnxDocument
    {
        $project = KnxProject::query()->where('code', 'C1618')->sole();

        return KnxDocument::query()
            ->where('project_id', $project->getKey())
            ->where('name', 'UV_C1618_Gelijkvloers_Wayfinding.pdf')
            ->sole();
    }

    private function url(KnxDocument $document, string $query = ''): string
    {
        return "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/devices{$query}";
    }

    /**
     * @return array<string, mixed>
     */
    private function device(string $provisionalId = 'D1', array $overrides = []): array
    {
        return array_replace([
            'provisionalId' => $provisionalId,
            'type' => 'pushbutton',
            'nx' => 0.41,
            'ny' => 0.22,
            'bbox' => ['x' => 0.408, 'y' => 0.217, 'w' => 0.006, 'h' => 0.006],
            'rotationDeg' => 90,
            'symbolId' => 's123',
            'label' => 'S1',
            'status' => 'proposed',
            'doubts' => ['no_legend_entry'],
            'analysisId' => null,
        ], $overrides);
    }

    public function test_a_page_without_devices_is_an_empty_list(): void
    {
        $document = $this->document();

        $this->getJson($this->url($document, '?page=1'))->assertOk()->assertExactJson(['devices' => []]);
        $this->getJson($this->url($document))->assertOk()->assertExactJson(['devices' => []]);
    }

    public function test_put_then_get_returns_the_devices_with_the_contract_shape(): void
    {
        $document = $this->document();
        $analysis = KnxPlanAnalysis::query()->create([
            'project_id' => $document->project_id, 'document_id' => $document->getKey(), 'page' => 1,
            'provider' => 'synthetic', 'schema_version' => 'plan-analysis/2', 'proposal' => [],
        ]);
        $devices = [
            $this->device('D1', ['analysisId' => (string) $analysis->getKey()]),
            $this->device('D2', ['type' => 'bord', 'symbolId' => 's9', 'label' => null, 'rotationDeg' => null, 'status' => 'confirmed', 'doubts' => []]),
        ];

        $put = $this->putJson($this->url($document), ['page' => 1, 'devices' => $devices])->assertOk();
        $get = $this->getJson($this->url($document, '?page=1'))->assertOk();

        foreach ([$put, $get] as $response) {
            $list = $response->json('devices');
            $this->assertSame(['devices'], array_keys($response->json()));
            $this->assertSame(
                ['id', 'provisionalId', 'type', 'nx', 'ny', 'bbox', 'rotationDeg', 'symbolId', 'label', 'status', 'doubts', 'analysisId', 'updatedAt'],
                array_keys($list[0]),
            );
            $this->assertSame(['D1', 'D2'], array_column($list, 'provisionalId'));
            $this->assertEquals($devices[0]['bbox'], $list[0]['bbox']);
            $this->assertEquals([90, null], array_column($list, 'rotationDeg'));
            $this->assertSame(['S1', null], array_column($list, 'label'));
            $this->assertSame(['proposed', 'confirmed'], array_column($list, 'status'));
            $this->assertSame([['no_legend_entry'], []], array_column($list, 'doubts'));
            $this->assertSame([(string) $analysis->getKey(), null], array_column($list, 'analysisId'));
            $this->assertIsString($list[0]['id']);
            $this->assertNotNull($list[0]['updatedAt']);
        }
        $row = KnxPlanDevice::query()->where('provisional_id', 'D1')->sole();
        $this->assertSame('Rev. C', $row->document_revision);
        $this->assertSame($document->project_id, $row->project_id);
        $this->assertNotNull($row->updated_by_employee_id);
    }

    public function test_put_replaces_the_page_set_atomically_and_leaves_other_pages_alone(): void
    {
        $document = $this->document();
        $this->putJson($this->url($document), ['page' => 1, 'devices' => [$this->device('D1'), $this->device('D2')]])->assertOk();
        $this->putJson($this->url($document), ['page' => 2, 'devices' => [$this->device('D1', ['symbolId' => 's5'])]])->assertOk();

        // The same provisional ids come back replaced, the dropped one is gone.
        $this->putJson($this->url($document), ['page' => 1, 'devices' => [$this->device('D1', ['type' => 'detector_360', 'status' => 'rejected'])]])->assertOk();

        $page1 = $this->getJson($this->url($document, '?page=1'))->json('devices');
        $this->assertSame(['D1'], array_column($page1, 'provisionalId'));
        $this->assertSame(['detector_360'], array_column($page1, 'type'));
        $this->assertSame(['s5'], array_column($this->getJson($this->url($document, '?page=2'))->json('devices'), 'symbolId'));

        // An empty list clears the page (there is no DELETE).
        $this->putJson($this->url($document), ['page' => 1, 'devices' => []])->assertOk()->assertExactJson(['devices' => []]);
        $this->assertSame(1, KnxPlanDevice::query()->count());
    }

    public function test_a_failing_put_leaves_the_previous_set_untouched(): void
    {
        $document = $this->document();
        $this->putJson($this->url($document), ['page' => 1, 'devices' => [$this->device('D1'), $this->device('D2')]])->assertOk();

        foreach ([
            [$this->device('D1'), $this->device('D1')],                       // provisional id twice
            [$this->device('D9', ['type' => 'lamp'])],
            [$this->device('X9')],
            [$this->device('D9', ['nx' => 1.5])],
            [$this->device('D9', ['bbox' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.1]])],
            [$this->device('D9', ['symbolId' => 'x1'])],
            [$this->device('D9', ['status' => 'maybe'])],
            [$this->device('D9', ['doubts' => 'no_legend_entry'])],
            [$this->device('D9', ['rotationDeg' => 'left'])],
            [$this->device('D9', ['analysisId' => '999999'])],               // not an analysis of this document
        ] as $bad) {
            $this->putJson($this->url($document), ['page' => 1, 'devices' => $bad])->assertUnprocessable();
        }
        $this->putJson($this->url($document), ['devices' => []])->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->putJson($this->url($document), ['page' => 1])->assertUnprocessable()->assertJsonValidationErrors('devices');

        $this->assertSame(['D1', 'D2'], array_column($this->getJson($this->url($document, '?page=1'))->json('devices'), 'provisionalId'));
    }

    public function test_the_document_is_resolved_inside_the_project_and_the_organization(): void
    {
        $document = $this->document();

        $this->getJson('/api/v1/knx/projects/C1618/plans/999999/devices')->assertNotFound();
        $this->putJson('/api/v1/knx/projects/C1618/plans/999999/devices', ['page' => 1, 'devices' => []])->assertNotFound();
        $this->getJson("/api/v1/knx/projects/NOPE/plans/{$document->getKey()}/devices")->assertNotFound();
        // A document of another project of the same tenant.
        $this->getJson("/api/v1/knx/projects/239870/plans/{$document->getKey()}/devices")->assertNotFound();

        $other = Organization::factory()->create(['slug' => 'other-devices']);
        $foreignProject = KnxProject::factory()->create(['organization_id' => $other->id, 'code' => 'FOREIGN-DEV']);
        $foreignDocument = KnxDocument::factory()->create(['project_id' => $foreignProject->getKey()]);

        $this->getJson("/api/v1/knx/projects/FOREIGN-DEV/plans/{$foreignDocument->getKey()}/devices")->assertNotFound();
        $this->putJson("/api/v1/knx/projects/FOREIGN-DEV/plans/{$foreignDocument->getKey()}/devices", ['page' => 1, 'devices' => [$this->device()]])->assertNotFound();
        $this->assertSame(0, KnxPlanDevice::query()->count());
    }

    public function test_the_devices_endpoints_refuse_a_field_account(): void
    {
        $document = $this->document();

        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );

        $this->getJson($this->url($document))->assertUnauthorized();
        $this->putJson($this->url($document), ['page' => 1, 'devices' => []])->assertUnauthorized();
    }
}
