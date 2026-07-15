<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\AccessEvent;
use Modules\Core\Models\AuthAttempt;
use Modules\Core\Models\AuthSecurityAlert;
use Modules\Core\Models\User;
use Modules\Core\Notifications\SecurityAlertNotification;

class AccessAnalyticsService
{
    public function recordLogin(User $user, string $appSource, string $authChannel, ?Request $request = null, array $metadata = []): AccessEvent
    {
        $event = $this->storeEvent(
            user: $user,
            eventType: AccessEvent::EVENT_LOGIN,
            appSource: $appSource,
            authChannel: $authChannel,
            request: $request,
            metadata: $metadata,
        );

        $occurredAt = $event->occurred_at ?? now();
        $updates = [
            'last_active_at' => $occurredAt,
        ];

        if ($this->userColumnExists('last_login_at')) {
            $updates['last_login_at'] = $occurredAt;
        }

        if ($this->userColumnExists('last_login_app_source')) {
            $updates['last_login_app_source'] = $this->normalizeSource($appSource);
        }

        if ($this->userColumnExists('last_login_channel')) {
            $updates['last_login_channel'] = $this->normalizeChannel($authChannel);
        }

        $user->forceFill($updates)->saveQuietly();

        return $event;
    }

    public function recordFailedLogin(
        ?User $user,
        ?string $loginIdentifier,
        string $appSource,
        string $authChannel,
        ?Request $request = null,
        string $failureReason = 'invalid_credentials',
        array $metadata = [],
    ): AuthAttempt {
        return $this->storeAuthAttempt(
            user: $user,
            loginIdentifier: $loginIdentifier,
            eventType: AuthAttempt::EVENT_FAILED,
            appSource: $appSource,
            authChannel: $authChannel,
            request: $request,
            failureReason: $failureReason,
            metadata: $metadata,
        );
    }

    public function recordBlockedLogin(
        ?User $user,
        ?string $loginIdentifier,
        string $appSource,
        string $authChannel,
        ?Request $request = null,
        string $failureReason = 'blocked',
        array $metadata = [],
    ): AuthAttempt {
        return $this->storeAuthAttempt(
            user: $user,
            loginIdentifier: $loginIdentifier,
            eventType: AuthAttempt::EVENT_BLOCKED,
            appSource: $appSource,
            authChannel: $authChannel,
            request: $request,
            failureReason: $failureReason,
            metadata: $metadata,
        );
    }

    public function recordThrottledLogin(
        ?User $user,
        ?string $loginIdentifier,
        string $appSource,
        string $authChannel,
        ?Request $request = null,
        string $failureReason = 'rate_limited',
        array $metadata = [],
    ): AuthAttempt {
        return $this->storeAuthAttempt(
            user: $user,
            loginIdentifier: $loginIdentifier,
            eventType: AuthAttempt::EVENT_THROTTLED,
            appSource: $appSource,
            authChannel: $authChannel,
            request: $request,
            failureReason: $failureReason,
            metadata: $metadata,
        );
    }

    public function recordLogout(User $user, string $appSource, string $authChannel, ?Request $request = null, array $metadata = []): AccessEvent
    {
        return $this->storeEvent(
            user: $user,
            eventType: AccessEvent::EVENT_LOGOUT,
            appSource: $appSource,
            authChannel: $authChannel,
            request: $request,
            metadata: $metadata,
        );
    }

