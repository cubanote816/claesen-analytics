<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Models\User;
use Modules\Core\Support\MailBrand;

// CLA-371: mirrors FieldOps\Notifications\ClientContactInvitationNotification's
// approach (plain MailMessage, action link into the frontend) rather than a
// fully custom Blade template, for consistency with the only other
// account-lifecycle email in the codebase.
// CLA-603: `$resetUrl` lets a caller send the same email to a different
// front door — the backoffice login builds a signed Filament reset URL per
// panel; leaving it null keeps the original client-portal link verbatim.
class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $resetCode,
        private readonly ?string $resetUrl = null,
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
        // The company comes from the notifiable, not from the panel that fired the
        // request: this notification is queued, so by send time there is no request
        // and no panel — and a Bertels user was getting a mail titled "Claesen
        // Outdoor Lighting Platform" from Claesen's address.
        $brand = MailBrand::forUser($notifiable instanceof User ? $notifiable : null);

        // Textos traducidos, no literales: el idioma lo elige el destinatario a través
        // de `User::preferredLocale()`, que Laravel consulta solo al enviar la
        // notificación. Antes estaban en inglés fijo y un usuario neerlandés recibía
        // «Reset your password».
        return (new MailMessage)
            ->from($brand['address'], $brand['name'])
            ->subject(__('core::notifications.password_reset.subject', ['company' => $brand['name']]))
            ->greeting(__('core::notifications.password_reset.greeting', ['name' => $notifiable->name]))
            ->line(__('core::notifications.password_reset.line_request'))
            ->action(__('core::notifications.password_reset.action'), $this->resetUrl())
            ->line(__('core::notifications.password_reset.line_ignore'))
            // Despedida propia: la de Laravel imprime `config('app.name')`, que en una
            // instancia con dos empresas no puede ser el nombre de las dos.
            ->salutation(__('core::notifications.password_reset.salutation'));
    }

    private function resetUrl(): string
    {
        if ($this->resetUrl !== null) {
            return $this->resetUrl;
        }

        $portal = rtrim((string) config('fieldops.client_portal_url'), '/');

        return $portal.'?'.http_build_query(['reset_code' => $this->resetCode]);
    }
}
