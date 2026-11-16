<?php

namespace Modules\Mailing\Tests\Feature;

use App\Support\Documentation\ModuleGuide;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Mailing\Filament\Pages\MailingDocumentation;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-693: the Mailing user guide page. Access follows the campaign resource
 * (everyone except technician); the guide renders every UI name from live keys.
 */
class MailingDocumentationPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string ...$roles): User
    {
        $this->withoutVite();
        // MFA enrolment for admin roles is covered elsewhere; it would redirect before the page.
        config(['core.panel_mfa_enforced' => false]);
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create(['organization_id' => Organization::claesenId(), 'is_active' => true]);
        $user->assignRole($roles);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    /**
     * campaign_manager and marketer only grant Mailing rights; opening the panel needs a
     * panel role too (User::hasPanelAccess), so they are tested on top of viewer.
     */
    public static function allowedRoles(): iterable
    {
        yield 'super_admin' => [['super_admin']];
        yield 'admin' => [['admin']];
        yield 'viewer' => [['viewer']];
        yield 'viewer + campaign_manager' => [['viewer', 'campaign_manager']];
        yield 'viewer + marketer' => [['viewer', 'marketer']];
    }

    /** @param  list<string>  $roles */
    #[DataProvider('allowedRoles')]
    public function test_roles_with_mailing_access_can_read_the_guide(array $roles): void
    {
        $this->actingAsRole(...$roles);

        $this->assertTrue(MailingDocumentation::canAccess());
        $this->get(MailingDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Send now');
    }

    public function test_technician_is_denied(): void
    {
        $this->actingAsRole('technician');

        $this->assertFalse(MailingDocumentation::canAccess());
        $this->get(MailingDocumentation::getUrl())->assertForbidden();
    }

    public function test_both_guides_resolve_every_placeholder(): void
    {
        $directory = ModuleGuide::directoryFor('Mailing');

        foreach (['en', 'nl'] as $locale) {
            $guide = ModuleGuide::render($directory, $locale);

            $this->assertSame($locale, $guide->locale);
            $this->assertSame([], $guide->missingKeys, "Unresolved keys in {$locale}.md");
            $this->assertSame('Mailing', $guide->meta['module']);
            $this->assertNotEmpty($guide->meta['summary']);
            $this->assertNotEmpty($guide->toc);
        }
    }

    public function test_page_is_in_the_documentation_navigation_group(): void
    {
        $this->actingAsRole('admin');

        $this->assertSame(__('navigation.groups.documentation'), MailingDocumentation::getNavigationGroup());
        $this->assertSame('documentation/mailing', MailingDocumentation::getSlug());
    }

    public function test_dutch_locale_renders_the_dutch_guide(): void
    {
        $this->actingAsRole('admin');
        app()->setLocale('nl');

        // A real request re-resolves the locale from the user, so assert through Livewire.
        $page = Livewire::test(MailingDocumentation::class)->assertOk()->assertSee('Nu verzenden');
        $this->assertSame('Handleiding Mailing', $page->instance()->getTitle());
    }
}
