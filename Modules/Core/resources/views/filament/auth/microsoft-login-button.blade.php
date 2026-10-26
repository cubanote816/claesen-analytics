{{-- Divider + Microsoft button (classes defined in login-theme.blade.php, only rendered on the login page).
     Wrapped in one element on purpose: the parent schema grid adds a gap between items, which would otherwise land between the divider and the button.
     The button is a real link to the existing SSO redirect route (MicrosoftAuthController). --}}
<div class="cafca-login-alt">
    <div class="cafca-login-divider" aria-hidden="true">
        <span class="cafca-login-divider__label">{{ __('core::auth.divider_or') }}</span>
    </div>

    <a href="{{ route('auth.microsoft.redirect', ['source' => 'filament']) }}" class="cafca-login-microsoft">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
        {{ __('core::auth.microsoft_login') }}
    </a>
</div>
