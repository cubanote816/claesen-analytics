<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
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

    protected function getEmailFormComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getEmailFormComponent()->placeholder(__('core::auth.email_placeholder'));
    }

    protected function getPasswordFormComponent(): \Filament\Schemas\Components\Component
    {
        $component = parent::getPasswordFormComponent()
            ->placeholder(__('core::auth.password_placeholder'));

        // CLA-603: the mockup puts "Forgot password?" on the password label.
        // Filament renders that link only when the panel has password reset
        // enabled and with its own label — point it at ours.
        if (! filament()->hasPasswordReset()) {
            return $component;
        }

        return $component->hint(new HtmlString(Blade::render(
            '<x-filament::link :href="filament()->getRequestPasswordResetUrl()" tabindex="-1">{{ __(\'core::auth.forgot_password_link\') }}</x-filament::link>'
        )));
    }

    protected function getRememberFormComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getRememberFormComponent()->label(__('core::auth.remember'));
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
                return false;
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
