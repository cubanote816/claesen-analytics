<?php

namespace Modules\Safety\Filament\Resources\InspectionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Safety\Filament\Resources\InspectionResource;
use Modules\Safety\Filament\Widgets\SafetyStatsWidget;
use Modules\Safety\Filament\Widgets\LatestInspectionsWidget;
use Modules\Safety\Filament\Widgets\InspectionsTrendChartWidget;

class ListInspections extends ListRecords
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SafetyStatsWidget::class,
            InspectionsTrendChartWidget::class,
            LatestInspectionsWidget::class,
        ];
    }
}
