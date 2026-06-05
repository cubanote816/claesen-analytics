<?php

namespace Modules\Prospects\Filament\Resources\Prospects\Pages;

use Modules\Prospects\Filament\Resources\Prospects\ProspectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ManageProspects extends ManageRecords
{
    protected static string $resource = ProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    // Filament resets selection when filters change (shouldDeselectAllRecordsWhenFiltered),
    // but not when tabs change — even though tabs also modify the query via modifyQueryUsing().
    // Without this override, IDs selected in one tab remain in $selectedTableRecords after
    // switching tabs, causing the BulkAction to run against a filtered query that excludes
    // those IDs, resulting in 0 records and a stale FAB badge.
    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();
        $this->deselectAllTableRecords();
    }

    public function getTabs(): array
    {
        return [
            'real_prospects' => Tab::make()
                ->label(__('prospects::resource.tabs.real_prospects'))
                ->modifyQueryUsing(fn ($query) => $query->where('is_tester', false)),
            'testers' => Tab::make()
                ->label(__('prospects::resource.tabs.testers'))
                ->modifyQueryUsing(fn ($query) => $query->where('is_tester', true)),
            'all' => Tab::make()
                ->label(__('prospects::resource.tabs.all')),
        ];
    }
}
