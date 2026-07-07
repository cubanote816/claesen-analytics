<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Modules\Safety\Filament\Widgets\InspectionsTrendChartWidget;
use Modules\Safety\Filament\Widgets\SafetyStatsWidget;
use Tests\TestCase;

final class DashboardWidgetPlacementTest extends TestCase
{
    private function bindRequestForRoute(string $uri, string $routeName): void
    {
        $request = Request::create($uri);
        $route = new Route('GET', $uri, []);
        $route->name($routeName);
        $request->setRouteResolver(fn () => $route);

        $this->app->instance('request', $request);
    }

    public function test_detail_widgets_are_hidden_on_general_dashboard_but_visible_on_inspections_page(): void
    {
        $widgets = [SafetyStatsWidget::class, InspectionsTrendChartWidget::class];

        $this->bindRequestForRoute('/', 'filament.admin.pages.dashboard');
        foreach ($widgets as $widget) {
            $this->assertFalse($widget::canView(), "{$widget} should be hidden on the general dashboard");
        }

        $this->bindRequestForRoute('/inspections', 'filament.admin.resources.inspections.index');
        foreach ($widgets as $widget) {
            $this->assertTrue($widget::canView(), "{$widget} should be visible on the inspections page");
        }
    }
}
