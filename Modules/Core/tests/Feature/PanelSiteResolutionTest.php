<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Tests\TestCase;

/**
 * CLA-598 (T1): a Filament panel manages exactly one site, resolved from
 * config('organizations.panel_sites') — never from request data.
 */
final class PanelSiteResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_panel_resolves_to_the_claesen_site(): void
    {
        $this->assertSame(Site::claesenId(), Site::forPanel('admin')?->id);
    }

    public function test_the_bertels_panel_has_no_site_until_its_row_exists(): void
    {
        $this->assertNull(Site::forPanel('bertels'));
    }

    public function test_the_bertels_panel_resolves_to_its_own_site_once_created(): void
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        $site = Site::factory()->create(['organization_id' => $organization->id, 'key' => 'electro-bertels']);

        $this->assertSame($site->id, Site::forPanel('bertels')?->id);
        $this->assertNotSame(Site::claesenId(), Site::forPanel('bertels')?->id);
    }

    public function test_an_unmapped_panel_never_falls_back_to_claesen(): void
    {
        $this->assertNull(Site::forPanel('unknown'));
    }

    public function test_without_a_current_panel_the_default_panel_decides(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(null);

        $this->assertSame(Site::claesenId(), Site::forPanel()?->id);
    }
}
