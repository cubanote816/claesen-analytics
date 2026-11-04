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
    {{--
        CLA-647: the rings' own clip wrapper, not the panel's. The panel used to set
        overflow:hidden to clip these (they bleed outside it via negative offsets) — but on a
        flex item, any overflow other than visible collapses its automatic min-height to 0,
        letting the panel shrink below its real content instead of growing the page and
        scrolling. Verified by removing it: the page correctly grows/scrolls when content (or a
        short viewport) needs more room. Clipping only this wrapper keeps the decorative rings
        contained without reintroducing that collapse.
    --}}
    <div class="cafca-login-brand__clip" aria-hidden="true">
        <svg class="cafca-login-brand__rings" aria-hidden="true" width="640" height="640" viewBox="0 0 640 640" fill="none"><circle cx="320" cy="320" r="120" stroke="rgba(255,255,255,.07)"/><circle cx="320" cy="320" r="200" stroke="rgba(255,255,255,.06)"/><circle cx="320" cy="320" r="280" stroke="rgba(255,255,255,.05)"/><circle cx="320" cy="320" r="360" stroke="rgba(255,255,255,.04)"/></svg>
    </div>

    <div class="cafca-login-brand__logos">
        <img src="{{ asset('img/claesen-logo-login.png') }}" alt="{{ __('core::auth.brand_logo_claesen_alt') }}">
        <div class="cafca-login-brand__sep" aria-hidden="true"></div>
        <img src="{{ asset('img/bertels-logo-dark.png') }}" alt="{{ __('core::auth.brand_logo_bertels_alt') }}">
    </div>

    {{-- CLA-647: the two company cards and the lead paragraph are gone. One <h1>, two
         weights: "Staff portal" stays the big line, who it's for is the smaller line
         right under it — same heading (read together by assistive tech), lighter block. --}}
    <div class="cafca-login-brand__body">
        <h1 class="cafca-login-brand__title">
            {{ __('core::auth.brand_headline') }}
            <span class="cafca-login-brand__title-detail">{{ __('core::auth.brand_headline_detail') }}</span>
        </h1>
    </div>

    <p class="cafca-login-brand__foot">{{ __('core::auth.brand_footer', ['year' => now()->year]) }}</p>
</aside>
