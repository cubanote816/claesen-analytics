<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Illuminate\Notifications\Messages\MailMessage;
use Modules\Core\Models\User;
use Modules\Core\Support\MailBrand;

/**
 * Filament's sign-in-code email, with the sender made company-correct.
 *
 * The parent builds a subject and two lines and sets no `from`, so the message
 * goes out under the global mailer identity: here that is `MAIL_FROM_NAME`, which
 * resolves to `${APP_NAME}` — "Laravel". A Bertels employee entering the panel got
 * a code from "Laravel" at Claesen's address.
 *
 * This only overrides `toMail()`. The constructor is inherited on purpose:
 * Filament instantiates it itself with `code` and `codeExpiryMinutes`, so adding
 * an argument would break the flow — and redeclaring the promoted properties the
 * parent already has is a fatal error, not a style choice. The brand is derived
 * from the notifiable instead, which is also the only thing that survives the
 * queue: this notification implements `ShouldQueue`, so by send time there is no
 * request and no panel to read a site from.
 *
 * @see MailBrand for why the brand is not simply passed in.
 */
class VerifyEmailAuthenticationWithBrand extends VerifyEmailAuthentication
{
    public function toMail(object $notifiable): MailMessage
    {
        $brand = MailBrand::forUser($notifiable instanceof User ? $notifiable : null);

        return parent::toMail($notifiable)
            ->from($brand['address'], $brand['name'])
            // El saludo y la despedida se fijan acá porque los de Laravel son sus
            // plantillas: el saludo por defecto es «Hello!» —una clave que nadie
            // traduce, así que sale en inglés aunque el resto del correo esté en
            // neerlandés— y la despedida imprime `config('app.name')`.
            ->greeting(__('core::notifications.sign_in_code.greeting', [
                'name' => $notifiable instanceof User ? $notifiable->name : '',
            ]))
            ->salutation(__('core::notifications.sign_in_code.salutation'));
    }
}
