<?php

namespace Modules\Mailing\Livewire;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Filament\Resources\CampaignResource;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Support\CampaignSendTracker;

/**
 * Panel-wide: polls only while this user's session tracks a send that has not finished,
 * then shows one closable toast per finished campaign (CLA-680). The bell row stays the
 * permanent record (CampaignFinishedNotifier).
 */
class CampaignProgressWatcher extends Component
{
    /**
     * A launch on the current page (another Livewire component) tracked a send: re-render so
     * the poll attribute appears without a page reload.
     */
    #[On('campaign-send-tracked')]
    public function startWatching(): void
    {
    }

    public function check(): void
    {
        $entries = CampaignSendTracker::pending();

        if ($entries === [] || auth()->id() === null) {
            return;
        }

        $keys = collect($entries)->where('type', 'dispatch')->pluck('value')->all();
        $ids  = collect($entries)->where('type', 'campaign')->pluck('value')->all();

        // Only what this session tracked; dispatch keys are further limited to the user's own campaigns.
        $campaigns = Campaign::query()
            ->select(['id', 'dispatch_key', 'status', 'description', 'sent_count', 'failed_count', 'skipped_count'])
            ->where(fn ($q) => $q
                ->whereIn('id', $ids)
                ->orWhere(fn ($q) => $q->whereIn('dispatch_key', $keys)->where('created_by', auth()->id())))
            ->get();

        foreach ($entries as $entry) {
            $campaign = $campaigns->first(fn (Campaign $c): bool => $entry['type'] === 'dispatch'
                ? $c->dispatch_key === $entry['value']
                : $c->id === $entry['value']);

            if ($campaign === null || ! in_array($campaign->status, [CampaignStatus::COMPLETED, CampaignStatus::FAILED, CampaignStatus::CANCELLED], true)) {
                continue;
            }

            if ($campaign->status !== CampaignStatus::CANCELLED) {
                $this->toast($campaign);
            }

            CampaignSendTracker::forget($entry['type'], $entry['value']);
        }
    }

    private function toast(Campaign $campaign): void
    {
        $failed = $campaign->status === CampaignStatus::FAILED;

        $notification = Notification::make()
            ->title(__(
                $failed ? 'mailing::resource.notifications.campaign_failed_title' : 'mailing::resource.notifications.campaign_finished_title',
                ['description' => $campaign->description ?: '#' . $campaign->id],
            ))
            ->body(__('mailing::resource.notifications.campaign_finished_body', [
                'sent'    => $campaign->sent_count,
                'failed'  => $campaign->failed_count,
                'skipped' => $campaign->skipped_count,
            ]))
            ->persistent()
            ->actions([
                Action::make('view')
                    ->label(__('mailing::resource.actions.view_campaign'))
                    ->url(CampaignResource::getUrl('view', ['record' => $campaign->id], panel: 'admin')),
            ]);

        ($failed ? $notification->danger() : $notification->success())->send();
    }

    public function render()
    {
        return view('mailing::livewire.campaign-progress-watcher', [
            'polling' => CampaignSendTracker::pending() !== [],
        ]);
    }
}
