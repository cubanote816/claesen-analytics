<?php

namespace App\Filament\Clusters\Website\Resources\ProjectResource\Pages;

use App\Filament\Clusters\Website\Resources\ProjectResource;
use App\Filament\Clusters\Website\Widgets\PublicationStatusWidget;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Website\Services\StaticSitePublicationService;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish_now')
                ->label(__('website.publication.actions.publish_now'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading(__('website.publication.actions.publish_now_confirm_title'))
                ->modalDescription(__('website.publication.actions.publish_now_confirm_body'))
                ->action(function () {
                    app(StaticSitePublicationService::class)
                        ->requestRebuild('manual', force: true);

                    Notification::make()
                        ->title(__('website.publication.actions.publish_now_success'))
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PublicationStatusWidget::class,
        ];
    }
}
