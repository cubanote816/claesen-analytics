<?php

namespace App\Filament\Clusters\Website\Widgets;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Http;
use Modules\Website\App\Enums\PublicationStatus;
use Modules\Website\Models\PublicationState;

class PublicationStatusWidget extends StatsOverviewWidget
{
    // Auto-refresh every 15 seconds — keeps status live without manual reload.
    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $state  = PublicationState::current();
        $health = $this->fetchHealth();

        return array_values(array_filter([
            $this->backendStatusStat($state, $health),
            $this->lastAcceptedStat($state, $health),
            $this->frontendBuildStat($health),
        ]));
    }

    // ─── Stats ───────────────────────────────────────────────────────────────

    private function backendStatusStat(PublicationState $state, ?array $health): Stat
    {
        $status = $state->status;

        // If frontend reports an active build, surface that over our "accepted" label.
        if ($health && ($health['building'] ?? false) && $status === PublicationStatus::ACCEPTED) {
            return Stat::make(
                __('website.publication.widget.status_label'),
                __('website.publication.widget.building'),
            )
            ->color('info')
            ->icon('heroicon-o-arrow-path');
        }

        $stat = Stat::make(
            __('website.publication.widget.status_label'),
            __('website.publication.status.' . $status->value),
        )
        ->color($status->getColor())
        ->icon($this->statusIcon($status));

        // Surface the error message directly so the admin doesn't have to dig.
        if ($status === PublicationStatus::ERROR && $state->last_error) {
            $stat->description(mb_substr($state->last_error, 0, 120));
        }

        return $stat;
    }

    private function lastAcceptedStat(PublicationState $state, ?array $health): Stat
    {
        // Prefer health.last_success_at — that's when the build actually completed,
        // which is more meaningful than our 202 timestamp.
        $at = null;
        if ($health && !empty($health['last_success_at'])) {
            try {
                $at = Carbon::parse($health['last_success_at']);
            } catch (\Throwable) {}
        }
        $at ??= $state->last_accepted_at;

        return Stat::make(
            __('website.publication.widget.last_accepted'),
            $at ? $at->diffForHumans() : __('website.publication.widget.no_data'),
        )
        ->color($at ? 'success' : 'gray')
        ->icon('heroicon-o-clock');
    }

    private function frontendBuildStat(?array $health): ?Stat
    {
        if (!config('static_site.health_url')) {
            return null;
        }

        if ($health === null) {
            return Stat::make(
                __('website.publication.widget.build_status'),
                __('website.publication.widget.unreachable'),
            )
            ->color('gray')
            ->icon('heroicon-o-signal-slash');
        }

        $release = $health['current_release'] ?? null;
        $value   = $release
            ? __('website.publication.widget.release', ['release' => $release])
            : __('website.publication.widget.no_data');

        $stat = Stat::make(
            __('website.publication.widget.build_status'),
            $value,
        )
        ->color(($health['building'] ?? false) ? 'info' : 'success')
        ->icon('heroicon-o-server');

        if ($health['building'] ?? false) {
            $stat->description(__('website.publication.widget.building'));
        }

        return $stat;
    }

    // ─── Health check ─────────────────────────────────────────────────────────

    private function fetchHealth(): ?array
    {
        $url = config('static_site.health_url');

        if (!$url) {
            return null;
        }

        try {
            $response = Http::timeout(2)->get($url);

            if ($response->ok()) {
                return $response->json();
            }
        } catch (\Throwable) {
            // Silent — the widget must never break if the frontend is unreachable.
        }

        return null;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function statusIcon(PublicationStatus $status): string
    {
        return match ($status) {
            PublicationStatus::IDLE     => 'heroicon-o-check-circle',
            PublicationStatus::PENDING  => 'heroicon-o-clock',
            PublicationStatus::ACCEPTED => 'heroicon-o-paper-airplane',
            PublicationStatus::ERROR    => 'heroicon-o-exclamation-triangle',
        };
    }
}
