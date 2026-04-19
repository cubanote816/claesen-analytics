<?php

namespace Modules\Performance\Filament\Widgets;

use Filament\Widgets\Widget;
use Modules\Performance\Services\CashFlowWatchdogService;

class CashFlowWatchdogWidget extends Widget
{
    protected string $view = 'performance::filament.widgets.cash-flow-watchdog-widget';
    protected int | string | array $columnSpan = 'full';
    
    protected static bool $isLazy = true;

    public string|array|null $report = null;

    public function mount()
    {
        // Try to fetch report synchronously to render quickly if cached.
        try {
            $service = app(CashFlowWatchdogService::class);
            $this->report = $service->generateRiskReport();
        } catch (\Exception $e) {
            $this->report = __('performance::dashboard.error_loading');
        }
    }

    public function getLoadingIndicator(): \Illuminate\Contracts\View\View
    {
        return view('performance::filament.widgets.skeletons.watchdog');
    }
}
