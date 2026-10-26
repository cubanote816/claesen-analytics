<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Resources\MediaSlotResource\Pages;

use App\Filament\Clusters\Website\Actions\PublishSiteNowAction;
use App\Filament\Clusters\Website\Concerns\ShowsSitePublicationState;
use App\Filament\Clusters\Website\Resources\MediaSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMediaSlot extends EditRecord
{
    use ShowsSitePublicationState;

    protected static string $resource = MediaSlotResource::class;

    /**
     * The trait contributes the publish action and the status widget. Delete is written out
     * explicitly rather than left to the parent: overriding getHeaderActions() to add one
     * action is exactly how the other one gets dropped without anyone noticing.
     */
    protected function getHeaderActions(): array
    {
        return [
            PublishSiteNowAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
