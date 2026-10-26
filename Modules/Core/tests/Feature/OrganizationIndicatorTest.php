<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-600: each panel's top bar names the company it is working on.
 */
final class OrganizationIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    private function createBertelsSite(): void
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels', 'name' => 'Electro Bertels']);
        Site::factory()->create(['organization_id' => $organization->id, 'key' => 'electro-bertels']);
    }

    public function test_the_admin_panel_names_claesen(): void
    {
        $claesen = Organization::query()->findOrFail(Organization::claesenId());

        $this->get('/')
            ->assertOk()
            ->assertSee('data-organization-indicator="'.$claesen->slug.'"', false)
            ->assertSee($claesen->name);
    }

    public function test_the_bertels_panel_names_electro_bertels_with_its_own_brand_name(): void
    {
        $this->createBertelsSite();

        $this->get('/bertels')
            ->assertOk()
            ->assertSee('data-organization-indicator="electro-bertels"', false)
            ->assertSee('Electro Bertels');
    }

    public function test_the_bertels_panel_shows_no_indicator_when_its_site_does_not_exist(): void
    {
        $this->get('/bertels')
            ->assertOk()
            ->assertDontSee('data-organization-indicator', false);
    }
}
