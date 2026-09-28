{{-- CLA-601: divider + Microsoft button, styled 1:1 with the login mockup (classes defined in brand-panel.blade.php, only rendered on the login page).
     Wrapped in one element on purpose: the parent schema grid adds a 24px gap between items, which would otherwise land between the divider and the button. --}}
<div class="cafca-login-alt">
    <div class="cafca-login-divider">
        <span class="cafca-login-divider__line"></span>
        <span class="cafca-login-divider__label">{{ __('core::auth.divider_or') }}</span>
        <span class="cafca-login-divider__line"></span>
    </div>

    <a href="{{ route('auth.microsoft.redirect', ['source' => 'filament']) }}" class="cafca-login-microsoft">
        <span class="cafca-login-microsoft__icon" aria-hidden="true">
            <span style="background:#F25022"></span><span style="background:#7FBA00"></span><span style="background:#00A4EF"></span><span style="background:#FFB900"></span>
        </span>
        {{ __('core::auth.microsoft_login') }}
    </a>
</div>
