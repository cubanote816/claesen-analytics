{{--
    All page-level CSS for the shared two-panel auth layout (design source of
    truth: odd/tasks/backoffice-auth-redesign.reference.html): tokens, fonts, the
    brand panel (brand-panel.blade.php) and the form panel restyle of Filament's
    simple layout.

    Registered on PanelsRenderHook::HEAD_END for every panel's auth routes
    (filament.*.auth.*): the rules restyle .fi-simple-layout/.fi-simple-main-ctn
    as the two-column flex shell AND style the brand column, so this file and
    brand-panel.blade.php must be gated by the same route check.

    Plain <style>, never Tailwind utilities: utilities from resources/css/app.css
    are not available in panel views, and the bertels panel has no Vite theme at
    all (CLAUDE.md rule 11). This is the design for BOTH panels — no per-company
    accent; Filament's panel colours (admin cyan / bertels orange) are overridden
    on purpose inside the auth screens only.

    The screens are light by design: the HEAD_END script in AdminPanelProvider
    strips the `dark` class on auth routes, and nothing here has a dark variant.

    T2/T3 (Forgot / Reset password) get the whole shell for free; they only need
    the shared page view (page.blade.php) and the classes below.
--}}
{{-- Sora (titles) + Inter (everything else), only on the auth pages. Same provider
     Filament already uses for the panel font (Bunny, GDPR-friendly mirror of Google Fonts). --}}
{{ app(\Filament\FontProviders\BunnyFontProvider::class)->getHtml('Sora', 'https://fonts.bunny.net/css?family=sora:500,600,700|inter:400,500,600&display=swap') }}
<style id="cafca-login-theme">
    /* ---------- Tokens ---------- */
    :root {
        --auth-brand-bg: #07111E;
        --auth-brand-text: #C3CEDF;
        --auth-brand-muted: #9FB0C7;
        --auth-page-bg: #F6F8FB;
        --auth-ink: #0B1727;
        --auth-ink-2: #4A586B;
        --auth-ink-3: #3F4D61;
        --auth-border: #C5CEDB;
        --auth-divider: #D3DAE5;
        --auth-primary: #1B66C0;
        --auth-primary-hover: #155AAD;
        --auth-primary-tint: #E3EEFB;
        --auth-focus: #7DB3F2;
        --auth-error: #8F1236;
        --auth-error-bg: #FDEDF1;
        --auth-error-border: #F3B7C6;
        --auth-font-display: 'Sora', 'Inter', sans-serif;
        --auth-font-body: 'Inter', system-ui, sans-serif;

        /* Legacy names still used by the password screens below (T2/T3 will drop them). */
        --cafca-login-accent: var(--auth-primary);
        --cafca-login-accent-strong: var(--auth-primary-hover);
    }

    /* ---------- Page shell ---------- */
    html.fi,
    .fi-body {
        --font-family: 'Inter';
        font-family: var(--auth-font-body) !important;
    }

    .fi-body {
        background: var(--auth-page-bg) !important;
        color: var(--auth-ink);
    }

    .fi-simple-layout {
        flex-direction: row !important;
        flex-wrap: wrap;
        align-items: stretch !important;
        min-height: 100vh;
    }

    .fi-simple-layout :focus-visible {
        outline: 3px solid var(--auth-focus);
        outline-offset: 2px;
    }

    /* ---------- Left brand panel ---------- */
    .cafca-login-brand {
        position: relative;
        overflow: hidden;
        flex: 1 1 560px;
        min-width: 0;
        box-sizing: border-box;
        padding: clamp(32px, 5vw, 72px);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 56px;
        color: #fff;
        background-color: var(--auth-brand-bg);
        background-image:
            radial-gradient(60% 50% at 100% 0%, rgba(34, 118, 210, .30), rgba(7, 17, 30, 0) 70%),
            linear-gradient(rgba(255, 255, 255, .035) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, .035) 1px, transparent 1px);
        background-size: auto, 48px 48px, 48px 48px;
    }

    .cafca-login-brand__rings {
        position: absolute;
        right: -240px;
        bottom: -260px;
        pointer-events: none;
    }

    .cafca-login-brand__logos {
        position: relative;
        display: flex;
        align-items: center;
        gap: 28px;
        flex-wrap: wrap;
    }

    .cafca-login-brand__logos img {
        height: 52px;
        width: auto;
        display: block;
    }

    .cafca-login-brand__sep {
        width: 1px;
        height: 36px;
        background: rgba(255, 255, 255, .2);
    }

    .cafca-login-brand__body {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 36px;
        max-width: 560px;
    }

    .cafca-login-brand__title {
        margin: 0;
        font-family: var(--auth-font-display);
        font-weight: 600;
        font-size: clamp(34px, 4vw, 52px);
        line-height: 1.08;
        letter-spacing: -.02em;
        text-wrap: balance;
    }

    .cafca-login-brand__lead {
        margin: 16px 0 0;
        font-size: 17px;
        line-height: 1.6;
        color: var(--auth-brand-text);
        text-wrap: pretty;
    }

    .cafca-login-brand__companies {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .cafca-login-brand__company {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        padding: 18px 20px;
        border-radius: 14px;
        background: rgba(255, 255, 255, .045);
        border: 1px solid rgba(255, 255, 255, .10);
    }

    .cafca-login-brand__dot {
        flex: none;
        width: 10px;
        height: 10px;
        margin-top: 7px;
        border-radius: 50%;
    }

    .cafca-login-brand__company-name {
        display: block;
        margin-bottom: 4px;
        font-family: var(--auth-font-display);
        font-weight: 600;
        font-size: 16px;
    }

    .cafca-login-brand__company-desc {
        display: block;
        font-size: 15px;
        line-height: 1.5;
        color: var(--auth-brand-text);
    }

    .cafca-login-brand__foot {
        position: relative;
        margin: 0;
        font-size: 13px;
        color: var(--auth-brand-muted);
    }

    /* ---------- Right form panel ---------- */
    .fi-simple-main-ctn {
        flex: 1 1 480px !important;
        min-width: 0;
        width: auto !important;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 48px clamp(20px, 5vw, 72px);
        background: var(--auth-page-bg);
    }

    .fi-simple-main {
        width: 100% !important;
        max-width: 420px !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }

    .fi-simple-page-content {
        display: flex !important;
        flex-direction: column;
        gap: 28px;
        row-gap: 28px !important;
    }

    /* Blocks of the page: heading, notices, form, alternatives — 28px apart. */
    .fi-simple-page .fi-sc.fi-sc-has-gap {
        gap: 28px !important;
    }

    /* Fields inside the form: 20px apart. */
    .fi-simple-page .fi-sc-form .fi-sc.fi-sc-has-gap {
        gap: 20px !important;
    }

    .fi-simple-page .fi-sc-form > .fi-sc.fi-sc-has-gap {
        gap: 28px !important;
    }

    /* Heading (page.blade.php; Filament's own header for pages not migrated yet) */
    .fi-simple-header .fi-logo {
        display: none !important;
    }

    .fi-simple-header {
        align-items: flex-start !important;
    }

    .cafca-login-heading__title,
    .fi-simple-header-heading {
        margin: 0 !important;
        font-family: var(--auth-font-display);
        font-weight: 600;
        font-size: 34px !important;
        line-height: 1.2 !important;
        letter-spacing: -.02em;
        text-align: left !important;
        color: var(--auth-ink) !important;
    }

    .cafca-login-heading__lede,
    .fi-simple-header-subheading {
        margin: 8px 0 0 !important;
        font-size: 16px !important;
        line-height: 1.5;
        text-align: left !important;
        color: var(--auth-ink-2) !important;
        text-wrap: pretty;
    }

    /* Notice above the fields (role="alert") */
    .cafca-login-alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 12px;
        background: var(--auth-error-bg);
        border: 1px solid var(--auth-error-border);
        color: var(--auth-error);
        font-size: 14px;
        line-height: 1.4;
    }

    .cafca-login-alert svg {
        flex: none;
    }

    /* The credential error is shown once, in the notice above — not under the email too. */
    .cafca-login-field--email [data-validation-error] {
        display: none;
    }

    /* Labels */
    .fi-simple-page .fi-fo-field-label-content {
        font-size: 14px !important;
        font-weight: 600 !important;
        color: var(--auth-ink) !important;
    }

    .fi-simple-page .fi-fo-field {
        row-gap: 8px;
    }

    .cafca-login-req {
        color: var(--auth-primary);
    }

    /* Inputs: 52px, radius 12, 16px (no iOS zoom) */
    .fi-simple-page .fi-input-wrp {
        min-height: 52px;
        border: 1px solid var(--auth-border);
        border-radius: 12px !important;
        background: #fff !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }

    .fi-simple-page .fi-input-wrp:focus-within {
        border-color: var(--auth-primary);
        box-shadow: none !important;
    }

    .fi-simple-page .fi-input-wrp:has(.fi-input:focus-visible) {
        outline: 3px solid var(--auth-focus);
        outline-offset: 2px;
    }

    .fi-simple-page .fi-input {
        height: 50px;
        padding: 0 16px !important;
        font-size: 16px !important;
        color: var(--auth-ink) !important;
    }

    .fi-simple-page .fi-input:focus-visible {
        outline: none;
    }

    .fi-simple-page .fi-input::placeholder {
        color: #6B7686 !important;
    }

    /* Password SHOW / HIDE text toggle (44px) instead of the eye icon.
       The accessible name is Filament's own aria-label ("Show password" / "Hide password"). */
    .fi-simple-page .fi-input-wrp-suffix {
        border: 0 !important;
        padding: 0 6px 0 0 !important;
    }

    .fi-simple-page .fi-input-wrp-actions button {
        width: auto !important;
        height: 44px !important;
        min-width: 64px;
        padding: 0 12px !important;
        border-radius: 8px !important;
        background: none !important;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--auth-ink-3);
        cursor: pointer;
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

    /* Remember-me row: checkbox on the left, "Forgot password?" on the right, wrapping on narrow screens. */
    .fi-simple-page .fi-fo-field-label-ctn:has(.fi-checkbox-input) {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        column-gap: 16px;
    }

    .fi-simple-page .fi-checkbox-input {
        appearance: auto !important;
        width: 20px !important;
        height: 20px !important;
        margin: 0;
        accent-color: var(--auth-primary);
        cursor: pointer;
    }

    .fi-simple-page label:has(.fi-checkbox-input) {
        display: flex;
        align-items: center;
        gap: 12px !important;
        min-height: 44px;
        cursor: pointer;
    }

    .fi-simple-page label:has(.fi-checkbox-input) .fi-fo-field-label-content {
        font-size: 15px !important;
        font-weight: 400 !important;
        color: #2C3A4E !important;
    }

    .cafca-login-link {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        font-size: 15px;
        font-weight: 600;
        color: var(--auth-primary);
        text-decoration: none;
    }

    .cafca-login-link:hover {
        text-decoration: underline;
    }

    /* Primary button: 54px, radius 12, 16px, full width, blue on both panels */
    .fi-simple-page button[type='submit'] {
        width: 100%;
        min-height: 54px;
        border-radius: 12px !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        background-color: var(--auth-primary) !important;
        color: #fff !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }

    .fi-simple-page button[type='submit']:hover {
        background-color: var(--auth-primary-hover) !important;
    }

    /* Divider + Microsoft button (microsoft-login-button.blade.php).
       The grid gap between blocks is 28px; the design wants 20px inside the actions group. */
    .cafca-login-alt {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-top: -8px;
    }

    .cafca-login-divider {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .cafca-login-divider::before,
    .cafca-login-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: var(--auth-divider);
    }

    .cafca-login-divider__label {
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #5A6779;
    }

    .cafca-login-microsoft {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        box-sizing: border-box;
        width: 100%;
        height: 54px;
        border-radius: 12px;
        border: 1px solid var(--auth-border);
        background: #fff;
        color: var(--auth-ink);
        font-size: 16px;
        font-weight: 600;
        text-decoration: none;
    }

    .cafca-login-microsoft:hover {
        background: #F1F5FA;
    }

    .cafca-login-microsoft svg {
        flex: none;
    }

    /* "Do you belong to the other company?" hint (other-panel-hint.blade.php) */
    .cafca-login-other-panel {
        margin: 0;
        text-align: center;
        font-size: 14px;
        line-height: 1.5;
        color: var(--auth-ink-2);
    }

    .cafca-login-other-panel a {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
        font-weight: 600;
        color: var(--auth-primary);
        text-decoration: underline;
    }

    /* ---------- Forgot password (password-reset-request.blade.php) ---------- */
    .cafca-login-link--back {
        align-self: flex-start;
        gap: 8px;
        color: var(--auth-ink-3);
    }

    .cafca-login-state {
        display: flex;
        flex-direction: column;
        gap: 28px;
    }

    .cafca-login-status-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        border-radius: 16px;
        background: var(--auth-primary-tint);
        color: var(--auth-primary);
    }

    .cafca-login-heading__lede .cafca-login-email {
        color: var(--auth-ink);
        font-weight: 600;
        word-break: break-all;
    }

    .cafca-login-btn-stack {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .cafca-login-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        box-sizing: border-box;
        width: 100%;
        height: 54px;
        border-radius: 12px;
        border: 0;
        font: inherit;
        font-size: 16px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
    }

    .cafca-login-btn--primary {
        background: var(--auth-primary);
        color: #fff;
    }

    .cafca-login-btn--primary:hover {
        background: var(--auth-primary-hover);
    }

    .cafca-login-btn--secondary {
        background: #fff;
        color: var(--auth-ink);
        border: 1px solid var(--auth-border);
    }

    .cafca-login-btn--secondary:hover {
        background: #F1F5FA;
    }

    /* ---------- Reset password screens (T3 still uses these; legacy) ---------- */
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

    .cafca-login-notice__check {
        width: 14px;
        height: 9px;
        border-left: 2.5px solid #fff;
        border-bottom: 2.5px solid #fff;
        transform: rotate(-45deg) translate(1px, -1px);
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
