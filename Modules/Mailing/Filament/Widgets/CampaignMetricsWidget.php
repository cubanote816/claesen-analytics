<?php

namespace Modules\Mailing\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\MessageEvent;

class CampaignMetricsWidget extends BaseWidget
{
    public ?int $campaignId = null;

    protected ?string $pollingInterval = '5s';

    protected static bool $isLazy = true;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        if (! $this->campaignId) {
            return [];
        }

        $messageIds = CampaignMessage::where('campaign_id', $this->campaignId)->pluck('id');

        $sent = CampaignMessage::where('campaign_id', $this->campaignId)
            ->where('status', 'sent')
            ->count();

        // Unique-message counts per event type.
        // withoutGlobalScope('chronological') drops the ORDER BY occurred_at that the
        // MessageEvent model injects globally — MySQL ONLY_FULL_GROUP_BY rejects it
        // when occurred_at is not in the GROUP BY clause.
        $eventCounts = MessageEvent::withoutGlobalScope('chronological')
            ->whereIn('message_id', $messageIds)
            ->selectRaw('event_type, COUNT(DISTINCT message_id) as cnt')
            ->groupBy('event_type')
            ->pluck('cnt', 'event_type');

        $delivered    = (int) ($eventCounts[MessageEventType::DELIVERED->value]    ?? 0);
        $opened       = (int) ($eventCounts[MessageEventType::OPENED->value]       ?? 0);
        $clicked      = (int) ($eventCounts[MessageEventType::CLICKED->value]      ?? 0);
        $bouncedHard  = (int) ($eventCounts[MessageEventType::BOUNCED_HARD->value] ?? 0);
        $bouncedSoft  = (int) ($eventCounts[MessageEventType::BOUNCED_SOFT->value] ?? 0);
        $complained   = (int) ($eventCounts[MessageEventType::COMPLAINED->value]   ?? 0);
        $unsubscribed = (int) ($eventCounts[MessageEventType::UNSUBSCRIBED->value] ?? 0);

        $ctr  = $sent > 0 ? round(($clicked  / $sent)   * 100, 2) : 0;
        $ctor = $opened > 0 ? round(($clicked / $opened) * 100, 2) : 0;

        $hardBounceRate = $sent > 0 ? $bouncedHard / $sent : 0;
        $spamRate       = $sent > 0 ? $complained  / $sent : 0;

        $hardBounceAlert = $hardBounceRate > config('mailing.hard_bounce_alert', 0.05);
        $spamAlert       = $spamRate       > config('mailing.spam_rate_alert',   0.0008);

        return [
            // KPI principal — clics
            Stat::make(__('mailing::resource.metrics.clicks'), $clicked)
                ->description("CTR {$ctr}% · CTOR {$ctor}%")
                ->color('info')
                ->icon('heroicon-o-cursor-arrow-rays'),

            Stat::make(__('mailing::resource.metrics.sent'), $sent)
                ->description(__('mailing::resource.metrics.delivered') . ': ' . $delivered)
                ->color('primary')
                ->icon('heroicon-o-paper-airplane'),

            // Aperturas — señal débil
            Stat::make(__('mailing::resource.metrics.opens'), $opened)
                ->description(__('mailing::resource.metrics.opens_note'))
                ->color('warning')
                ->icon('heroicon-o-eye'),

            // Hard bounces — alerta si > 5 %
            Stat::make(__('mailing::resource.metrics.hard_bounces'), $bouncedHard)
                ->description(
                    $hardBounceAlert
                        ? __('mailing::resource.metrics.alert_high_bounce')
                        : round($hardBounceRate * 100, 2) . '%'
                )
                ->color($hardBounceAlert ? 'danger' : 'gray')
                ->icon('heroicon-o-exclamation-triangle'),

            // Quejas — alerta si > 0.08 %
            Stat::make(__('mailing::resource.metrics.complained'), $complained)
                ->description(
                    $spamAlert
                        ? __('mailing::resource.metrics.alert_high_spam')
                        : round($spamRate * 100, 4) . '%'
                )
                ->color($spamAlert ? 'danger' : 'gray')
                ->icon('heroicon-o-flag'),

            Stat::make(__('mailing::resource.metrics.unsubscribed'), $unsubscribed)
                ->color('warning')
                ->icon('heroicon-o-user-minus'),

            Stat::make(__('mailing::resource.metrics.soft_bounces'), $bouncedSoft)
                ->color('gray')
                ->icon('heroicon-o-arrow-uturn-left'),
        ];
    }
}
