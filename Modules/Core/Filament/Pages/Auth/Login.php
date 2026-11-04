<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Auth\RedirectToAccessiblePanel;
use Modules\Core\Filament\Pages\Auth\Concerns\HasRequiredMarkLabel;
use Modules\Core\Models\User;

/**
 * CLA-363: client must fail the backoffice login itself, not just lose
 * access to resources after authenticating (which is what
 * User::canAccessPanel()/hasPanelAccess() already do). This lives here — instead
 * of in canAccessPanel() — because Filament's Authenticate middleware also calls
 * canAccessPanel() on every panel request, and 403ing an already-authenticated
 * session there blocks the logout route too (it shares the same auth-middleware
 * group). A denylist inside isUserAllowedToAccessPanel() only ever runs at the login
 * attempt, leaving canAccessPanel()/EnsurePanelAccess free to keep handling
 * already-existing sessions (redirect to the no-access page, still allow logout).
 *
 * CLA-648: the rule hooks in through Filament's protected isUserAllowedToAccessPanel(),
 * which Filament calls both before presenting the MFA challenge and again when the login
 * is attempted. authenticate() must NOT be copied: this class used to duplicate it, and
 * after the Filament 5.10.3 upgrade the copy stored the challenge state in the old shape,
 * so the challenge form lost its code input and the second submit signed the user in
 * without any code. authenticate() now only wraps the parent call.
 */
class Login extends BaseLogin
{
    use HasRequiredMarkLabel;

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

    public function getHeading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getHeading()
            : __('core::auth.heading');
    }

    /** CLA-647: "Welcome back. Enter your details to continue." added no instruction the
     *  heading, fields and labels didn't already give — dropped for the sign-in step. The MFA
     *  challenge step keeps Filament's own subheading (its code-entry instructions). */
    public function getSubheading(): string|Htmlable|null
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            ? parent::getSubheading()
            : null;
    }

    protected function getEmailFormComponent(): Component
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

    protected function getPasswordFormComponent(): Component
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

    protected function getRememberFormComponent(): Component
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

    /**
     * Filament's own authenticate() does the whole sign-in; this only adds the 30-day remember
     * duration and, after a successful login, remembers which panel to land on (see
     * accessiblePanelFor()).
     */
    public function authenticate(): ?LoginResponse
    {
        Filament::auth()->setRememberDuration(self::REMEMBER_MINUTES);

        $response = parent::authenticate();

        if ($response === null) {
            return null;
        }

        $user = Filament::auth()->user();

        if (($user instanceof FilamentUser) && (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))) {
            // Only reachable when isUserAllowedToAccessPanel() let a wrong-panel user through,
            // i.e. they can enter another panel: send them there instead of back to the form.
            $accessiblePanel = $this->accessiblePanelFor($user);

            if ($accessiblePanel !== null) {
                session()->put(RedirectToAccessiblePanel::SESSION_KEY, $accessiblePanel);
            }
        }

        return $response;
    }

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        // CLA-363. CLA-581: technician removed from this denylist — hasPanelAccess()
        // now grants it restricted access (Modules/FieldOps/Filament/Pages/MyWorkOrders
        // only, every other resource explicitly excludes it, see that ticket).
        if (($user instanceof User) && $user->hasRole('client')) {
            return false;
        }

        if (parent::isUserAllowedToAccessPanel($user)) {
            return true;
        }

        // No poder entrar AQUÍ no siempre es un rechazo: puede ser haber aterrizado en el
        // panel equivocado. Como el código de MFA ya se validó y se gastó para llegar
        // hasta aquí, negarlo ahora deja a la persona con un «código inválido» al
        // reintentar, sin haber hecho nada mal. Si pertenece a otro panel, se le lleva
        // allí; si no puede entrar a ninguno, se mantiene la denegación de siempre.
        return $this->accessiblePanelFor($user) !== null;
    }
}
