<?php

declare(strict_types=1);

namespace Modules\FieldOps\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\FieldOps\Models\FoClient;

class ClientContactInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly FoClient $client,
        private readonly ?string $activationCode,
    ) {
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
        $message = (new MailMessage)
            ->subject('Invitation — Claesen Outdoor Lighting Platform')
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been invited to access maintenance requests for {$this->client->name}.");

        return $message
            ->action($this->activationCode ? 'Activate account' : 'Open client portal', $this->activationUrl())
            ->line('If you did not expect this invitation, contact Claesen Verlichting.');
    }

    public function activationUrl(): string
    {
        $portal = rtrim((string) config('fieldops.client_portal_url'), '/');
        if (! $this->activationCode) {
            return $portal;
        }

        return $portal.'?'.http_build_query([
            'activation_code' => $this->activationCode,
            'setup_required' => 'true',
        ]);
    }
}
