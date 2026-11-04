{{-- Microsoft button + transition into the form (classes defined in login-theme.blade.php, only
     rendered on the login page). CLA-647: Microsoft is the team's usual method, so the button
     comes first and the divider now reads as a transition into the password form below it, not
     a bare "OR" with nothing above it to divide.
     Wrapped in one element on purpose: the parent schema grid adds a gap between items, which would otherwise land between the button and the divider.
     The button is a real link to the existing SSO redirect route (MicrosoftAuthController). --}}
<div class="cafca-login-alt">
    <a href="{{ route('auth.microsoft.redirect', ['source' => 'filament']) }}" class="cafca-login-microsoft">
        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 21 21"><rect x="1" y="1" width="9" height="9" fill="#F25022"/><rect x="11" y="1" width="9" height="9" fill="#7FBA00"/><rect x="1" y="11" width="9" height="9" fill="#00A4EF"/><rect x="11" y="11" width="9" height="9" fill="#FFB900"/></svg>
        {{ __('core::auth.microsoft_login') }}
    </a>

    <div class="cafca-login-divider" aria-hidden="true">
        <span class="cafca-login-divider__label">{{ __('core::auth.divider_or') }}</span>
    </div>
</div>
