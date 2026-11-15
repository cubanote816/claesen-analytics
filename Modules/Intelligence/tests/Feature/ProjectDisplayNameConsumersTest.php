<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Intelligence\Filament\Pages\ProjectIntelligenceDetail;
use Modules\Intelligence\Services\MaterialIntelligenceService;
use Modules\Intelligence\Services\ProjectSimilarityService;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorMaterial;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-688 (S2) — in CAFCA `project.name` holds the CLIENT; the project name is
 * `project.descr`. Other consumers must present descr as the project name and
 * take the client from the mirror relation.
 */
class ProjectDisplayNameConsumersTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT_NAME = 'Limburg - Veldverlichting Diepenbeek';

    private const CLIENT_NAME = 'TC Tenkie';

    protected function setUp(): void
    {
        parent::setUp();
        MirrorRelation::create(['id' => 100, 'name' => self::CLIENT_NAME]);
        MirrorProject::create([
            'id' => 'P20260050',
            'name' => self::CLIENT_NAME,
            'descr' => self::PROJECT_NAME,
            'relation_id' => 100,
            'category' => 'Sport',
            'fl_active' => true,
        ]);
    }

    public function test_project_detail_page_title_is_project_name_and_subtitle_is_client(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $component = Livewire::test(ProjectIntelligenceDetail::class, ['projectId' => 'P20260050']);

        $this->assertSame(self::PROJECT_NAME, $component->instance()->getTitle());
        $component->assertSee('P20260050')
            ->assertSee(self::PROJECT_NAME)
            ->assertSee(self::CLIENT_NAME);
    }

    public function test_material_usage_summary_uses_project_name(): void
    {
        $material = MirrorMaterial::create(['id' => 1, 'ref' => 'R1', 'description' => 'Armatuur']);
        MirrorCost::create(['id' => 'C1', 'project_id' => 'P20260050', 'art_id' => 1, 'cost_price' => 10, 'quantity' => 1, 'date' => '2026-06-15']);

        $summary = app(MaterialIntelligenceService::class)->buildUsageSummary($material);

        $this->assertStringContainsString(self::PROJECT_NAME, $summary);
        $this->assertStringContainsString(self::CLIENT_NAME, $summary);
        $this->assertStringNotContainsString('Project: '.self::CLIENT_NAME.' (', $summary);
    }

    public function test_similar_projects_context_carries_project_name_and_client(): void
    {
        $results = app(ProjectSimilarityService::class)->findSimilar('Sport');

        $this->assertCount(1, $results);
        $this->assertSame(self::PROJECT_NAME, $results[0]['name']);
        $this->assertSame(self::CLIENT_NAME, $results[0]['client']);
    }
}
