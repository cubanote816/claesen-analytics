<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Modules\Core\Support\CurrentPage;
use Modules\Employee\Filament\Widgets\EmployeeHoursSummaryWidget;
use Tests\TestCase;

final class CurrentPageTrustTest extends TestCase
{
    public function test_unsigned_input_cannot_override_a_non_dashboard_route(): void
    {
        $request = Request::create('/users', 'GET', [
            'components' => [['snapshot' => json_encode([
                'memo' => ['path' => '/', 'method' => 'GET'],
            ])]],
        ]);
        $request->headers->set('X-Livewire', '1');
        $route = (new Route('GET', 'users', fn () => ''))
            ->name('filament.admin.resources.users.index');
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);

        $this->assertFalse(EmployeeHoursSummaryWidget::canView());
    }

    public function test_empty_original_path_fails_closed(): void
    {
        $request = Request::create('/livewire-test/update', 'POST', [
            'components' => [['snapshot' => json_encode([
                'memo' => ['path' => '', 'method' => 'GET'],
            ])]],
        ]);
        $request->headers->set('X-Livewire', '1');
        app()->instance('request', $request);

        $this->assertFalse(CurrentPage::is('filament.admin.pages.dashboard'));
    }
}
