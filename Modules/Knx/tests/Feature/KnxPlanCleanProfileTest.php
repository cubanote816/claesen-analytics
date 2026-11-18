<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxPlanCleanProfile;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * The clean-plan profile (group → role) of a document page (KNX-48).
 *
 * Pins what Kantoor relies on to survive a reload: save, read back the same page
 * exactly, keep pages independent, and tell a profile made for another revision.
 */
final class KnxPlanCleanProfileTest extends TestCase
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
        return "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/clean-profile{$query}";
    }

    public function test_a_page_without_a_profile_is_a_200_with_an_empty_roles_object(): void
    {
        $document = $this->document();

        $response = $this->getJson($this->url($document, '?page=1'))->assertOk();

        $this->assertSame(
            ['documentId', 'page', 'revision', 'documentRevision', 'processingVersion', 'roles', 'boundaries', 'updatedAt'],
            array_keys($response->json()),
        );
        $this->assertSame([], $response->json('boundaries'));
        $this->assertSame((string) $document->getKey(), $response->json('documentId'));
        $this->assertSame(1, $response->json('page'));
        $this->assertNull($response->json('revision'));
        $this->assertSame('Rev. C', $response->json('documentRevision'));
        $this->assertNull($response->json('processingVersion'));
        $this->assertNull($response->json('updatedAt'));
        // A JSON object, never `[]`.
        $this->assertStringContainsString('"roles":{}', $response->getContent());
    }

    public function test_put_then_get_returns_exactly_what_was_saved_and_pages_are_independent(): void
    {
        $document = $this->document();
        $roles = ['layer:A-WALL.1' => 'wall', 'layer:A-FURN' => 'hidden', 'style:0.5|#000' => 'unknown', '7' => 'door'];

        $put = $this->putJson($this->url($document), [
            'page' => 1,
            'roles' => $roles,
            'processingVersion' => 'kantoor-plan/3',
        ])->assertOk();

        $get = $this->getJson($this->url($document, '?page=1'))->assertOk();

        foreach ([$put, $get] as $response) {
            $this->assertEquals($roles, $response->json('roles'));
            $this->assertSame('Rev. C', $response->json('revision'));
            $this->assertSame('Rev. C', $response->json('documentRevision'));
            $this->assertSame('kantoor-plan/3', $response->json('processingVersion'));
            $this->assertNotNull($response->json('updatedAt'));
            $this->assertSame(1, $response->json('page'));
        }

        // Another page is independent: empty until it is saved on its own.
        $this->getJson($this->url($document, '?page=2'))->assertOk()
            ->assertJsonPath('page', 2)
            ->assertJsonPath('revision', null);
        $this->assertStringContainsString('"roles":{}', $this->getJson($this->url($document, '?page=2'))->getContent());

        $this->putJson($this->url($document), ['page' => 2, 'roles' => ['layer:X' => 'lift']])->assertOk();
        $this->assertEquals(['layer:X' => 'lift'], $this->getJson($this->url($document, '?page=2'))->json('roles'));
        $this->assertEquals($roles, $this->getJson($this->url($document, '?page=1'))->json('roles'));
        $this->assertSame(2, KnxPlanCleanProfile::query()->where('document_id', $document->getKey())->count());

        // A second PUT replaces the page's map, it does not merge.
        $this->putJson($this->url($document), ['page' => 1, 'roles' => ['layer:ONLY' => 'stair']])->assertOk();
        $this->assertEquals(['layer:ONLY' => 'stair'], $this->getJson($this->url($document, '?page=1'))->json('roles'));
        $this->assertNull($this->getJson($this->url($document, '?page=1'))->json('processingVersion'));
    }

    public function test_an_empty_roles_object_clears_the_overrides_but_a_missing_key_is_rejected(): void
    {
        $document = $this->document();

        $this->putJson($this->url($document), ['page' => 1, 'roles' => ['layer:A' => 'wall']])->assertOk();

        $cleared = $this->putJson($this->url($document), ['page' => 1, 'roles' => []])->assertOk();
        $this->assertStringContainsString('"roles":{}', $cleared->getContent());
        $this->assertStringContainsString('"roles":{}', $this->getJson($this->url($document, '?page=1'))->getContent());

        $this->putJson($this->url($document), ['page' => 1])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['roles']]);
    }

    public function test_invalid_bodies_are_a_422_and_store_nothing(): void
    {
        $document = $this->document();

        $this->putJson($this->url($document), ['page' => 1, 'roles' => ['layer:A' => 'sofa']])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['roles.layer:A']]);
        $this->putJson($this->url($document), ['page' => 0, 'roles' => []])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['page']]);
        $this->putJson($this->url($document), ['roles' => []])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['page']]);
        $this->putJson($this->url($document), ['page' => 1, 'roles' => [], 'processingVersion' => str_repeat('v', 33)])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['processingVersion']]);
        $this->putJson($this->url($document), ['page' => 1, 'roles' => [str_repeat('k', 256) => 'wall']])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['roles']]);

        $tooMany = [];
        for ($i = 0; $i < 501; $i++) {
            $tooMany["layer:{$i}"] = 'wall';
        }
        $this->putJson($this->url($document), ['page' => 1, 'roles' => $tooMany])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['roles']]);

        $this->getJson($this->url($document, '?page=0'))->assertStatus(422);
        $this->assertSame(0, KnxPlanCleanProfile::query()->count());
    }

    public function test_the_document_revision_is_reported_next_to_the_revision_of_the_save(): void
    {
        $document = $this->document();

        $this->putJson($this->url($document), ['page' => 1, 'roles' => ['layer:A' => 'wall']])->assertOk();

        $document->update(['revision' => 'Rev. D']);

        $get = $this->getJson($this->url($document, '?page=1'))->assertOk();
        $this->assertSame('Rev. C', $get->json('revision'));
        $this->assertSame('Rev. D', $get->json('documentRevision'));

        // Saving again stamps the current revision.
        $put = $this->putJson($this->url($document), ['page' => 1, 'roles' => ['layer:A' => 'wall']])->assertOk();
        $this->assertSame('Rev. D', $put->json('revision'));
        $this->assertSame('Rev. D', $put->json('documentRevision'));
    }

    public function test_the_document_is_resolved_inside_the_project_and_the_organization(): void
    {
        $document = $this->document();

        $this->getJson('/api/v1/knx/projects/C1618/plans/999999/clean-profile')->assertNotFound();
        $this->putJson('/api/v1/knx/projects/C1618/plans/999999/clean-profile', ['page' => 1, 'roles' => []])->assertNotFound();
        $this->getJson("/api/v1/knx/projects/NOPE/plans/{$document->getKey()}/clean-profile")->assertNotFound();
        // A document of another project of the same tenant.
        $this->getJson("/api/v1/knx/projects/239870/plans/{$document->getKey()}/clean-profile")->assertNotFound();

        $other = Organization::factory()->create(['slug' => 'other-profiles']);
        $foreignProject = KnxProject::factory()->create(['organization_id' => $other->id, 'code' => 'FOREIGN-PROF']);
        $foreignDocument = KnxDocument::factory()->create(['project_id' => $foreignProject->getKey()]);

        $this->getJson("/api/v1/knx/projects/FOREIGN-PROF/plans/{$foreignDocument->getKey()}/clean-profile")->assertNotFound();
        $this->putJson(
            "/api/v1/knx/projects/FOREIGN-PROF/plans/{$foreignDocument->getKey()}/clean-profile",
            ['page' => 1, 'roles' => []],
        )->assertNotFound();
        $this->assertSame(0, KnxPlanCleanProfile::query()->count());
    }

    public function test_the_profile_endpoints_refuse_a_field_account(): void
    {
        $document = $this->document();

        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );

        $this->getJson($this->url($document))->assertUnauthorized();
        $this->putJson($this->url($document), ['page' => 1, 'roles' => []])->assertUnauthorized();
    }

    public function test_virtual_boundaries_are_stored_with_the_roles_and_read_back_exactly_per_page(): void
    {
        $document = $this->document();
        $boundaries = [[[0.82, 0.1], [0.82, 0.9]], [[0.05, 0.5], [0.12, 0.5]]];

        $this->putJson($this->url($document), [
            'page' => 1,
            'roles' => ['layer:A-WALL' => 'wall'],
            'boundaries' => $boundaries,
        ])->assertOk()->assertJsonPath('boundaries', $boundaries);

        $this->assertEquals($boundaries, $this->getJson($this->url($document, '?page=1'))->json('boundaries'));
        $this->assertSame([], $this->getJson($this->url($document, '?page=2'))->json('boundaries'));

        // The profile is replaced whole: a PUT without boundaries leaves none.
        $this->putJson($this->url($document), ['page' => 1, 'roles' => ['layer:A-WALL' => 'wall']])->assertOk();
        $this->assertSame([], $this->getJson($this->url($document, '?page=1'))->json('boundaries'));
    }

    public function test_malformed_boundaries_are_a_422_and_store_nothing(): void
    {
        $document = $this->document();
        $this->putJson($this->url($document), ['page' => 1, 'roles' => [], 'boundaries' => [[[0.2, 0.2], [0.4, 0.4]]]])->assertOk();

        foreach ([
            [[[0.2, 1.2], [0.4, 0.4]]],             // off the page
            [[[0.2, 0.2], [0.4, 0.4], [0.5, 0.5]]], // three points
            [[[0.2], [0.4, 0.4]]],                  // one coordinate
            [[[0.3, 0.3], [0.3, 0.3]]],             // degenerate
            array_fill(0, 201, [[0.1, 0.1], [0.2, 0.2]]),
        ] as $bad) {
            $this->putJson($this->url($document), ['page' => 1, 'roles' => [], 'boundaries' => $bad])->assertStatus(422);
        }
        $this->assertEquals([[[0.2, 0.2], [0.4, 0.4]]], $this->getJson($this->url($document, '?page=1'))->json('boundaries'));
    }
}
