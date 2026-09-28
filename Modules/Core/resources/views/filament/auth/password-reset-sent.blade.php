{{--
    CLA-603: the mockup's "Check your email" screen (sent state of the
    forgot-password page). The heading and the body line ("We sent a reset link
    to :email…") come from RequestPasswordReset::getHeading()/getSubheading().

    Deliberate deviation from the mockup: it has a primary "Open reset link"
    button here, and this screen does not. Opening the link from this page
    would only be possible for addresses that exist, which is precisely the
    account enumeration the API's generic response avoids — see
    RequestPasswordReset::request().
--}}
<div class="cafca-login-panel">
    <div class="cafca-login-notice" aria-hidden="true">
        <span class="cafca-login-notice__envelope"></span>
    </div>

    <p class="cafca-login-panel__aside">
        {{ __('core::auth.reset_sent_resend') }}
        <button type="button" class="cafca-login-linkbutton" wire:click="resend">
            {{ __('core::auth.reset_sent_resend_action') }}
        </button>
    </p>

    <a href="{{ filament()->getLoginUrl() }}" class="cafca-login-back">
        <span aria-hidden="true">&larr;</span>
        {{ __('core::auth.reset_back_to_login') }}
    </a>
</div>
