{{--
    CLA-580: split-screen brand panel for the Filament login page only.
    Injected via PanelsRenderHook::SIMPLE_LAYOUT_START, scoped to the login
    route in AdminPanelProvider — this markup never renders on other
    fi-simple-layout screens (password reset / MFA challenge share the same
    Login page+route, so they inherit it too, which is intentional).

    CLA-601: dual-brand redesign from the user-supplied mockup. Keeps CLA-580's
    proven background treatment (dark gradient + dot grid + decorative SVG
    rays, already DESIGN.md-token colors) — only the content changes: both
    companies' logos side by side, and a headline/intro/bullet copy block
    replacing the single Claesen wordmark, since this backoffice now serves
    both Claesen and Electro Bertels (mockup's own generic client-portal copy
    was replaced with each company's actual trade, per the user).

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
        <div class="cafca-login-brand__logos">
            <img src="{{ asset('img/brand-logo-dark.png') }}" alt="Claesen" class="cafca-login-brand__logo">
            <span class="cafca-login-brand__logo-divider" aria-hidden="true"></span>
            <img src="{{ asset('img/bertels-logo-dark.png') }}" alt="Electro Bertels" class="cafca-login-brand__logo cafca-login-brand__logo--bertels">
        </div>

        <div class="cafca-login-brand__copy">
            <h1 class="cafca-login-brand__headline">{{ __('core::auth.brand_headline') }}</h1>
            <p class="cafca-login-brand__intro">{{ __('core::auth.brand_intro') }}</p>

            <ul class="cafca-login-brand__bullets">
                <li><span class="cafca-login-brand__dot cafca-login-brand__dot--cyan"></span>{{ __('core::auth.brand_bullet_claesen') }}</li>
                <li><span class="cafca-login-brand__dot cafca-login-brand__dot--orange"></span>{{ __('core::auth.brand_bullet_bertels') }}</li>
                <li><span class="cafca-login-brand__dot cafca-login-brand__dot--cyan"></span>{{ __('core::auth.brand_bullet_shared') }}</li>
            </ul>
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

    .cafca-login-brand__logos {
        display: flex;
        align-items: center;
        gap: 0.875rem;
    }

    .cafca-login-brand__logo {
        height: 1.5rem;
        width: auto;
        object-fit: contain;
    }

    .cafca-login-brand__logo--bertels {
        /* Slightly shorter aspect ratio than the Claesen mark (1774x887 vs
           633x276) — a shade taller keeps both marks reading as the same
           visual weight instead of Bertels looking smaller. */
        height: 1.375rem;
    }

    .cafca-login-brand__logo-divider {
        width: 1px;
        height: 1.5rem;
        background: rgba(248, 250, 252, 0.2);
        flex: none;
    }

    .cafca-login-brand__headline {
        margin: 0 0 0.375rem;
        font-size: 1.0625rem;
        font-weight: 700;
        color: #f8fafc;
        letter-spacing: -0.01em;
        line-height: 1.3;
    }

    .cafca-login-brand__intro {
        margin: 0;
        max-width: 20rem;
        font-size: 0.8125rem;
        line-height: 1.55;
        color: rgba(248, 250, 252, 0.6);
    }

    .cafca-login-brand__bullets {
        display: none;
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

        .cafca-login-brand__logo {
            height: 2.25rem;
        }

        .cafca-login-brand__logo--bertels {
            height: 2.0625rem;
        }

        .cafca-login-brand__logo-divider {
            height: 2.25rem;
        }

        .cafca-login-brand__headline {
            font-size: 1.75rem;
            margin-bottom: 0.75rem;
        }

        .cafca-login-brand__intro {
            font-size: 0.875rem;
            margin-bottom: 1.75rem;
        }

        .cafca-login-brand__bullets {
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .cafca-login-brand__bullets li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.8125rem;
            line-height: 1.4;
            color: rgba(248, 250, 252, 0.85);
        }

        .cafca-login-brand__dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            flex: none;
        }

        .cafca-login-brand__dot--cyan {
            background: #00aeef;
        }

        .cafca-login-brand__dot--orange {
            background: #f97316;
        }

        .fi-simple-main-ctn {
            padding-top: 0;
            margin-left: 30rem;
        }
    }
</style>
