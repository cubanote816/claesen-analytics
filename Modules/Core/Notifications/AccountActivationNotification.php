<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Models\User;
use Modules\Core\Services\PasswordResetService;
use Modules\Core\Support\MailBrand;

// CLA-685: the welcome email of an External worker (CLA-683) — the link to create
// their first password, valid 72 hours. Same conventions as PasswordResetNotification
// (brand from the recipient, recipient's language, queued after commit); it differs in
// what it has to tell a person who has never signed in: the backoffice is only
// reachable from the private network, and an expired link is replaced by an admin.
class AccountActivationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly string $activationUrl)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = MailBrand::forUser($notifiable instanceof User ? $notifiable : null);

        return (new MailMessage)
            ->from($brand['address'], $brand['name'])
            ->subject(__('core::notifications.account_activation.subject', ['company' => $brand['name']]))
            ->greeting(__('core::notifications.account_activation.greeting', ['name' => $notifiable->name]))
            ->line(__('core::notifications.account_activation.line_intro', ['company' => $brand['name']]))
            ->action(__('core::notifications.account_activation.action'), $this->activationUrl)
            ->line(__('core::notifications.account_activation.line_validity', [
                'hours' => PasswordResetService::WELCOME_CODE_TTL_MINUTES / 60,
            ]))
            ->line(__('core::notifications.account_activation.line_network'))
            ->line(__('core::notifications.account_activation.line_expired'))
            ->salutation(__('core::notifications.account_activation.salutation'));
    }
}
