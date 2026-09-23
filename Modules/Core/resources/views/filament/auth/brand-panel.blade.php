{{--
    CLA-580: split-screen brand panel for the Filament login page only.
    Injected via PanelsRenderHook::SIMPLE_LAYOUT_START, scoped to the login
    route in AdminPanelProvider — this markup never renders on other
    fi-simple-layout screens (password reset / MFA challenge share the same
    Login page+route, so they inherit it too, which is intentional).

    Uses a scoped <style> block instead of Tailwind utility classes for the
    gradient/dot-grid background: this view is compiled under
    resources/css/filament/admin/theme.css, which does NOT import
    resources/css/app.css — so app.css-only utilities like .bg-mesh-signature
    are not available here. .glass-signature IS safe to reuse (theme.css
    defines its own copy), but this panel doesn't need it.
--}}
<div class="cafca-login-brand" aria-hidden="false">
    <svg class="cafca-login-brand__rays" viewBox="0 0 400 400" aria-hidden="true">
        <path d="M 0 340 A 340 340 0 0 1 340 0" fill="none" stroke="#00aeef" stroke-opacity="0.16" stroke-width="1.5"></path>
        <path d="M 0 260 A 260 260 0 0 1 260 0" fill="none" stroke="#f97316" stroke-opacity="0.22" stroke-width="1.5"></path>
        <path d="M 0 180 A 180 180 0 0 1 180 0" fill="none" stroke="#00aeef" stroke-opacity="0.22" stroke-width="1.5"></path>
        <path d="M 0 100 A 100 100 0 0 1 100 0" fill="none" stroke="#f97316" stroke-opacity="0.3" stroke-width="1.5"></path>
        <circle cx="0" cy="400" r="7" fill="#f97316"></circle>
    </svg>

    <div class="cafca-login-brand__content">
        <img src="{{ asset('img/brand-logo-dark.png') }}" alt="Claesen" class="cafca-login-brand__logo">

        <div class="cafca-login-brand__copy">
            <div class="cafca-login-brand__wordmark">Claesen</div>
            <div class="cafca-login-brand__subwordmark">Outdoor Lighting Platform</div>
            <p class="cafca-login-brand__tagline">{{ __('core::auth.brand_tagline') }}</p>
        </div>
    </div>
</div>

<style id="claesen-login-brand-panel">
    .cafca-login-brand {
        position: fixed;
        inset: 0 0 auto 0;
        z-index: 40;
        overflow: hidden;
        height: 13.5rem;
        background-color: #0b0b0f;
        background-image:
            radial-gradient(circle at 90% 12%, rgba(249, 115, 22, 0.2), transparent 45%),
            radial-gradient(circle, rgba(255, 255, 255, 0.05) 1px, transparent 1.5px);
        background-size: 100% 100%, 20px 20px;
    }

    .cafca-login-brand__rays {
        position: absolute;
        left: -3rem;
        bottom: -3.5rem;
        width: 13rem;
        height: 13rem;
        z-index: 0;
    }

    .cafca-login-brand__content {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        box-sizing: border-box;
        padding: 1.5rem;
    }

    .cafca-login-brand__logo {
        height: 1.65rem;
        width: auto;
        object-fit: contain;
    }

    .cafca-login-brand__wordmark {
        font-size: 1.25rem;
        font-weight: 700;
        color: #f8fafc;
        letter-spacing: -0.01em;
    }

    .cafca-login-brand__subwordmark {
        margin-top: 0.25rem;
        font-size: 0.6875rem;
        font-weight: 500;
        color: rgba(248, 250, 252, 0.55);
        text-transform: uppercase;
        letter-spacing: 0.12em;
    }

    .cafca-login-brand__tagline {
        display: none;
        margin: 1.25rem 0 0;
        max-width: 18.5rem;
        font-size: 0.875rem;
        line-height: 1.6;
        color: rgba(248, 250, 252, 0.5);
    }

    /* Pushes the real Filament form below the top band on mobile. */
    .fi-simple-main-ctn {
        padding-top: 13.5rem;
    }

    @media (min-width: 1024px) {
        .cafca-login-brand {
            width: 30rem;
            height: 100%;
        }

        .cafca-login-brand__rays {
            left: -4.5rem;
            bottom: -4.5rem;
            width: 22.5rem;
            height: 22.5rem;
        }

        .cafca-login-brand__tagline {
            display: block;
        }

        .fi-simple-main-ctn {
            padding-top: 0;
            margin-left: 30rem;
        }
    }
</style>
