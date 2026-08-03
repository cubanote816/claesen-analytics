<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Modules\Core\Services\AccessAnalyticsService;

class AccessAnalyticsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'access-analytics';

    protected string $view = 'core::filament.pages.access-analytics';

    public string $period = '30';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.user_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::access_analytics.navigation_label');
    }

    public function getTitle(): string
    {
        return __('core::access_analytics.title');
    }

    public function getPeriodOptions(): array
    {
        return [
            '7' => __('core::access_analytics.periods.7'),
            '30' => __('core::access_analytics.periods.30'),
            '90' => __('core::access_analytics.periods.90'),
        ];
    }

    public function getPeriodLabel(): string
    {
        return $this->getPeriodOptions()[$this->period] ?? $this->getPeriodOptions()['30'];
    }

    public function getAnalyticsData(): array
    {
        return app(AccessAnalyticsService::class)->dashboardData((int) $this->period);
    }
}