    public function dashboardData(int $days = 30, int $recentLimit = 20, int $userLimit = 25): array
    {
        $days = max(1, $days);
        $since = now()->subDays($days - 1)->startOfDay();
        $eventsAvailable = $this->eventsTableExists();
        $attemptsAvailable = $this->authAttemptsTableExists();

        $eligibleUsers = User::query()->where('is_active', true);
        $eligibleCount = (clone $eligibleUsers)->count();

        $activeUserIds = $eventsAvailable
            ? AccessEvent::query()
                ->where('event_type', AccessEvent::EVENT_LOGIN)
                ->where('occurred_at', '>=', $since)
                ->distinct()
                ->pluck('user_id')
            : collect();

        $activeCount = (clone $eligibleUsers)
            ->whereIn('id', $activeUserIds)
            ->count();

        $inactiveCount = max(0, $eligibleCount - $activeCount);

        $appSummary = $eventsAvailable
            ? AccessEvent::query()
                ->where('event_type', AccessEvent::EVENT_LOGIN)
                ->where('occurred_at', '>=', $since)
                ->selectRaw("COALESCE(NULLIF(app_source, ''), 'unknown') as app_source")
                ->selectRaw('COUNT(*) as login_count')
                ->selectRaw('COUNT(DISTINCT user_id) as unique_users')
                ->selectRaw('MAX(occurred_at) as last_login_at')
                ->groupBy('app_source')
                ->orderByDesc('login_count')
                ->get()
            : collect();

        $recentEvents = $eventsAvailable
            ? AccessEvent::query()
                ->with('user:id,name,email')
                ->orderByDesc('occurred_at')
                ->limit($recentLimit)
                ->get()
            : collect();

        $failedAttempts = 0;
        $blockedAttempts = 0;
        $throttledAttempts = 0;
        $failedIdentifiers = collect();
        $failedIps = collect();
        $recentSecurityEvents = collect();

        if ($attemptsAvailable) {
            $failedAttempts = AuthAttempt::query()
                ->where('event_type', AuthAttempt::EVENT_FAILED)
                ->where('occurred_at', '>=', $since)
                ->count();

            $blockedAttempts = AuthAttempt::query()
                ->where('event_type', AuthAttempt::EVENT_BLOCKED)
                ->where('occurred_at', '>=', $since)
                ->count();

            $throttledAttempts = AuthAttempt::query()
                ->where('event_type', AuthAttempt::EVENT_THROTTLED)
                ->where('occurred_at', '>=', $since)
                ->count();

            $failedIdentifiers = AuthAttempt::query()
                ->where('event_type', AuthAttempt::EVENT_FAILED)
                ->where('occurred_at', '>=', $since)
                ->whereNotNull('login_identifier')
                ->selectRaw("COALESCE(NULLIF(login_identifier, ''), 'unknown') as identifier")
                ->selectRaw('COUNT(*) as attempt_count')
                ->groupByRaw("COALESCE(NULLIF(login_identifier, ''), 'unknown')")
                ->orderByDesc('attempt_count')
                ->limit(5)
                ->get();

            $failedIps = AuthAttempt::query()
                ->where('event_type', AuthAttempt::EVENT_FAILED)
                ->where('occurred_at', '>=', $since)
                ->whereNotNull('ip_address')
                ->selectRaw("COALESCE(NULLIF(ip_address, ''), 'unknown') as ip_address")
                ->selectRaw('COUNT(*) as attempt_count')
                ->groupByRaw("COALESCE(NULLIF(ip_address, ''), 'unknown')")
                ->orderByDesc('attempt_count')
                ->limit(5)
                ->get();

            $recentSecurityEvents = AuthAttempt::query()
                ->with('user:id,name,email')
                ->where('occurred_at', '>=', $since)
                ->orderByDesc('occurred_at')
                ->limit($recentLimit)
                ->get();
        }

        $distinctFailedIdentifiers = $attemptsAvailable
            ? AuthAttempt::query()
                ->where('event_type', AuthAttempt::EVENT_FAILED)
                ->where('occurred_at', '>=', $since)
                ->whereNotNull('login_identifier')
                ->distinct()
                ->count('login_identifier')
            : 0;

        $distinctFailedIps = $attemptsAvailable
            ? AuthAttempt::query()
                ->where('event_type', AuthAttempt::EVENT_FAILED)
                ->where('occurred_at', '>=', $since)
                ->whereNotNull('ip_address')
                ->distinct()
                ->count('ip_address')
            : 0;

        $securityAlerts = $this->buildSecurityAlerts(
            failedAttempts: $failedAttempts,
            blockedAttempts: $blockedAttempts,
            throttledAttempts: $throttledAttempts,
            failedIdentifiers: $failedIdentifiers,
            failedIps: $failedIps,
        );

        $userColumns = ['id', 'name', 'email', 'is_active'];

        if ($this->userColumnExists('last_login_at')) {
            $userColumns[] = 'last_login_at';
        } else {
            $userColumns[] = DB::raw('NULL as last_login_at');
        }

        if ($this->userColumnExists('last_login_app_source')) {
            $userColumns[] = 'last_login_app_source';
        } else {
            $userColumns[] = DB::raw('NULL as last_login_app_source');
        }

        if ($this->userColumnExists('last_login_channel')) {
            $userColumns[] = 'last_login_channel';
        } else {
            $userColumns[] = DB::raw('NULL as last_login_channel');
        }

        if ($this->userColumnExists('last_active_at')) {
            $userColumns[] = 'last_active_at';
        } else {
            $userColumns[] = DB::raw('NULL as last_active_at');
        }

        $userOrderColumn = $this->userColumnExists('last_login_at') ? 'last_login_at' : 'last_active_at';

        $userSnapshot = User::query()
            ->where('is_active', true)
            ->orderByDesc($userOrderColumn)
            ->orderByDesc('id')
            ->limit($userLimit)
            ->get($userColumns);

        if ($attemptsAvailable && $userSnapshot->isNotEmpty()) {
            $failedAttemptsByUser = AuthAttempt::query()
                ->whereIn('user_id', $userSnapshot->pluck('id')->all())
                ->where('event_type', AuthAttempt::EVENT_FAILED)
                ->where('occurred_at', '>=', $since)
                ->select('user_id')
                ->selectRaw('MAX(occurred_at) as last_failed_login_at')
                ->selectRaw('COUNT(*) as failed_login_count')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            $userSnapshot = $userSnapshot->map(function (User $user) use ($failedAttemptsByUser): User {
                $failedStats = $failedAttemptsByUser->get($user->id);

                $user->setAttribute('failed_login_count', (int) ($failedStats?->failed_login_count ?? 0));
                $user->setAttribute(
                    'last_failed_login_at',
                    isset($failedStats?->last_failed_login_at) && $failedStats->last_failed_login_at
                        ? Carbon::parse($failedStats->last_failed_login_at)
                        : null
                );

                return $user;
            });
        } else {
            $userSnapshot = $userSnapshot->map(function (User $user): User {
                $user->setAttribute('failed_login_count', 0);
                $user->setAttribute('last_failed_login_at', null);

                return $user;
            });
        }

        return [
            'window_days' => $days,
            'since' => $since,
            'analytics_available' => $eventsAvailable,
            'security_available' => $attemptsAvailable,
            'eligible_users' => $eligibleCount,
            'active_users' => $activeCount,
            'inactive_users' => $inactiveCount,
            'apps_seen' => $appSummary->count(),
            'app_summary' => $appSummary,
            'recent_events' => $recentEvents,
            'failed_attempts' => $failedAttempts,
            'blocked_attempts' => $blockedAttempts,
            'throttled_attempts' => $throttledAttempts,
            'distinct_failed_identifiers' => $distinctFailedIdentifiers,
            'distinct_failed_ips' => $distinctFailedIps,
            'security_alerts' => $securityAlerts,
            'recent_security_events' => $recentSecurityEvents,
            'user_snapshot' => $userSnapshot,
        ];
    }

