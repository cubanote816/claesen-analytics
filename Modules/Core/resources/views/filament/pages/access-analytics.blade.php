@php
    $data = $this->getAnalyticsData();
    $periodLabel = $this->getPeriodLabel();
    $unknownSource = __('core::access_analytics.unknown_source');
    $unknownChannel = __('core::access_analytics.unknown_channel');
    $unknownUser = __('core::access_analytics.unknown_user');
    $analyticsAvailable = $data['analytics_available'] ?? true;
    $securityAvailable = $data['security_available'] ?? true;

    $surfaceClass = 'rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900';
    $softSurfaceClass = 'rounded-2xl border border-slate-200 bg-slate-50 shadow-sm dark:border-slate-700 dark:bg-slate-950/60';
    $tableShellClass = 'overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900';
    $labelClass = 'text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400';
    $headingClass = 'text-lg font-bold tracking-tight text-slate-950 dark:text-white';
    $bodyClass = 'text-sm leading-6 text-slate-600 dark:text-slate-300';

    $eventLabels = [
        'failed' => __('core::access_analytics.security.events.failed'),
        'blocked' => __('core::access_analytics.security.events.blocked'),
        'throttled' => __('core::access_analytics.security.events.throttled'),
        'oauth_failed' => __('core::access_analytics.security.events.oauth_failed'),
    ];

    $reasonLabels = [
        'unknown_user' => __('core::access_analytics.security.reasons.unknown_user'),
        'invalid_password' => __('core::access_analytics.security.reasons.invalid_password'),
        'invalid_credentials' => __('core::access_analytics.security.reasons.invalid_credentials'),
        'inactive_account' => __('core::access_analytics.security.reasons.inactive_account'),
        'password_setup_required' => __('core::access_analytics.security.reasons.password_setup_required'),
        'rate_limited' => __('core::access_analytics.security.reasons.rate_limited'),
        'account_not_authorized' => __('core::access_analytics.security.reasons.account_not_authorized'),
        'oauth_exception' => __('core::access_analytics.security.reasons.oauth_exception'),
        'blocked' => __('core::access_analytics.security.reasons.blocked'),
    ];

    $alertClasses = [
        'danger' => 'border-red-200 bg-red-50 text-red-900 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-100',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100',
        'info' => 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-8 text-slate-950 dark:text-slate-50">
        @unless ($analyticsAvailable)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                {{ __('core::access_analytics.fallback_notice') }}
            </div>
        @endunless

        @unless ($securityAvailable)
            <div class="rounded-2xl border border-sky-300 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
                {{ __('core::access_analytics.security.fallback_notice') }}
            </div>
        @endunless

        <section class="rounded-3xl border border-slate-200 bg-gradient-to-br from-white via-slate-50 to-cyan-50 p-6 shadow-sm dark:border-slate-700 dark:from-slate-950 dark:via-slate-900 dark:to-slate-800">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-700 dark:text-cyan-300">
                        {{ __('navigation.groups.user_management') }}
                    </p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-white">
                        {{ __('core::access_analytics.hero_title') }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-700 dark:text-slate-300">
                        {{ __('core::access_analytics.hero_description') }}
                    </p>
                </div>

                <div class="w-full max-w-xs">
                    <label for="access-analytics-period" class="mb-2 block text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">
                        {{ __('core::access_analytics.period_label') }}
                    </label>
                    <select
                        id="access-analytics-period"
                        wire:model.live="period"
                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    >
                        @foreach ($this->getPeriodOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="{{ $headingClass }}">{{ __('core::access_analytics.sections.adoption_overview') }}</h2>
                <p class="{{ $bodyClass }}">{{ __('core::access_analytics.sections.adoption_overview_hint', ['days' => $periodLabel]) }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="{{ $surfaceClass }} p-5">
                    <p class="{{ $labelClass }}">{{ __('core::access_analytics.stats.eligible_users') }}</p>
                    <p class="mt-3 text-3xl font-black text-slate-950 dark:text-white">{{ $data['eligible_users'] }}</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('core::access_analytics.stats.eligible_users_hint') }}</p>
                </article>

                <article class="{{ $surfaceClass }} p-5">
                    <p class="{{ $labelClass }}">{{ __('core::access_analytics.stats.active_users') }}</p>
                    <p class="mt-3 text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ $data['active_users'] }}</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('core::access_analytics.stats.active_users_hint', ['days' => $periodLabel]) }}</p>
                </article>

                <article class="{{ $surfaceClass }} p-5">
                    <p class="{{ $labelClass }}">{{ __('core::access_analytics.stats.inactive_users') }}</p>
                    <p class="mt-3 text-3xl font-black text-rose-600 dark:text-rose-400">{{ $data['inactive_users'] }}</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('core::access_analytics.stats.inactive_users_hint') }}</p>
                </article>

                <article class="{{ $surfaceClass }} p-5">
                    <p class="{{ $labelClass }}">{{ __('core::access_analytics.stats.apps_seen') }}</p>
                    <p class="mt-3 text-3xl font-black text-cyan-600 dark:text-cyan-400">{{ $data['apps_seen'] }}</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('core::access_analytics.stats.apps_seen_hint') }}</p>
                </article>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-4">
            <article class="{{ $surfaceClass }} p-5">
                <p class="{{ $labelClass }}">{{ __('core::access_analytics.security.metrics.failed_attempts') }}</p>
                <p class="mt-3 text-3xl font-black text-slate-950 dark:text-white">{{ $data['failed_attempts'] }}</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('core::access_analytics.security.metrics.failed_attempts_hint') }}</p>
            </article>

            <article class="{{ $surfaceClass }} p-5">
                <p class="{{ $labelClass }}">{{ __('core::access_analytics.security.metrics.throttled_attempts') }}</p>
                <p class="mt-3 text-3xl font-black text-amber-600 dark:text-amber-400">{{ $data['throttled_attempts'] }}</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('core::access_analytics.security.metrics.throttled_attempts_hint') }}</p>
            </article>

            <article class="{{ $surfaceClass }} p-5">
                <p class="{{ $labelClass }}">{{ __('core::access_analytics.security.metrics.blocked_attempts') }}</p>
                <p class="mt-3 text-3xl font-black text-sky-600 dark:text-sky-400">{{ $data['blocked_attempts'] }}</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('core::access_analytics.security.metrics.blocked_attempts_hint') }}</p>
            </article>

            <article class="{{ $surfaceClass }} p-5">
                <p class="{{ $labelClass }}">{{ __('core::access_analytics.security.metrics.flagged_sources') }}</p>
                <p class="mt-3 text-3xl font-black text-violet-600 dark:text-violet-400">{{ $data['distinct_failed_ips'] }}</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('core::access_analytics.security.metrics.flagged_sources_hint') }}</p>
            </article>
        </section>

        <section class="{{ $surfaceClass }} p-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="{{ $headingClass }}">{{ __('core::access_analytics.sections.app_summary') }}</h2>
                    <p class="{{ $bodyClass }}">{{ __('core::access_analytics.sections.app_summary_hint') }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.app_source') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.login_count') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.unique_users') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.last_login_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($data['app_summary'] as $row)
                            <tr class="align-top">
                                <td class="py-3 pr-4 font-semibold text-slate-950 dark:text-slate-100">{{ $row->app_source !== 'unknown' ? $row->app_source : $unknownSource }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ $row->login_count }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ $row->unique_users }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">
                                    {{ $row->last_login_at ? \Illuminate\Support\Carbon::parse($row->last_login_at)->format('d M Y · H:i') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-sm text-slate-500 dark:text-slate-400">{{ __('core::access_analytics.empty_states.no_app_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="{{ $headingClass }}">{{ __('core::access_analytics.sections.security_summary') }}</h2>
                <p class="{{ $bodyClass }}">{{ __('core::access_analytics.sections.security_summary_hint') }}</p>
            </div>

            @if ($data['security_alerts']->isNotEmpty())
                <div class="space-y-3">
                    @foreach ($data['security_alerts'] as $alert)
                        <div class="rounded-2xl border p-4 text-sm {{ $alertClasses[$alert['severity']] ?? $alertClasses['info'] }}">
                            <p class="font-bold">{{ $alert['title'] }}</p>
                            <p class="mt-1 leading-6">{{ $alert['message'] }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-300">
                    {{ __('core::access_analytics.security.no_alerts') }}
                </div>
            @endif
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="{{ $surfaceClass }} p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="{{ $headingClass }}">{{ __('core::access_analytics.sections.recent_activity') }}</h2>
                        <p class="{{ $bodyClass }}">{{ __('core::access_analytics.sections.recent_activity_hint') }}</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse ($data['recent_events'] as $event)
                        <div class="{{ $softSurfaceClass }} p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-950 dark:text-slate-100">
                                        {{ $event->user?->name ?? $unknownUser }}
                                    </p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                        {{ $event->event_type }} · {{ $event->app_source ?: $unknownSource }} · {{ $event->auth_channel ?: $unknownChannel }}
                                    </p>
                                </div>
                                <p class="shrink-0 text-sm text-slate-500 dark:text-slate-400">
                                    {{ optional($event->occurred_at)->format('d M Y · H:i') }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 p-5 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            {{ __('core::access_analytics.empty_states.no_recent_activity') }}
                        </p>
                    @endforelse
                </div>
            </section>

            <section class="{{ $surfaceClass }} p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="{{ $headingClass }}">{{ __('core::access_analytics.sections.recent_security_activity') }}</h2>
                        <p class="{{ $bodyClass }}">{{ __('core::access_analytics.sections.recent_security_activity_hint') }}</p>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="py-3 pr-4">{{ __('core::access_analytics.security.table.when') }}</th>
                                <th class="py-3 pr-4">{{ __('core::access_analytics.security.table.user') }}</th>
                                <th class="py-3 pr-4">{{ __('core::access_analytics.security.table.event') }}</th>
                                <th class="py-3 pr-4">{{ __('core::access_analytics.security.table.reason') }}</th>
                                <th class="py-3 pr-4">{{ __('core::access_analytics.security.table.app') }}</th>
                                <th class="py-3 pr-4">{{ __('core::access_analytics.security.table.ip') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($data['recent_security_events'] as $event)
                                @php
                                    $eventLabel = $eventLabels[$event->event_type] ?? $event->event_type;
                                    $reasonLabel = $reasonLabels[$event->failure_reason] ?? $event->failure_reason ?? '—';
                                    $eventUserLabel = $event->user?->name
                                        ?? $event->login_identifier
                                        ?? $unknownUser;
                                @endphp
                                <tr class="align-top">
                                    <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ optional($event->occurred_at)->format('d M Y · H:i') }}</td>
                                    <td class="py-3 pr-4 font-semibold text-slate-950 dark:text-slate-100">{{ $eventUserLabel }}</td>
                                    <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ $eventLabel }}</td>
                                    <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ $reasonLabel }}</td>
                                    <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ $event->app_source ?: $unknownSource }}</td>
                                    <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ $event->ip_address ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-sm text-slate-500 dark:text-slate-400">{{ __('core::access_analytics.security.empty_states.no_security_activity') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="{{ $surfaceClass }} p-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="{{ $headingClass }}">{{ __('core::access_analytics.sections.user_snapshot') }}</h2>
                    <p class="{{ $bodyClass }}">{{ __('core::access_analytics.sections.user_snapshot_hint', ['days' => $periodLabel]) }}</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.user') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.last_login_at') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.last_login_app') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.last_login_channel') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.last_failed_login_at') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.failed_login_count') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.last_activity_at') }}</th>
                            <th class="py-3 pr-4">{{ __('core::access_analytics.tables.activity_state') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($data['user_snapshot'] as $user)
                            @php
                                $hasRecentLogin = $user->last_login_at && $user->last_login_at->greaterThanOrEqualTo($data['since']);
                                $hasFailedAttempts = (int) ($user->failed_login_count ?? 0) > 0;
                            @endphp
                            <tr class="align-top">
                                <td class="py-3 pr-4">
                                    <div class="font-semibold text-slate-950 dark:text-slate-100">{{ $user->name }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                                </td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ optional($user->last_login_at)->format('d M Y · H:i') ?? '—' }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ $user->last_login_app_source ?: $unknownSource }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ $user->last_login_channel ?: $unknownChannel }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ optional($user->last_failed_login_at)->format('d M Y · H:i') ?? '—' }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ $user->failed_login_count ?? 0 }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-300">{{ optional($user->last_active_at)->format('d M Y · H:i') ?? '—' }}</td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $hasFailedAttempts ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300' : ($hasRecentLogin ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300') }}">
                                        {{ $hasFailedAttempts ? __('core::access_analytics.states.watchlist') : ($hasRecentLogin ? __('core::access_analytics.states.active') : __('core::access_analytics.states.inactive')) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-sm text-slate-500 dark:text-slate-400">{{ __('core::access_analytics.empty_states.no_users') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
