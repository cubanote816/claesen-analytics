<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Support\Documentation\ModuleGuide;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Website\Filament\Pages\WebsiteDocumentation;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-720: the Website guide on both panels. Access follows the projects resource and never the
 * technician role (user decision 2026-11-26); the bertels panel registers the page by hand.
 */
class WebsiteDocumentationPageTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPanelSites;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // MFA enrolment is covered elsewhere; it would redirect before the page.
        config(['core.panel_mfa_enforced' => false]);
        $this->seedPanelSites();
    }

    private function actingAsRole(string $role, ?int $organizationId = null, string $panel = 'admin'): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create(['organization_id' => $organizationId ?? Organization::claesenId(), 'is_active' => true]);
        $user->assignRole($role);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel($panel));

        return $user;
    }

    public function test_a_panel_user_reads_the_guide_on_the_claesen_panel(): void
    {
        $this->actingAsRole('viewer');

        $this->assertTrue(WebsiteDocumentation::canAccess());
        $this->get(WebsiteDocumentation::getUrl(panel: 'admin'))
            ->assertOk()
            ->assertSee('Publish now')
            ->assertSee('Save draft');
    }

    public function test_technician_is_denied(): void
    {
        $this->actingAsRole('technician');

        $this->assertFalse(WebsiteDocumentation::canAccess());
        $this->get(WebsiteDocumentation::getUrl(panel: 'admin'))->assertForbidden();
    }

    public function test_an_electro_bertels_member_reads_the_guide_on_the_bertels_panel(): void
    {
        $bertels = Site::query()->where('key', config('organizations.panel_sites.bertels'))->firstOrFail();
        $this->actingAsRole('viewer', $bertels->organization_id, 'bertels');

        $this->assertSame('/bertels/documentation/website', parse_url(WebsiteDocumentation::getUrl(panel: 'bertels'), PHP_URL_PATH));
        $this->get(WebsiteDocumentation::getUrl(panel: 'bertels'))
            ->assertOk()
            ->assertSee('Publish now');
    }

    /** Independent verification: on /bertels a technician of Electro Bertels and an outsider are denied. */
    public function test_bertels_guide_denies_technicians_and_outsiders(): void
    {
        config(['organizations.enforce' => false]);
        $organizationId = Site::query()->where('key', config('organizations.panel_sites.bertels'))->firstOrFail()->organization_id;
        $url = WebsiteDocumentation::getUrl(panel: 'bertels');

        $this->actingAsRole('technician', $organizationId, 'bertels');
        $this->get($url)->assertForbidden();

        $this->actingAsRole('viewer', Organization::claesenId(), 'bertels');
        $this->get($url)->assertForbidden();
    }

    public function test_both_guides_resolve_every_placeholder(): void
    {
        $directory = ModuleGuide::directoryFor('Website');

        foreach (['en', 'nl'] as $locale) {
            $guide = ModuleGuide::render($directory, $locale);

            $this->assertSame($locale, $guide->locale);
            $this->assertSame([], $guide->missingKeys, "Unresolved keys in {$locale}.md");
            $this->assertSame('Website', $guide->meta['module']);
            $this->assertNotEmpty($guide->toc);
        }
    }

    public function test_page_is_in_the_documentation_navigation_group(): void
    {
        $this->actingAsRole('viewer');

        $this->assertSame(__('navigation.groups.documentation'), WebsiteDocumentation::getNavigationGroup());
        $this->assertSame('documentation/website', WebsiteDocumentation::getSlug());
    }

    public function test_dutch_locale_renders_the_dutch_guide(): void
    {
        $this->actingAsRole('viewer');
        app()->setLocale('nl');

        // A real request re-resolves the locale from the user, so assert through Livewire.
        Livewire::test(WebsiteDocumentation::class)->assertOk()->assertSee('Nu publiceren');
    }
}