    public function userSummary(User $user, int $days = 30): array
    {
        $since = now()->subDays(max(1, $days) - 1)->startOfDay();

        $recent = $this->eventsTableExists()
            ? AccessEvent::query()
                ->where('user_id', $user->id)
                ->where('event_type', AccessEvent::EVENT_LOGIN)
                ->where('occurred_at', '>=', $since)
                ->orderByDesc('occurred_at')
                ->first()
            : null;

        return [
            'last_login_at' => $user->last_login_at,
            'last_login_app_source' => $user->last_login_app_source,
            'last_login_channel' => $user->last_login_channel,
            'recent_login_at' => $recent?->occurred_at,
        ];
    }

    private function storeEvent(
        User $user,
        string $eventType,
        string $appSource,
        string $authChannel,
        ?Request $request = null,
        array $metadata = [],
    ): AccessEvent {
        if (! $this->eventsTableExists()) {
            return new AccessEvent([
                'user_id' => $user->id,
                'event_type' => $eventType,
                'app_source' => $this->normalizeSource($appSource),
                'auth_channel' => $this->normalizeChannel($authChannel),
                'session_id' => $request?->hasSession() ? $request->session()->getId() : null,
                'access_token_name' => $metadata['access_token_name'] ?? null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'metadata' => $metadata ?: null,
                'occurred_at' => now(),
            ]);
        }

        return AccessEvent::create([
            'user_id' => $user->id,
            'event_type' => $eventType,
            'app_source' => $this->normalizeSource($appSource),
            'auth_channel' => $this->normalizeChannel($authChannel),
            'session_id' => $request?->hasSession() ? $request->session()->getId() : null,
            'access_token_name' => $metadata['access_token_name'] ?? null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }

    private function storeAuthAttempt(
        ?User $user,
        ?string $loginIdentifier,
        string $eventType,
        string $appSource,
        string $authChannel,
        ?Request $request = null,
        string $failureReason = 'unknown',
        array $metadata = [],
    ): AuthAttempt {
        $payload = [
            'user_id' => $user?->id,
            'login_identifier' => $this->normalizeIdentifier($loginIdentifier),
            'event_type' => $eventType,
            'app_source' => $this->normalizeSource($appSource),
            'auth_channel' => $this->normalizeChannel($authChannel),
            'failure_reason' => $this->normalizeIdentifier($failureReason) ?? 'unknown',
            'session_id' => $request?->hasSession() ? $request->session()->getId() : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ];

        if (! $this->authAttemptsTableExists()) {
            return new AuthAttempt($payload);
        }

        $attempt = AuthAttempt::create($payload);
        $this->dispatchSecurityAlerts($attempt);

        return $attempt;
    }

    private function dispatchSecurityAlerts(AuthAttempt $attempt): void
    {
        $windowMinutes = max(1, (int) config('core.security_alerts.window_minutes', 15));
        $windowStart = $this->securityAlertWindowStart($attempt->occurred_at ?? now(), $windowMinutes);
        $windowEnd = $windowStart->copy()->addMinutes($windowMinutes);

        $failedThreshold = max(1, (int) config('core.security_alerts.failed_login_threshold', 10));
        $throttledThreshold = max(1, (int) config('core.security_alerts.throttled_login_threshold', 5));
        $identifierThreshold = max(1, (int) config('core.security_alerts.repeated_identifier_threshold', 5));
        $ipThreshold = max(1, (int) config('core.security_alerts.repeated_ip_threshold', 5));

        $alerts = [];

        $failedQuery = AuthAttempt::query()
            ->where('event_type', AuthAttempt::EVENT_FAILED)
            ->whereBetween('occurred_at', [$windowStart, $windowEnd]);

        $failedCount = (clone $failedQuery)->count();
        if ($failedCount >= $failedThreshold) {
            $alerts[] = [
                'alert_type' => 'failed_volume',
                'alert_key' => sprintf('failed_volume:%s', $windowStart->format('YmdHi')),
                'attempt_count' => $failedCount,
                'identifier' => $this->normalizeIdentifier($attempt->login_identifier),
                'ip_address' => $attempt->ip_address,
                'metadata' => [
                    'window_minutes' => $windowMinutes,
                    'threshold' => $failedThreshold,
                ],
            ];
        }

        $throttledCount = AuthAttempt::query()
            ->where('event_type', AuthAttempt::EVENT_THROTTLED)
            ->whereBetween('occurred_at', [$windowStart, $windowEnd])
            ->count();

        if ($throttledCount >= $throttledThreshold) {
            $alerts[] = [
                'alert_type' => 'throttled_volume',
                'alert_key' => sprintf('throttled_volume:%s', $windowStart->format('YmdHi')),
                'attempt_count' => $throttledCount,
                'identifier' => $this->normalizeIdentifier($attempt->login_identifier),
                'ip_address' => $attempt->ip_address,
                'metadata' => [
                    'window_minutes' => $windowMinutes,
                    'threshold' => $throttledThreshold,
                ],
            ];
        }

        $topIdentifier = AuthAttempt::query()
            ->where('event_type', AuthAttempt::EVENT_FAILED)
            ->whereBetween('occurred_at', [$windowStart, $windowEnd])
            ->whereNotNull('login_identifier')
            ->selectRaw("COALESCE(NULLIF(login_identifier, ''), 'unknown') as identifier")
            ->selectRaw('COUNT(*) as attempt_count')
            ->groupByRaw("COALESCE(NULLIF(login_identifier, ''), 'unknown')")
            ->orderByDesc('attempt_count')
            ->first();

        if ($topIdentifier && (int) $topIdentifier->attempt_count >= $identifierThreshold) {
            $identifier = (string) $topIdentifier->identifier;
            $alerts[] = [
                'alert_type' => 'identifier_spike',
                'alert_key' => sprintf('identifier_spike:%s:%s', $windowStart->format('YmdHi'), sha1($identifier)),
                'attempt_count' => (int) $topIdentifier->attempt_count,
                'identifier' => $identifier,
                'ip_address' => null,
                'metadata' => [
                    'window_minutes' => $windowMinutes,
                    'threshold' => $identifierThreshold,
                ],
            ];
        }

        $topIp = AuthAttempt::query()
            ->where('event_type', AuthAttempt::EVENT_FAILED)
            ->whereBetween('occurred_at', [$windowStart, $windowEnd])
            ->whereNotNull('ip_address')
            ->selectRaw("COALESCE(NULLIF(ip_address, ''), 'unknown') as ip_address")
            ->selectRaw('COUNT(*) as attempt_count')
            ->groupByRaw("COALESCE(NULLIF(ip_address, ''), 'unknown')")
            ->orderByDesc('attempt_count')
            ->first();

        if ($topIp && (int) $topIp->attempt_count >= $ipThreshold) {
            $ipAddress = (string) $topIp->ip_address;
            $alerts[] = [
                'alert_type' => 'ip_spike',
                'alert_key' => sprintf('ip_spike:%s:%s', $windowStart->format('YmdHi'), sha1($ipAddress)),
                'attempt_count' => (int) $topIp->attempt_count,
                'identifier' => null,
                'ip_address' => $ipAddress,
                'metadata' => [
                    'window_minutes' => $windowMinutes,
                    'threshold' => $ipThreshold,
                ],
            ];
        }

        if ($alerts === []) {
            return;
        }

        $recipients = User::query()
            ->role('super_admin')
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($alerts as $alertData) {
            $alert = AuthSecurityAlert::firstOrCreate(
                ['alert_key' => $alertData['alert_key']],
                [
                    'alert_type' => $alertData['alert_type'],
                    'window_started_at' => $windowStart,
                    'window_ended_at' => $windowEnd,
                    'attempt_count' => $alertData['attempt_count'],
                    'identifier' => $alertData['identifier'],
                    'ip_address' => $alertData['ip_address'],
                    'metadata' => $alertData['metadata'],
                ]
            );

            if (! $alert->wasRecentlyCreated) {
                continue;
            }

            Notification::send($recipients, new SecurityAlertNotification($alert));
            $alert->forceFill(['notified_at' => now()])->saveQuietly();
        }
    }

    private function securityAlertWindowStart(Carbon $moment, int $windowMinutes): Carbon
    {
        $minute = intdiv($moment->minute, $windowMinutes) * $windowMinutes;

        return $moment->copy()->minute($minute)->second(0)->microsecond(0);
    }

    private function buildSecurityAlerts(
        int $failedAttempts,
        int $blockedAttempts,
        int $throttledAttempts,
        Collection $failedIdentifiers,
        Collection $failedIps,
    ): Collection {
        $alerts = collect();

        if ($failedAttempts >= 10) {
            $alerts->push([
                'severity' => 'danger',
                'title' => __('core::access_analytics.security.alerts.failed_volume.title'),
                'message' => __('core::access_analytics.security.alerts.failed_volume.message', ['count' => $failedAttempts]),
            ]);
        }

        if ($failedIdentifiers->isNotEmpty() && (int) $failedIdentifiers->first()->attempt_count >= 5) {
            $topIdentifier = $failedIdentifiers->first();

            $alerts->push([
                'severity' => 'danger',
                'title' => __('core::access_analytics.security.alerts.identifier_spike.title'),
                'message' => __('core::access_analytics.security.alerts.identifier_spike.message', [
                    'identifier' => $topIdentifier->identifier,
                    'count' => $topIdentifier->attempt_count,
                ]),
            ]);
        }

        if ($failedIps->isNotEmpty() && (int) $failedIps->first()->attempt_count >= 5) {
            $topIp = $failedIps->first();

            $alerts->push([
                'severity' => 'warning',
                'title' => __('core::access_analytics.security.alerts.ip_spike.title'),
                'message' => __('core::access_analytics.security.alerts.ip_spike.message', [
                    'ip' => $topIp->ip_address,
                    'count' => $topIp->attempt_count,
                ]),
            ]);
        }

        if ($throttledAttempts > 0) {
            $alerts->push([
                'severity' => 'warning',
                'title' => __('core::access_analytics.security.alerts.throttled.title'),
                'message' => __('core::access_analytics.security.alerts.throttled.message', ['count' => $throttledAttempts]),
            ]);
        }

        if ($blockedAttempts > 0) {
            $alerts->push([
                'severity' => 'info',
                'title' => __('core::access_analytics.security.alerts.blocked.title'),
                'message' => __('core::access_analytics.security.alerts.blocked.message', ['count' => $blockedAttempts]),
            ]);
        }

        return $alerts;
    }

    private function normalizeSource(?string $source): string
    {
        $source = trim((string) $source);

        return $source !== '' ? $source : 'unknown';
    }

    private function normalizeChannel(?string $channel): string
    {
        $channel = trim((string) $channel);

        return $channel !== '' ? $channel : 'unknown';
    }

    private function normalizeIdentifier(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function eventsTableExists(): bool
    {
        return Schema::hasTable((new AccessEvent())->getTable());
    }

    protected function authAttemptsTableExists(): bool
    {
        return Schema::hasTable((new AuthAttempt())->getTable());
    }

    protected function userColumnExists(string $column): bool
    {
        return Schema::hasColumn((new User())->getTable(), $column);
    }
}
