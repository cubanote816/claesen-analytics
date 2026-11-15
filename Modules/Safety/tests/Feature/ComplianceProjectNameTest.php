<?php

namespace Modules\Safety\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Modules\Safety\Services\ComplianceService;
use Tests\TestCase;

/**
 * CLA-688 (S2) — the Safety PWA compliance payload keeps `project_name` but it
 * must be the real project name (descr), with the client additively in `client_name`.
 */
class ComplianceProjectNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_payload_uses_project_name_and_adds_client_name(): void
    {
        MirrorRelation::create(['id' => 100, 'name' => 'TC Tenkie']);
        MirrorProject::create([
            'id' => 'P1', 'name' => 'TC Tenkie', 'descr' => 'Veldverlichting Diepenbeek',
            'relation_id' => 100, 'fl_active' => true,
        ]);
        MirrorProject::create(['id' => 'P2', 'name' => 'Intern', 'fl_active' => true]);

        $rows = app(ComplianceService::class)->getNonCompliantProjects(30)->keyBy('project_id');

        $this->assertSame('Veldverlichting Diepenbeek', $rows['P1']['project_name']);
        $this->assertSame('TC Tenkie', $rows['P1']['client_name']);
        $this->assertSame('Intern', $rows['P2']['project_name']);
        $this->assertNull($rows['P2']['client_name']);
    }
}
