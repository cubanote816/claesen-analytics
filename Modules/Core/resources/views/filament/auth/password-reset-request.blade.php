{{--
    Forgot password page (shared two-panel auth layout, see page.blade.php).
    Form state: back link, heading, e-mail field, submit.
    Sent state (role="status"): envelope icon, "Check your inbox", the lede with the escaped
    e-mail, and two buttons. The sent state is identical whether or not the account exists
    (RequestPasswordReset::request()).
--}}
<x-filament-panels::page.simple heading="" subheading="">
    <a class="cafca-login-link cafca-login-link--back" href="{{ filament()->getLoginUrl() }}">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
        {{ __('core::auth.reset_back_to_login') }}
    </a>

    @if ($sent)
        <div class="cafca-login-state" role="status">
            <div class="cafca-login-status-icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7.5l8.5 6 8.5-6"/></svg>
            </div>

            @include('core::filament.auth.heading')

            <div class="cafca-login-btn-stack">
                <a class="cafca-login-btn cafca-login-btn--primary" href="{{ filament()->getLoginUrl() }}">{{ __('core::auth.reset_back_to_login') }}</a>
                <button class="cafca-login-btn cafca-login-btn--secondary" type="button" wire:click="useDifferentEmail">{{ __('core::auth.reset_use_different_email') }}</button>
            </div>
        </div>
    @else
        @include('core::filament.auth.heading')

        {{ $this->content }}
    @endif
</x-filament-panels::page.simple>
