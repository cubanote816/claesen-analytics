<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// CLA-371: mirrors FieldOps\Notifications\ClientContactInvitationNotification's
// approach (plain MailMessage, action link into the frontend) rather than a
// fully custom Blade template, for consistency with the only other
// account-lifecycle email in the codebase.
class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly string $resetCode)
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
        return (new MailMessage)
            ->subject('Reset your password — Claesen Outdoor Lighting Platform')
            ->greeting("Hello {$notifiable->name},")
            ->line('We received a request to reset your password. This link expires in 60 minutes.')
            ->action('Reset password', $this->resetUrl())
            ->line('If you did not request this, you can safely ignore this email — your password will not change.');
    }

    private function resetUrl(): string
    {
        $portal = rtrim((string) config('fieldops.client_portal_url'), '/');

        return $portal.'?'.http_build_query(['reset_code' => $this->resetCode]);
    }
}
