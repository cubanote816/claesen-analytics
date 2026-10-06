<?php

namespace App\Filament\Clusters\Website\Resources\ProjectResource\Pages;

use App\Filament\Clusters\Website\Actions\PublishSiteNowAction;
use App\Filament\Clusters\Website\Concerns\ShowsSitePublicationState;
use App\Filament\Clusters\Website\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    use ShowsSitePublicationState;

    protected static string $resource = ProjectResource::class;

    /**
     * The trait already contributes the publish action and the status widget. A List page
     * also needs its create action, and a trait method cannot be extended, so this pair is
     * written out here — with the action taken from the one shared definition rather than
     * copied.
     */
    protected function getHeaderActions(): array
    {
        return [
            PublishSiteNowAction::make(),
            Actions\CreateAction::make(),
        ];
    }
}
