{{--
    Left brand panel of the shared two-panel auth layout (design source of truth:
    odd/tasks/backoffice-auth-redesign.reference.html).

    Injected via PanelsRenderHook::SIMPLE_LAYOUT_START on every panel's auth
    routes. It is the SAME dual-brand screen on the admin (Claesen) and bertels
    panels — both companies, both logos — and renders on every filament.*.auth.*
    screen (sign in, forgot / reset password, MFA), so those pages need no brand
    markup of their own.

    All the CSS lives in login-theme.blade.php, registered on HEAD_END with the
    exact same route gate — plain <style>, never Tailwind utilities, because a
    panel without a Vite theme (bertels) would not compile them.
--}}
<aside class="cafca-login-brand">
    <svg class="cafca-login-brand__rings" aria-hidden="true" width="640" height="640" viewBox="0 0 640 640" fill="none"><circle cx="320" cy="320" r="120" stroke="rgba(255,255,255,.07)"/><circle cx="320" cy="320" r="200" stroke="rgba(255,255,255,.06)"/><circle cx="320" cy="320" r="280" stroke="rgba(255,255,255,.05)"/><circle cx="320" cy="320" r="360" stroke="rgba(255,255,255,.04)"/></svg>

    <div class="cafca-login-brand__logos">
        <img src="{{ asset('img/claesen-logo-login.png') }}" alt="{{ __('core::auth.brand_logo_claesen_alt') }}">
        <div class="cafca-login-brand__sep" aria-hidden="true"></div>
        <img src="{{ asset('img/bertels-logo-dark.png') }}" alt="{{ __('core::auth.brand_logo_bertels_alt') }}">
    </div>

    <div class="cafca-login-brand__body">
        <div>
            <h1 class="cafca-login-brand__title">{{ __('core::auth.brand_headline') }}</h1>
            <p class="cafca-login-brand__lead">{{ __('core::auth.brand_intro') }}</p>
        </div>

        <ul class="cafca-login-brand__companies">
            <li class="cafca-login-brand__company">
                <span class="cafca-login-brand__dot" style="background:#2FD3E6" aria-hidden="true"></span>
                <div>
                    <span class="cafca-login-brand__company-name">{{ __('core::auth.brand_company_claesen') }}</span>
                    <span class="cafca-login-brand__company-desc">{{ __('core::auth.brand_company_claesen_desc') }}</span>
                </div>
            </li>
            <li class="cafca-login-brand__company">
                <span class="cafca-login-brand__dot" style="background:#FF7A1A" aria-hidden="true"></span>
                <div>
                    <span class="cafca-login-brand__company-name">{{ __('core::auth.brand_company_bertels') }}</span>
                    <span class="cafca-login-brand__company-desc">{{ __('core::auth.brand_company_bertels_desc') }}</span>
                </div>
            </li>
        </ul>
    </div>

    <p class="cafca-login-brand__foot">{{ __('core::auth.brand_footer', ['year' => now()->year]) }}</p>
</aside>
