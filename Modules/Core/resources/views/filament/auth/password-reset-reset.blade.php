{{--
    Reset password page (shared two-panel auth layout, see page.blade.php).
    Form state: heading + the two password fields. `reveal` is the single Show/Hide state
    shared by both fields. Success state (role="status"): check icon, "Password updated",
    and a link to the panel's real login.
--}}
<x-filament-panels::page.simple heading="" subheading="" x-data="{ reveal: false }">
    @if ($done)
        <div class="cafca-login-state" role="status">
            <div class="cafca-login-status-icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            </div>

            @include('core::filament.auth.heading')

            <a class="cafca-login-btn cafca-login-btn--primary" href="{{ filament()->getLoginUrl() }}">{{ __('core::auth.reset_done_action') }}</a>
        </div>
    @else
        @include('core::filament.auth.heading')

        {{ $this->content }}
    @endif
</x-filament-panels::page.simple>
