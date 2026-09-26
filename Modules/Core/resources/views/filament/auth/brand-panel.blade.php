{{--
    CLA-601: login page rebuilt 1:1 from the approved mockup
    (diseno/Rediseño de login profesional backoffice/Login.dc.html). Every
    size, color and spacing below is copied from that file's inline styles,
    not approximated — only the copy text differs (real business copy, per
    the user) and the form itself is Filament's real Livewire form, restyled.

    Injected via PanelsRenderHook::SIMPLE_LAYOUT_START, scoped to the login
    route in AdminPanelProvider, so none of these rules exist on any other
    page. Layout mirrors the mockup's own flexbox exactly: .fi-simple-layout
    becomes the mockup's `display:flex; flex-wrap:wrap` container, this panel
    is its `flex:1 1 320px` left column and .fi-simple-main-ctn its
    `flex:1 1 340px` right column — so it stacks on narrow screens the same
    way the mockup does, with no fixed positioning or margin tricks.

    Plain <style> instead of Tailwind utilities: this view is compiled under
    resources/css/filament/admin/theme.css, whose @source does not guarantee
    arbitrary classes used only here get generated.
--}}
<div class="cafca-login-brand">
    <div class="cafca-login-brand__grid" aria-hidden="true"></div>
    <div class="cafca-login-brand__glow" aria-hidden="true"></div>

    <div class="cafca-login-brand__logos">
        <img src="{{ asset('img/claesen-logo-login.png') }}" alt="Claesen" class="cafca-login-brand__logo-claesen">
        <span class="cafca-login-brand__logo-divider" aria-hidden="true"></span>
        <img src="{{ asset('img/bertels-logo-dark.png') }}" alt="Electro Bertels" class="cafca-login-brand__logo-bertels">
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

<style id="claesen-login-brand-panel">
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
        background: radial-gradient(circle, oklch(0.5 0.16 255 / 0.35), transparent 70%);
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

    .cafca-login-brand__logo-claesen {
        height: 65px;
        width: 131px;
        object-fit: contain;
    }

    .cafca-login-brand__logo-bertels {
        height: 60px;
        width: 111px;
        object-fit: contain;
    }

    .cafca-login-brand__logo-divider {
        width: 1px;
        height: 24px;
        background: oklch(1 0 0 / 0.25);
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
        align-items: center;
        gap: 12px;
        font-size: 14px;
        color: oklch(0.9 0.006 255);
    }

    .cafca-login-brand__dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: oklch(0.7 0.14 255);
        flex: none;
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
        color: oklch(0.55 0.16 255) !important;
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
        border-color: oklch(0.55 0.16 255);
        box-shadow: 0 0 0 3px oklch(0.55 0.16 255 / 0.15) !important;
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
        background-color: oklch(0.55 0.16 255) !important;
        color: #fff !important;
        box-shadow: none !important;
    }

    .fi-simple-page button[type='submit']:hover {
        background-color: oklch(0.48 0.16 255) !important;
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

    /* Filament's primary ramp, recolored to the mockup's hue (255) for any
       remaining themed control (checkbox checked state, links) that reads it. */
    .fi-simple-page {
        --primary-50: oklch(0.97717647058824 0.01395454545455 255);
        --primary-100: oklch(0.95035294117647 0.03272727272727 255);
        --primary-200: oklch(0.90547058823529 0.06318181818182 255);
        --primary-300: oklch(0.84047058823529 0.10604545454546 255);
        --primary-400: oklch(0.75352941176471 0.15027272727273 255);
        --primary-500: oklch(0.68270588235294 0.17009090909091 255);
        --primary-600: oklch(0.55 0.16 255);
        --primary-700: oklch(0.48 0.16 255);
        --primary-800: oklch(0.44611764705882 0.12331818181818 255);
        --primary-900: oklch(0.39458823529412 0.09963636363636 255);
        --primary-950: oklch(0.27788235294118 0.07136363636364 255);
    }
</style>
