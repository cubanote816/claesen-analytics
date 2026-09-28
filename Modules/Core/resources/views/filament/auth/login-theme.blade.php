{{--
    CLA-603: all page-level CSS for the login/auth screens (mockup:
    diseno/Rediseño de login profesional backoffice/Login.dc.html), extracted
    from brand-panel.blade.php so it no longer depends on that partial being
    rendered — and, crucially, no longer depends on the admin panel's Vite
    theme compiling Tailwind utilities.

    Registered on PanelsRenderHook::HEAD_END for every panel's auth routes
    (filament.*.auth.*): the rules restyle .fi-simple-layout/.fi-simple-main-ctn
    as the mockup's two-column flex shell AND style the brand column, so this
    file and brand-panel.blade.php must be gated by the same route check.

    The per-company brand tokens (--cafca-login-accent*) are injected by
    brand-panel.blade.php from the panel's own site/organization.
--}}

@php
    // CLA-603: brand accent of the company whose panel is being served. Hue comes
    // from that panel's own ->colors()['primary'] (config/organizations.php
    // login_brand); lightness/chroma are the approved mockup's. No site mapped →
    // 255, i.e. the mockup's own blue, never another company's colour.
    $loginBrand = \Modules\Core\Models\Site::forPanel()?->loginBrand() ?? [];
    $accentHue = $loginBrand['accent_hue'] ?? 255;
