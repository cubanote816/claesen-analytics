<?php

namespace Modules\Prospects\Filament\Resources\SyncHistoryResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Modules\Prospects\Filament\Resources\SyncHistoryResource;

class ViewSyncHistory extends ViewRecord
{
    protected static string $resource = SyncHistoryResource::class;

    protected string $view = 'prospects::filament.resources.sync-history.view';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return str($this->record->command)
            ->after(':')
            ->after('sync-')
            ->replace('-', ' ')
            ->title()
            ->value();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('prospects::resource.actions.back_to_list'))
                ->url(SyncHistoryResource::getUrl('index'))
                ->icon(Heroicon::ArrowLeft)
                ->color('gray'),
        ];
    }
}
