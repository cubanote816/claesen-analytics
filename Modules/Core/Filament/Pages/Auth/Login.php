<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Modules\Core\Filament\Auth\RedirectToAccessiblePanel;
use Filament\Auth\MultiFactor\Contracts\HasBeforeChallengeHook;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Modules\Core\Models\User;

/**
 * CLA-363: client must fail the backoffice login itself, not just lose
 * access to resources after authenticating (which is what
 * User::canAccessPanel()/hasPanelAccess() already do). This lives here — instead
 * of in canAccessPanel() — because Filament's Authenticate middleware also calls
 * canAccessPanel() on every panel request, and 403ing an already-authenticated
 * session there blocks the logout route too (it shares the same auth-middleware
 * group). A denylist inside attemptWhen()'s closure only ever runs at the login
 * attempt, leaving canAccessPanel()/EnsurePanelAccess free to keep handling
 * already-existing sessions (redirect to the no-access page, still allow logout).
 *
 * This otherwise duplicates Filament\Auth\Pages\Login::authenticate() verbatim —
 * there's no smaller extension point to hook an extra rejection rule into
 * attemptWhen()'s closure, so a Filament upgrade that changes this method's
 * internals needs this file re-diffed against the new version.
 */
class Login extends BaseLogin
{
    /** CLA-601: the mockup promises "Remember me for 30 days" — make that literally true. */
    private const REMEMBER_MINUTES = 60 * 24 * 30;

    /**
     * The shared two-panel auth layout (brand panel + form panel) renders its own
     * <h2> heading, so the page uses the shared view instead of Filament's
     * simple-page header (whose <h1> would clash with the brand panel's).
     */
    protected string $view = 'core::filament.auth.page';

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getHeading()
            : __('core::auth.heading');
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getSubheading()
            : __('core::auth.subheading');
    }

    /** Label whose required asterisk is hidden from assistive technology. */
    private function labelWithRequiredMark(string $label): HtmlString
    {
        return new HtmlString(e($label).' <span class="cafca-login-req" aria-hidden="true">*</span>');
    }

    protected function getEmailFormComponent(): \Filament\Schemas\Components\Component
    {
        $label = __('filament-panels::auth/pages/login.form.email.label');

        return parent::getEmailFormComponent()
            ->label($this->labelWithRequiredMark($label))
            ->validationAttribute($label)
            ->markAsRequired(false)
            ->autocomplete('username')
            ->placeholder(__('core::auth.email_placeholder'))
            // The credential failure is shown once, as the role="alert" notice above the
            // fields (login-alert.blade.php); the stylesheet hides this field's own copy.
            ->extraFieldWrapperAttributes(['class' => 'cafca-login-field--email']);
    }

    protected function getPasswordFormComponent(): \Filament\Schemas\Components\Component
    {
        $label = __('filament-panels::auth/pages/login.form.password.label');

        // The "Forgot password?" link moves to the remember-me row (see below), so the
        // hint Filament puts on the password label is dropped.
        return parent::getPasswordFormComponent()
            ->label($this->labelWithRequiredMark($label))
            ->validationAttribute($label)
            ->markAsRequired(false)
            ->hint(null)
            ->placeholder(__('core::auth.password_placeholder'));
    }

    protected function getRememberFormComponent(): \Filament\Schemas\Components\Component
    {
        $component = parent::getRememberFormComponent()->label(__('core::auth.remember'));

        // CLA-603: Filament renders the reset link only when the panel has password reset
        // enabled — point it at that panel's own request route. Sits next to the
        // remember-me checkbox, as in the approved design.
        if (! filament()->hasPasswordReset()) {
            return $component;
        }

        return $component->hint(new HtmlString(Blade::render(
            '<a class="cafca-login-link" href="{{ filament()->getRequestPasswordResetUrl() }}">{{ __(\'core::auth.forgot_password_link\') }}</a>'
        )));
    }

    /**
     * Otro panel al que esta persona pueda entrar, o null si no puede entrar a ninguno.
     *
     * Se recorren los paneles registrados y se devuelve el primero que la admita, saltándose el
     * actual (que ya se sabe que no). Sirve para distinguir «no sos de aquí» de «no sos de
     * ninguna parte», que son la misma respuesta para `canAccessPanel` y no para la persona.
     */
    private function accessiblePanelFor(Authenticatable $user): ?string
    {
        $currentPanelId = Filament::getCurrentOrDefaultPanel()?->getId();

        foreach (Filament::getPanels() as $panelId => $panel) {
            if ($panelId === $currentPanelId) {
                continue;
            }

            if ($user->canAccessPanel($panel)) {
                return $panelId;
            }
        }

        return null;
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $credentials = $this->getCredentialsFromFormData($data);

        $user = $authProvider->retrieveByCredentials($credentials);

        if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
            $this->userUndertakingMultiFactorAuthentication = null;

            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        if (
            filled($this->userUndertakingMultiFactorAuthentication) &&
            (decrypt($this->userUndertakingMultiFactorAuthentication) === $user->getAuthIdentifier())
        ) {
            $this->multiFactorChallengeForm->validate();
        } else {
            foreach (Filament::getMultiFactorAuthenticationProviders() as $multiFactorAuthenticationProvider) {
                if (! $multiFactorAuthenticationProvider->isEnabled($user)) {
                    continue;
                }

                $this->userUndertakingMultiFactorAuthentication = encrypt($user->getAuthIdentifier());

                if ($multiFactorAuthenticationProvider instanceof HasBeforeChallengeHook) {
                    $multiFactorAuthenticationProvider->beforeChallenge($user);
                }

                break;
            }

            if (filled($this->userUndertakingMultiFactorAuthentication)) {
                $this->multiFactorChallengeForm->fill();

                return null;
            }
        }

        $authGuard->setRememberDuration(self::REMEMBER_MINUTES);

        if (! $authGuard->attemptWhen($credentials, function (Authenticatable $user): bool {
            if (($user instanceof FilamentUser) && (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))) {
                // No poder entrar AQUÍ no siempre es un rechazo: puede ser haber aterrizado en el
                // panel equivocado. Como el código de MFA ya se validó y se gastó para llegar
                // hasta aquí, negarlo ahora deja a la persona con un «código inválido» al
                // reintentar, sin haber hecho nada mal. Si pertenece a otro panel, se le lleva
                // allí; si no puede entrar a ninguno, se mantiene la denegación de siempre.
                $accessiblePanel = $this->accessiblePanelFor($user);

                if ($accessiblePanel === null) {
                    return false;
                }

                session()->put(RedirectToAccessiblePanel::SESSION_KEY, $accessiblePanel);

                return true;
            }

            // CLA-363. CLA-581: technician removed from this denylist — hasPanelAccess()
            // now grants it restricted access (Modules/FieldOps/Filament/Pages/MyWorkOrders
            // only, every other resource explicitly excludes it, see that ticket).
            if (($user instanceof User) && $user->hasRole('client')) {
                return false;
            }

            return true;
        }, $data['remember'] ?? false)) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