@endphp
<style id="cafca-login-theme">
    /* ---------- Brand accent (per company) ---------- */
    :root {
        --cafca-login-accent: oklch(0.55 0.16 {{ $accentHue }});
        --cafca-login-accent-strong: oklch(0.48 0.16 {{ $accentHue }});
        --cafca-login-dot: oklch(0.7 0.14 {{ $accentHue }});
        --cafca-login-glow: oklch(0.5 0.16 {{ $accentHue }} / 0.35);
        --cafca-login-ring: oklch(0.55 0.16 {{ $accentHue }} / 0.15);
    }

    /* ---------- Page shell (mockup: outer flex container) ---------- */
    html.fi,
    .fi-body {
        --font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
    }

    .fi-body {
        background: oklch(0.985 0.003 255) !important;
    }

    .fi-simple-layout {
        flex-direction: row !important;
        flex-wrap: wrap;
        align-items: stretch !important;
        min-height: 100vh;
    }

    /* ---------- Left brand column ---------- */
    .cafca-login-brand {
        flex: 1 1 320px;
        min-width: 0;
        min-height: 420px;
        box-sizing: border-box;
        position: relative;
        overflow: hidden;
        padding: 56px 48px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        color: oklch(0.96 0.004 255);
        background: linear-gradient(155deg, oklch(0.16 0.03 255), oklch(0.22 0.035 258));
    }

    .cafca-login-brand__grid {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background-image:
            repeating-linear-gradient(0deg, oklch(1 0 0 / 0.04) 0px, transparent 1px, transparent 48px),
            repeating-linear-gradient(90deg, oklch(1 0 0 / 0.04) 0px, transparent 1px, transparent 48px);
    }

    .cafca-login-brand__glow {
        position: absolute;
        top: -140px;
        right: -140px;
        width: 380px;
        height: 380px;
        border-radius: 50%;
        pointer-events: none;
        background: radial-gradient(circle, var(--cafca-login-glow), transparent 70%);
    }

    .cafca-login-brand__logos,
    .cafca-login-brand__copy,
    .cafca-login-brand__footer {
        position: relative;
        z-index: 1;
    }

    .cafca-login-brand__logos {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    /* CLA-603: one logo per panel — the company of the site that panel manages.
       Base height is the mockup's Claesen lockup (65px) and width:auto keeps each
       logo's aspect ratio instead of the mockup's hard-coded widths, which
       belonged to its two-logos row; the per-company height comes from
       config/organizations.php login_brand.logo_height. */
    .cafca-login-brand__logo {
        width: auto;
        max-width: 100%;
        object-fit: contain;
    }

    .cafca-login-brand__copy {
        max-width: 400px;
    }

    .cafca-login-brand__headline {
        font-size: 34px;
        line-height: 1.25;
        font-weight: 700;
        margin: 0 0 16px 0;
        letter-spacing: -0.01em;
    }

    .cafca-login-brand__intro {
        font-size: 15px;
        line-height: 1.6;
        color: oklch(0.85 0.01 255);
        margin: 0 0 28px 0;
    }

    .cafca-login-brand__bullets {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .cafca-login-brand__bullets li {
        display: flex;
        /* CLA-603: flex-start (not the mockup's center) because our real copy
           wraps to two lines; the dot then aligns with the first line. */
        align-items: flex-start;
        gap: 12px;
        font-size: 14px;
        line-height: 1.6;
        color: oklch(0.9 0.006 255);
    }

    .cafca-login-brand__dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--cafca-login-dot);
        flex: none;
        /* centres the 6px dot on the first line: (line-height 1.6 × 14px)/2 − 3px */
        margin-top: 8px;
    }

    .cafca-login-brand__footer {
        font-size: 12px;
        color: oklch(0.62 0.01 255);
    }

    /* ---------- Right form column ---------- */
    .fi-simple-main-ctn {
        flex: 1 1 340px !important;
        min-width: 0;
        min-height: 420px;
        width: auto !important;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 56px 32px;
    }

    .fi-simple-main {
        width: 100% !important;
        max-width: 400px !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    .fi-simple-page-content {
        row-gap: 0 !important;
    }

    .fi-simple-header {
        align-items: flex-start !important;
    }

    .fi-simple-header .fi-logo {
        display: none !important;
    }

    .fi-simple-header-heading {
        font-size: 26px !important;
        font-weight: 700 !important;
        line-height: normal !important;
        color: oklch(0.2 0.01 255) !important;
        margin: 0 0 6px 0 !important;
        letter-spacing: -0.01em !important;
        text-align: left !important;
    }

    .fi-simple-header-subheading {
        font-size: 14px !important;
        color: oklch(0.5 0.008 255) !important;
        margin: 0 0 32px 0 !important;
        text-align: left !important;
    }

    .fi-simple-page .fi-sc-form .fi-sc-has-gap {
        gap: 20px !important;
    }

    /* Labels */
    .fi-simple-page .fi-fo-field-label-content {
        font-size: 13px !important;
        font-weight: 600 !important;
        color: oklch(0.3 0.01 255) !important;
    }

    .fi-simple-page .fi-fo-field-label-required-mark {
        top: 0 !important;
        vertical-align: baseline;
        font-size: inherit;
        font-weight: 600;
        margin-left: 4px;
        color: var(--cafca-login-accent) !important;
    }

    /* Inputs */
    .fi-simple-page .fi-input-wrp {
        min-height: 44px;
        border: 1px solid oklch(0.88 0.005 255);
        border-radius: 8px !important;
        background: oklch(1 0 0) !important;
        box-shadow: none !important;
    }

    .fi-simple-page .fi-input-wrp:focus-within {
        border-color: var(--cafca-login-accent);
        box-shadow: 0 0 0 3px var(--cafca-login-ring) !important;
    }

    .fi-simple-page .fi-input {
        height: 42px;
        padding: 0 14px !important;
        font-size: 15px !important;
        color: oklch(0.2 0.01 255) !important;
    }

    .fi-simple-page .fi-input::placeholder {
        color: oklch(0.62 0.006 255) !important;
    }

    /* Password SHOW / HIDE text toggle instead of the eye icon */
    .fi-simple-page .fi-input-wrp-suffix {
        border: 0 !important;
        padding-inline: 0 12px !important;
    }

    .fi-simple-page .fi-input-wrp-actions button {
        width: auto !important;
        height: auto !important;
        padding: 4px !important;
        background: none !important;
        font-size: 12px;
        font-weight: 700;
        color: oklch(0.5 0.01 255);
    }

    .fi-simple-page .fi-input-wrp-actions button svg {
        display: none !important;
    }

    .fi-simple-page .fi-input-wrp-actions button[x-show="! isPasswordRevealed"]::after {
        content: "{{ __('core::auth.password_show') }}";
    }

    .fi-simple-page .fi-input-wrp-actions button[x-show="isPasswordRevealed"]::after {
        content: "{{ __('core::auth.password_hide') }}";
    }

    /* Remember checkbox */
    .fi-simple-page .fi-checkbox-input {
        width: 16px !important;
        height: 16px !important;
    }

    .fi-simple-page label:has(.fi-checkbox-input) {
        gap: 9px !important;
    }

    .fi-simple-page label:has(.fi-checkbox-input) .fi-fo-field-label-content {
        font-size: 14px !important;
        font-weight: 400 !important;
        color: oklch(0.35 0.01 255) !important;
    }

    /* Primary button */
    .fi-simple-page button[type='submit'] {
        width: 100%;
        height: 46px;
        margin-top: 4px;
        border-radius: 8px !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        background-color: var(--cafca-login-accent) !important;
        color: #fff !important;
        box-shadow: none !important;
    }

    .fi-simple-page button[type='submit']:hover {
        background-color: var(--cafca-login-accent-strong) !important;
    }

    /* Divider + Microsoft button (microsoft-login-button.blade.php) */
    /* Mockup: 28px above and below the divider. The schema grid already puts
       24px before this block, so only 4px more is added on top. */
    .cafca-login-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 4px 0 28px;
    }

    .cafca-login-divider__line {
        flex: 1;
        height: 1px;
        background: oklch(0.9 0.005 255);
    }

    .cafca-login-divider__label {
        font-size: 12px;
        font-weight: 600;
        color: oklch(0.6 0.008 255);
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .cafca-login-microsoft {
        width: 100%;
        height: 46px;
        box-sizing: border-box;
        border-radius: 8px;
        border: 1px solid oklch(0.88 0.005 255);
        background: oklch(1 0 0);
        color: oklch(0.25 0.01 255);
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-decoration: none;
    }

    .cafca-login-microsoft:hover {
        background: oklch(0.97 0.003 255);
    }

    .cafca-login-microsoft__icon {
        display: inline-grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        width: 14px;
        height: 14px;
        gap: 1px;
    }

    /* CLA-603: the mockup's hard-coded hue-255 primary ramp used to live here.
       Removed on purpose: Filament already generates --primary-50..950 from the
       panel's own ->colors()['primary'] and scopes it to the panel, so every
       themed control (checkbox checked, links) is brand-coloured per company
       without any override — and without a second place to keep in sync. */

    /* ---------- Password screens (mockup: forgot / sent / reset / done) ---------- */
    /* Notice tile: 44px, accent at 12% on the sent screen and solid on the
       success one (mockup's own two treatments). */
    .cafca-login-notice {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: color-mix(in oklab, var(--cafca-login-accent) 12%, transparent);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 24px;
    }

    .cafca-login-notice--done {
        border-radius: 50%;
        background: var(--cafca-login-accent);
    }

    .cafca-login-notice__envelope {
        width: 16px;
        height: 12px;
        border: 2px solid var(--cafca-login-accent);
        border-radius: 2px;
    }

    .cafca-login-notice__check {
        width: 14px;
        height: 9px;
        border-left: 2.5px solid #fff;
        border-bottom: 2.5px solid #fff;
        transform: rotate(-45deg) translate(1px, -1px);
    }

    .cafca-login-panel__aside {
        font-size: 13px;
        color: oklch(0.5 0.008 255);
        margin: 0;
        text-align: center;
    }

    .cafca-login-linkbutton {
        background: none;
        border: 0;
        padding: 0;
        font: inherit;
        font-weight: 600;
        color: var(--cafca-login-accent);
        cursor: pointer;
    }

    .cafca-login-linkbutton:hover {
        text-decoration: underline;
    }

    .cafca-login-back {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 24px;
        font-size: 13px;
        font-weight: 600;
        color: oklch(0.5 0.008 255);
    }

    .cafca-login-primary-button {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 46px;
        border-radius: 8px;
        background: var(--cafca-login-accent);
        color: #fff !important;
        font-size: 15px;
        font-weight: 600;
    }

    .cafca-login-primary-button:hover {
        background: var(--cafca-login-accent-strong);
        color: #fff !important;
    }

    /* One line per requirement, each with a 6px dot that turns green once met. */
    .cafca-login-requirements {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 14px 16px;
        border-radius: 8px;
        background: oklch(0.97 0.003 255);
    }

    .cafca-login-requirement {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        color: oklch(0.6 0.008 255);
    }

    .cafca-login-requirement__dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        flex: none;
    }

    .cafca-login-requirement.is-met {
        color: oklch(0.55 0.14 150);
    }
</style>
