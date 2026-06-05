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
    // Without this override, IDs from the previous tab persist in the PHP snapshot and the
    // Alpine selectedRecords Set, causing the FAB to show a stale badge count and BulkActions
    // to run against a tab-filtered query that excludes those IDs (0 results).
    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();
        // Directly wipe the PHP snapshot — deselectAllTableRecords() only dispatches a browser
        // event (for Alpine visual state) and never clears these PHP-side properties.
        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        // Also dispatch the browser event so Alpine clears its local selectedRecords Set and
        // re-evaluates x-bind:checked, unchecking all visible rows.
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
