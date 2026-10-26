<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Resources\AnnouncementResource\Pages;

use App\Filament\Clusters\Website\Resources\AnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
