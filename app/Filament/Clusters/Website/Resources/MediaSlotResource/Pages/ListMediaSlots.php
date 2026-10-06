<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Resources\MediaSlotResource\Pages;

use App\Filament\Clusters\Website\Actions\PublishSiteNowAction;
use App\Filament\Clusters\Website\Concerns\ShowsSitePublicationState;
use App\Filament\Clusters\Website\Resources\MediaSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMediaSlots extends ListRecords
{
    use ShowsSitePublicationState;

    protected static string $resource = MediaSlotResource::class;

    /**
     * The trait contributes the publish action and the status widget; a List page also needs
     * its create action, and a trait method cannot be extended, so this pair is written out.
     */
    protected function getHeaderActions(): array
    {
        return [
            PublishSiteNowAction::make(),
            Actions\CreateAction::make(),
        ];
    }
}
