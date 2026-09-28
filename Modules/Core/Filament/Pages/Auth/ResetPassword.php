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
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
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

    public function getHeading(): string|Htmlable|null
    {
        return __($this->done ? 'core::auth.reset_done_heading' : 'core::auth.reset_new_heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __($this->done ? 'core::auth.reset_done_body' : 'core::auth.reset_new_subheading');
    }

    /**
     * The mockup's reset screen shows only the two password fields — no
     * (disabled) email field, which is Filament's own layout, and the
     * requirements checklist between the fields and the submit button.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                View::make('core::filament.auth.password-reset-requirements'),
            ]);
    }

    public function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label(__('core::auth.reset_new_password_label'))
            ->placeholder(__('core::auth.reset_new_password_placeholder'));
    }

    public function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label(__('core::auth.reset_confirm_password_label'))
            ->placeholder(__('core::auth.reset_confirm_password_placeholder'));
    }

    public function getResetPasswordFormAction(): Action
    {
        return parent::getResetPasswordFormAction()
            ->label(__('core::auth.reset_submit_new'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Closures, not the plain booleans: Filament memoizes the content
                // schema per component instance, so a value captured here would
                // stay at its first-render state.
                $this->getFormContentComponent()
                    ->visible(fn (): bool => ! $this->done),
                View::make('core::filament.auth.password-reset-done')
                    ->visible(fn (): bool => $this->done),
            ]);
    }
}
