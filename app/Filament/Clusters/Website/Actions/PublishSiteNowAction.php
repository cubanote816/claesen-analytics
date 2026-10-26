<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Modules\Website\Services\StaticSitePublicationService;

/**
 * The one explicit "publish the website" action.
 *
 * Defined once because it now belongs on every screen where content is edited: with
 * deliberate publishing (2026-10-06) this is the only trigger left, and a second copy of the
 * definition would be a second place for the confirmation text, the icon and the reason to
 * drift apart.
 *
 * `force: true` skips the debounce window on purpose. A person who pressed the button has
 * already decided, and should not have to wait 20 seconds to find out whether the click
 * registered.
 *
 * Note on vocabulary: the review screens already have a per-row action also called
 * "publish", and it means something else — approving one page into the manifest. This one
 * rebuilds the whole site.
 */
class PublishSiteNowAction
{
    public static function make(): Action
    {
        return Action::make('publish_now')
            ->label(__('website.publication.actions.publish_now'))
            ->icon('heroicon-o-cloud-arrow-up')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading(__('website.publication.actions.publish_now_confirm_title'))
            ->modalDescription(__('website.publication.actions.publish_now_confirm_body'))
            ->action(function (): void {
                // With the pipeline off `requestRebuild()` does nothing, so a success notice
                // would announce a publication that never happened (same gate as the
                // per-section action).
                if (! config('static_site.enabled')) {
                    Notification::make()
                        ->title(__('website.publication.actions.publish_section_disabled'))
                        ->warning()
                        ->send();

                    return;
                }

                app(StaticSitePublicationService::class)
                    ->requestRebuild(reason: 'manual', force: true);

                Notification::make()
                    ->title(__('website.publication.actions.publish_now_success'))
                    ->success()
                    ->send();
            });
    }
}
