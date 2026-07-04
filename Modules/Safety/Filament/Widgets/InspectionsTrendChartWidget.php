<?php

declare(strict_types=1);

namespace Modules\Safety\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Modules\Safety\Models\Inspection;

class InspectionsTrendChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public ?string $filter = '30';

    public static function canView(): bool
    {
        // Detalle operativo de Safety: vive en la página de Inspections, no en el dashboard general.
        return ! request()->routeIs('filament.admin.pages.dashboard');
    }

    public function getHeading(): string
    {
        return __('safety::inspections.widgets.trend_chart.heading');
    }

    public function getDescription(): ?string
    {
        return __('safety::inspections.widgets.trend_chart.description');
    }

    protected function getFilters(): ?array
    {
        return [
            '7'  => __('safety::inspections.widgets.periods.7'),
            '30' => __('safety::inspections.widgets.periods.30'),
            '90' => __('safety::inspections.widgets.periods.90'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = max(1, (int) ($this->filter ?? '30'));
        $rangeStart = now()->subDays($days - 1)->startOfDay();

        $counts = Inspection::selectRaw('DATE(completed_at) as date, COUNT(*) as count')
            ->where('completed_at', '>=', $rangeStart)
            ->groupBy('date')
            ->pluck('count', 'date');

        $labels = [];
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d/m');
            $data[] = (int) ($counts[$date->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label'           => __('safety::inspections.widgets.trend_chart.dataset_label'),
                    'data'            => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'borderColor'     => '#3b82f6',
                    'fill'            => true,
                    'tension'         => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
