{{--
    CLA-603: left brand column of the login mockup
    (diseno/Rediseño de login profesional backoffice/Login.dc.html).

    Injected via PanelsRenderHook::SIMPLE_LAYOUT_START on every panel's auth
    routes. It shows ONLY the company of the panel it is served on — logo,
    accent colour and copy all come from that panel's own site
    (Site::forPanel()->loginBrand()), never from a hard-coded brand, so the
    bertels panel can never render Claesen's identity or vice versa.

    Every panel with a mapped site gets it; a panel whose site does not exist
    yet renders nothing at all and leaves the form centred on its own
    (.fi-simple-main-ctn is flex:1 1 340px → full width as the only child).

    All the actual CSS lives in login-theme.blade.php, registered on HEAD_END
    with the exact same route gate — plain <style>, never Tailwind utilities,
    because a panel without a Vite theme (bertels) would not compile them.
--}}
@php
    $site = \Modules\Core\Models\Site::forPanel();
    $brand = $site?->loginBrand();
@endphp

@if ($brand)
    <div class="cafca-login-brand">
        <div class="cafca-login-brand__grid" aria-hidden="true"></div>
        <div class="cafca-login-brand__glow" aria-hidden="true"></div>

        <div class="cafca-login-brand__logos">
            <img src="{{ asset($brand['logo']) }}"
                 alt="{{ $brand['logo_alt'] }}"
                 class="cafca-login-brand__logo"
                 style="height: {{ (int) $brand['logo_height'] }}px">
        </div>

        <div class="cafca-login-brand__copy">
            <h1 class="cafca-login-brand__headline">{{ __('core::auth.brand_headline') }}</h1>
            <p class="cafca-login-brand__intro">{{ __('core::auth.brand_intro') }}</p>
            <ul class="cafca-login-brand__bullets">
                <li><span class="cafca-login-brand__dot"></span>{{ __('core::auth.brand_bullet_claesen') }}</li>
                <li><span class="cafca-login-brand__dot"></span>{{ __('core::auth.brand_bullet_bertels') }}</li>
                <li><span class="cafca-login-brand__dot"></span>{{ __('core::auth.brand_bullet_shared') }}</li>
            </ul>
        </div>

        <div class="cafca-login-brand__footer">{{ __('core::auth.brand_footer', ['year' => now()->year]) }}</div>
    </div>
@endif
