<?php

namespace Modules\Mailing\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Filament\Resources\CampaignResource;
use Modules\Mailing\Models\Campaign;

/**
 * Tells the person who launched a campaign that its run reached the final state.
 *
 * Callers invoke this only after winning the terminal status update (affected rows === 1),
 * which is what makes the notice fire once per run. It never throws: a notification problem
 * must not fail or retry a send.
 */
class CampaignFinishedNotifier
{
    public static function notify(Campaign $campaign, ?int $userId = null, ?string $locale = null): void
    {
        try {
            $campaign->refresh();
            $user = self::recipient($campaign, $userId);

            if ($user === null) {
                return;
            }

            $locale ??= config('app.locale');
            $failed = $campaign->status === CampaignStatus::FAILED;
            $title  = __(
                $failed ? 'mailing::resource.notifications.campaign_failed_title' : 'mailing::resource.notifications.campaign_finished_title',
                ['description' => $campaign->description ?: '#' . $campaign->id],
                $locale,
            );
            $body = __('mailing::resource.notifications.campaign_finished_body', [
                'sent'    => $campaign->sent_count,
                'failed'  => $campaign->failed_count,
                'skipped' => $campaign->skipped_count,
            ], $locale);

            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->actions([
                    Action::make('view')
                        ->label(__('mailing::resource.actions.view_campaign', [], $locale))
                        ->url(CampaignResource::getUrl('view', ['record' => $campaign], panel: 'admin')),
                ]);

            // notifyNow(): write the bell row synchronously. sendToDatabase() queues it, and a
            // queue outage after the terminal transition would lose the notice for good.
            $user->notifyNow(($failed ? $notification->danger() : $notification->success())->toDatabase());
        } catch (\Throwable $e) {
            Log::warning('CampaignFinishedNotifier: notification could not be sent.', [
                'campaign_id' => $campaign->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private static function recipient(Campaign $campaign, ?int $userId): ?User
    {
        $id = $userId ?? $campaign->approved_by ?? $campaign->created_by;

        return $id === null ? null : User::find($id);
    }
}
