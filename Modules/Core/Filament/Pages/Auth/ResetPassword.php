<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Pages\Auth\Concerns\HasRequiredMarkLabel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\PasswordResetService;

/**
 * CLA-603: the mockup's "create a new password" and "password updated"
 * screens, on Filament's own simple-layout page.
 *
 * Same reason as RequestPasswordReset: Filament's stock `resetPassword()`
 * consumes a Laravel `password_reset_tokens` token through the broker, while
 * this project resets via the hashed activation code (PasswordResetService).
 * The token in the signed URL is therefore our 64-char code, and it is the
 * service that validates it (expiry, Microsoft-only accounts, single use).
 *
 * On success this does NOT redirect to the login: the mockup ends on an
 * explicit "Password updated" screen with a "Continue to sign in" button, so
 * `$done` swaps the form for that screen instead.
 */
class ResetPassword extends BaseResetPassword
{
    use HasRequiredMarkLabel;

    protected string $view = 'core::filament.auth.password-reset-reset';

    /**
     * The project's real password policy for this page: Filament's `PasswordRule::default()`
     * (nothing calls Password::defaults(), so Laravel's default: min 8, nothing else), the same
     * `min:8` the API requests use. Only mirrored in the `minlength` attribute and the hint.
     */
    private const PASSWORD_MIN_LENGTH = 8;

    /** CLA-603: the mockup's "Password updated" screen. */
    public bool $done = false;

    public function resetPassword(): ?PasswordResetResponse
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if ($this->isResetPasswordRateLimited($this->email)) {
            return null;
        }

        $password = (string) ($this->form->getState()['password'] ?? '');

        try {
            app(PasswordResetService::class)->resetPassword((string) $this->token, $password);
        } catch (ValidationException $exception) {
            // Same generic reason the API returns (422) for any unusable code.
            Notification::make()
                ->title((string) collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            return null;
        }

        $this->done = true;

        return null;
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return __($this->done ? 'core::auth.reset_done_heading' : 'core::auth.reset_new_heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __($this->done ? 'core::auth.reset_done_body' : 'core::auth.reset_new_subheading');
    }

    /**
     * Only the two password fields (no disabled email field, which is Filament's own layout).
     * The reset token and email stay as Livewire properties filled from the signed URL.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    public function getPasswordFormComponent(): Component
    {
        $label = __('core::auth.reset_new_password_label');

        return parent::getPasswordFormComponent()
            ->label($this->labelWithRequiredMark($label))
            ->validationAttribute($label)
            ->markAsRequired(false)
            // One toggle (below) switches both fields; the per-field eye icon goes away.
            ->revealable(false)
            ->suffix(new HtmlString(Blade::render('<button type="button" class="cafca-login-toggle" x-on:click="reveal = ! reveal" x-bind:aria-label="reveal ? \'{{ __(\'core::auth.reset_hide_passwords\') }}\' : \'{{ __(\'core::auth.reset_show_passwords\') }}\'" aria-label="{{ __(\'core::auth.reset_show_passwords\') }}"><span x-text="reveal ? \'{{ __(\'core::auth.password_hide\') }}\' : \'{{ __(\'core::auth.password_show\') }}\'">{{ __(\'core::auth.password_show\') }}</span></button>')))
            ->extraInputAttributes([
                'minlength' => self::PASSWORD_MIN_LENGTH,
                'aria-describedby' => 'reset-password-hint',
                'x-bind:type' => "reveal ? 'text' : 'password'",
            ])
            // The real `same:passwordConfirmation` rule keeps validating; only its text changes,
            // and it is shown once, as an alert under the confirm field.
            ->validationMessages(['same' => __('core::auth.reset_passwords_mismatch')])
            ->extraFieldWrapperAttributes(['class' => 'cafca-login-field--alert-below'])
            ->belowContent(new HtmlString('<span class="cafca-login-hint" id="reset-password-hint">'.e(__('core::auth.reset_password_hint', ['min' => self::PASSWORD_MIN_LENGTH])).'</span>'));
    }

    public function getPasswordConfirmationFormComponent(): Component
    {
        $label = __('core::auth.reset_confirm_password_label');

        return parent::getPasswordConfirmationFormComponent()
            ->label($this->labelWithRequiredMark($label))
            ->markAsRequired(false)
            ->extraInputAttributes(['x-bind:type' => "reveal ? 'text' : 'password'"])
            ->belowContent(View::make('core::filament.auth.password-reset-mismatch'));
    }

    public function getResetPasswordFormAction(): Action
    {
        return parent::getResetPasswordFormAction()
            ->label(__('core::auth.reset_submit_new'));
    }

    public function content(Schema $schema): Schema
    {
        // The success state is rendered by the page view (icon above the heading, link below).
        return $schema->components([$this->getFormContentComponent()]);
    }
}
