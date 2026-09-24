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
            /* Pre-existing bug from CLA-580, only now visible: this container
               keeps Filament's own width:100% while also getting a 30rem
               margin-left, so its right edge sits 30rem past the viewport —
               a horizontal (and, once the card grows past the fold,
               vertical) scrollbar neither side of the layout ever needed.
               box-sizing here is Tailwind's global border-box reset, so
               width has to shrink by the same 30rem the margin adds. */
            padding-top: 0;
            margin-left: 30rem;
            width: calc(100% - 30rem);
        }
    }

    /* CLA-601 (exact-match pass): the mockup's right pane is flush with the
       page background — no boxed/shadowed card at all — and its accent is a
       specific blue (oklch(0.55 0.16 255)), not Claesen Cyan. Scoped to this
       page only; the rest of the app keeps its real Cyan primary and its
       real card styling elsewhere. */
    .fi-simple-main {
        background: transparent !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    /* Filament generates its whole primary-{50..950} OKLCH ramp from one seed
       color (Cyan, hue 234.363) and every themed control — button fill,
       hover, focus ring, checkbox accent — reads those custom properties
       rather than a single hardcoded color. Re-declaring the same 11 steps
       with the mockup's hue (255) keeps Filament's own lightness/chroma
       progression (so contrast/hover/focus states stay coherent) while
       recoloring everything that already depends on --primary-*, without
       hunting down each individual utility class by hand. --primary-600 is
       the one confirmed by measuring the actual rendered button background
       (oklch(0.598 0.169 234.363) before this rule) — its recolored value
       (oklch(0.598 0.169 255)) reads as the same blue as the mockup's own
       oklch(0.55 0.16 255), close enough that the difference isn't visible. */
    /* The --primary-* ramp override above recolors the focus ring correctly
       (wrapper.css reads --primary-600 for that), but Filament's light-mode
       button convention is a pastel bg (--primary-400) with dark text —
       the opposite of the mockup's solid, saturated button with white text.
       That's a real design-language mismatch, not just a wrong hue, so the
       ramp swap alone can't fix it: the button itself needs a direct
       override to the mockup's literal color. */
    .fi-simple-page button[type='submit'] {
        background-color: oklch(0.55 0.16 255) !important;
        color: #fff !important;
    }

    .fi-simple-page button[type='submit']:hover {
        background-color: oklch(0.48 0.16 255) !important;
    }

    /* Same story as the button above: measured the actual ring color after
       focusing the email field (rgb(0, 155, 214), still Cyan) — the
       --primary-* ramp override isn't what this ring reads from in
       practice, so it gets the same direct-override treatment. */
    .fi-simple-page .fi-input-wrp:focus-within {
        box-shadow: 0 0 0 2px oklch(0.55 0.16 255), 0 1px 2px rgba(0, 0, 0, 0.05) !important;
    }

    .fi-simple-page input[type='checkbox'] {
        accent-color: oklch(0.55 0.16 255);
    }

    .fi-simple-page {
        --primary-50: oklch(0.97717647058824 0.01395454545455 255);
        --primary-100: oklch(0.95035294117647 0.03272727272727 255);
        --primary-200: oklch(0.90547058823529 0.06318181818182 255);
        --primary-300: oklch(0.84047058823529 0.10604545454546 255);
        --primary-400: oklch(0.75352941176471 0.15027272727273 255);
        --primary-500: oklch(0.68270588235294 0.17009090909091 255);
        --primary-600: oklch(0.59782352941176 0.16913636363636 255);
        --primary-700: oklch(0.51494117647059 0.14940909090909 255);
        --primary-800: oklch(0.44611764705882 0.12331818181818 255);
        --primary-900: oklch(0.39458823529412 0.09963636363636 255);
        --primary-950: oklch(0.27788235294118 0.07136363636364 255);
    }
</style>
