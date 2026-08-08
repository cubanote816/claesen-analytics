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
use Modules\Core\Models\User;

/**
 * CLA-363: client/technician must fail the backoffice login itself, not just lose
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

        if (! $authGuard->attemptWhen($credentials, function (Authenticatable $user): bool {
            if (($user instanceof FilamentUser) && (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))) {
                return false;
            }

            // CLA-363
            if (($user instanceof User) && $user->hasAnyRole(['client', 'technician'])) {
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
