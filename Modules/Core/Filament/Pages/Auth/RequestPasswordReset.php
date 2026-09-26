<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Core\Services\PasswordResetService;

/**
 * CLA-603: the mockup's "forgot password" screens, built on Filament's own
 * simple-layout page — so the panel's brand column and login-theme apply with
 * no extra wiring — but backed by this project's real reset mechanism.
 *
 * Filament's stock implementation calls Laravel's `password_reset_tokens`
 * broker through `Password::sendResetLink()`, which would be a second, parallel
 * reset mechanism next to the hashed activation code the API and the account
 * setup flow already share (CLA-371). `request()` is therefore replaced here:
 * eligibility, code lifetime and anti-enumeration behaviour all come from
 * PasswordResetService, so the backoffice can never drift from the PWAs.
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    /** CLA-603: the mockup's "Check your email" screen. */
    public bool $sent = false;

    /** CLA-603: echoed back on that screen only — never used for any lookup. */
    public ?string $sentTo = null;

    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $email = (string) ($this->form->getState()['email'] ?? '');

        app(PasswordResetService::class)->sendResetLink(
            $email,
            static fn ($user, string $code): string => Filament::getCurrentPanel()->getResetPasswordUrl($code, $user),
        );

        // The same screen for every address, exactly like the API's generic
        // response — the page must never reveal whether the account exists.
        // (That is also why this screen deliberately has no "open reset link"
        // button, which the mockup does show: being able to open the link here
        // would mean we only send it for accounts that exist, which is the
        // enumeration the API went out of its way to avoid.)
        $this->sent = true;
        $this->sentTo = $email;
    }

    /** The mockup's "resend email" link on the sent screen. */
    public function resend(): void
    {
        $this->request();
    }

    public function getHeading(): string|Htmlable|null
    {
        return __($this->sent ? 'core::auth.reset_sent_heading' : 'core::auth.forgot_heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! $this->sent) {
            return __('core::auth.forgot_subheading');
        }

        return __('core::auth.reset_sent_body', [
            'email' => $this->sentTo,
            'minutes' => PasswordResetService::CODE_TTL_MINUTES,
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->placeholder(__('core::auth.email_placeholder'));
    }

    protected function getRequestFormAction(): Action
    {
        return parent::getRequestFormAction()
            ->label(__('core::auth.forgot_submit'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent()
                    ->visible(! $this->sent),
                View::make('core::filament.auth.password-reset-sent')
                    ->visible($this->sent),
            ]);
    }
}
