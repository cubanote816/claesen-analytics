<?php

namespace Modules\Core\Tests\Feature;

use App\Support\Documentation\ModuleGuide;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Pages\CoreDocumentation;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-719: the Users and access guide. Access follows the users resource (user decision),
 * which only a super admin can open; the guide renders every UI name from live keys.
 */
class CoreDocumentationPageTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string ...$roles): User
    {
        $this->withoutVite();
        // MFA enrolment is covered elsewhere; it would redirect before the page.
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

    public function test_super_admin_can_read_the_guide(): void
    {
        $this->actingAsRole('super_admin');

        $this->assertTrue(CoreDocumentation::canAccess());
        $this->get(CoreDocumentation::getUrl())
            ->assertOk()
            ->assertSee('Resend activation email')
            ->assertSee('Revoke sessions');
    }

    public static function deniedRoles(): iterable
    {
        yield 'admin' => ['admin'];
        yield 'viewer' => ['viewer'];
        yield 'technician' => ['technician'];
    }

    #[DataProvider('deniedRoles')]
    public function test_roles_that_cannot_open_users_are_denied(string $role): void
    {
        $this->actingAsRole($role);

        $this->assertFalse(CoreDocumentation::canAccess());
        $this->get(CoreDocumentation::getUrl())->assertForbidden();
    }

    public function test_both_guides_resolve_every_placeholder(): void
    {
        $directory = ModuleGuide::directoryFor('Core');

        foreach (['en', 'nl'] as $locale) {
            $guide = ModuleGuide::render($directory, $locale);

            $this->assertSame($locale, $guide->locale);
            $this->assertSame([], $guide->missingKeys, "Unresolved keys in {$locale}.md");
            $this->assertSame('Core', $guide->meta['module']);
            $this->assertNotEmpty($guide->meta['summary']);
            $this->assertNotEmpty($guide->toc);
        }
    }

    public function test_page_is_in_the_documentation_navigation_group(): void
    {
        $this->actingAsRole('super_admin');

        $this->assertSame(__('navigation.groups.documentation'), CoreDocumentation::getNavigationGroup());
        $this->assertSame('documentation/core', CoreDocumentation::getSlug());
    }

    public function test_dutch_locale_renders_the_dutch_guide(): void
    {
        $this->actingAsRole('super_admin');
        app()->setLocale('nl');

        // A real request re-resolves the locale from the user, so assert through Livewire.
        $page = Livewire::test(CoreDocumentation::class)->assertOk()->assertSee('Rollen wijzigen');
        $this->assertSame('Gebruikers en toegang', $page->instance()->getTitle());
    }
}
