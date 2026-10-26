<?php

declare(strict_types=1);

namespace Modules\Employee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Modules\Core\Models\User;
use Modules\Employee\Filament\Widgets\EmployeeHoursSummaryWidget;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The widget is placed on the main admin dashboard only. Filament 5.10 enforces canView() on every
 * Livewire request, where request()->routeIs() sees the update endpoint instead of the page, so the
 * placement check has to resolve the page the component was rendered on.
 */
final class EmployeeHoursSummaryWidgetPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        return $user;
    }

    /** @return array{snapshot: string, token: string}|null */
    private function lazyWidget(string $html): ?array
    {
        $pattern = '/wire:snapshot="([^"]+)"[^>]*wire:name="'
            . preg_quote(EmployeeHoursSummaryWidget::class, '/')
            . '"[^>]*x-intersect="\$wire\.__lazyLoad\(&#039;([^&]+)&#039;\)"/';

        if (! preg_match($pattern, $html, $matches)) {
            return null;
        }

        return ['snapshot' => html_entity_decode($matches[1], ENT_QUOTES), 'token' => $matches[2]];
    }

    public function test_the_dashboard_renders_the_widget_and_its_livewire_requests_are_not_forbidden(): void
    {
        $this->actingAs($this->superAdmin());

        $page = $this->get('/')->assertSuccessful();
        $widget = $this->lazyWidget($page->getContent());

        $this->assertNotNull($widget, 'The dashboard must render the lazy hours summary widget.');

        $this->withHeaders(['X-Livewire' => '1'])
            ->postJson(EndpointResolver::updatePath(), [
                'components' => [[
                    'snapshot' => $widget['snapshot'],
                    'updates' => (object) [],
                    'calls' => [['path' => '', 'method' => '__lazyLoad', 'params' => [$widget['token']]]],
                ]],
            ])
            ->assertSuccessful();
    }

    public function test_a_non_dashboard_page_does_not_render_the_widget(): void
    {
        $this->actingAs($this->superAdmin());

        $html = $this->get('/users')->assertSuccessful()->getContent();

        $this->assertNull($this->lazyWidget($html));
        $this->assertStringNotContainsString('EmployeeHoursSummaryWidget', $html);
    }

    public function test_can_view_is_false_outside_a_livewire_request_on_another_route(): void
    {
        $this->actingAs($this->superAdmin());
        $this->get('/users')->assertSuccessful();

        $this->assertFalse(EmployeeHoursSummaryWidget::canView());
    }
}
