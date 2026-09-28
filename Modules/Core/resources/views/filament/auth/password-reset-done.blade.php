{{--
    CLA-603: the mockup's "Password updated" screen (success state of the
    reset page). Heading and body come from
    Modules\Core\Filament\Pages\Auth\ResetPassword::getHeading()/getSubheading().
--}}
<div class="cafca-login-panel">
    <div class="cafca-login-notice cafca-login-notice--done" aria-hidden="true">
        <span class="cafca-login-notice__check"></span>
    </div>

    <a href="{{ filament()->getLoginUrl() }}" class="cafca-login-primary-button">
        {{ __('core::auth.reset_done_action') }}
    </a>
</div>
