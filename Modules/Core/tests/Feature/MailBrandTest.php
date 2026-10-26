<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email as Message;
use Illuminate\Support\Facades\Event;use Modules\Core\Models\User;
use Modules\Core\Notifications\PasswordResetNotification;
use Modules\Core\Notifications\VerifyEmailAuthenticationWithBrand;
use Modules\Core\Support\MailBrand;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Tests\TestCase;

/**
 * One application instance serves two companies, but Laravel's mailer has a single
 * global `from`. Left alone, every message carried `MAIL_FROM_NAME` — which here
 * resolved to `${APP_NAME}`, i.e. "Laravel" — and a Bertels user's password reset
 * arrived titled "Claesen Outdoor Lighting Platform".
 *
 * Both notifications are queued, so the company cannot come from the request or the
 * panel: by send time there is neither. It comes from the notifiable's organization,
 * which is what these tests pin down.
 */
final class MailBrandTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    public function test_the_name_is_the_users_own_company(): void
    {
        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization);

        $brand = MailBrand::forUser($this->bertelsFixtureUser($organization));

        $this->assertSame('Bertels Fixture Org', $brand['name']);
    }

    public function test_a_site_level_from_name_wins_over_the_organization_name(): void
    {
        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization, ['mail_from_name' => 'Winkel Bertels']);

        $brand = MailBrand::forUser($this->bertelsFixtureUser($organization));

        $this->assertSame('Winkel Bertels', $brand['name']);
    }

    /**
     * Without a user there is nothing to derive a company from, and inventing one
     * would be worse than the anonymous global value.
     */
    public function test_without_a_user_it_falls_back_to_the_global_config(): void
    {
        config(['mail.from.name' => 'Global Brand', 'mail.from.address' => 'global@example.test']);

        $brand = MailBrand::forUser(null);

        $this->assertSame('Global Brand', $brand['name']);
        $this->assertSame('global@example.test', $brand['address']);
    }

    public function test_the_reset_notification_names_the_users_company(): void
    {
        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization);

        $mail = (new PasswordResetNotification('a-code'))
            ->toMail($this->bertelsFixtureUser($organization, ['language' => 'en']));

        $this->assertSame('Reset your password — Bertels Fixture Org', $mail->subject);
        $this->assertSame('Bertels Fixture Org', $mail->from[1]);
    }

    /**
     * Both notifications went out in English for Dutch users: the reset copy was
     * hardcoded English, and Filament's sign-in code is translated but rendered under
     * the app default, which in the queue worker is never the recipient's.
     *
     * Laravel asks the notifiable for the locale (`NotificationSender::preferredLocale()`)
     * and wraps the send in it, so `User::preferredLocale()` is what makes both render
     * in the user's own language.
     *
     * This test sets the locale itself rather than calling the sender: `toMail()` is
     * invoked directly here, and the sender is the piece that applies the locale. Its
     * wiring is covered by the assertion on `preferredLocale()` above and proven end to
     * end by actually sending the notification — see the note in MailBrandTest's class
     * docblock and the live check recorded with this change.
     */
    public function test_the_reset_notification_renders_in_the_recipients_language(): void
    {
        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization);

        $user = $this->bertelsFixtureUser($organization, ['language' => 'nl']);
        $this->assertSame('nl', $user->preferredLocale());

        $notification = new PasswordResetNotification('a-code');

        app()->setLocale('en');
        $english = $notification->toMail($user);

        app()->setLocale('nl');
        $dutch = $notification->toMail($user);

        $this->assertSame('Wachtwoord opnieuw instellen — Bertels Fixture Org', $dutch->subject);
        $this->assertNotSame($english->subject, $dutch->subject);
        $this->assertStringContainsString('Hallo', (string) $dutch->greeting);
        $this->assertStringContainsString('herstellink', (string) $dutch->introLines[0]);
    }

    /**
     * This is the assertion that would have caught the first attempt. Laravel only asks
     * a notifiable for its locale behind `instanceof HasLocalePreference`
     * (`NotificationSender::preferredLocale()`), so having the method is not enough —
     * without the interface nothing calls it and both notifications keep rendering in
     * the app default. The live mail is what exposed it; this keeps it from coming back.
     */
    public function test_the_user_model_declares_the_locale_preference_interface(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Translation\HasLocalePreference::class,
            $this->bertelsFixtureUser(),
            'Laravel only consults preferredLocale() behind this interface',
        );
    }

    /**
     * The header and footer of every mail print `MailBrand::footerName()`, because
     * Laravel's markdown template is a component that receives no message and no data
     * — dumping `get_defined_vars()` inside it shows only `__path`, `__data`, `app`,
     * `attributes`, `slot`… So the company has to be live while the body renders, and
     * it is set by `forUser()`, which both notifications call from `toMail()`.
     */
    public function test_the_footer_name_follows_the_send_in_progress(): void
    {
        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization);

        config(['app.name' => 'Configured Name']);

        MailBrand::forUser($this->bertelsFixtureUser($organization));
        $this->assertSame('Bertels Fixture Org', MailBrand::footerName());

        // Sin usuario no hay empresa que afirmar: se vuelve al nombre configurado en
        // vez de dejar el del envío anterior.
        MailBrand::forUser(null);
        $this->assertSame('Configured Name', MailBrand::footerName());
    }

    /**
     * Forgetting after the send is what stops a company inheriting the previous one's
     * name. The listener matters as much as the setter, and registration is exactly
     * the kind of thing that breaks in silence.
     */
    public function test_the_brand_is_forgotten_after_each_send(): void
    {
        $this->assertTrue(
            Event::hasListeners(MessageSending::class),
            'nothing clears the brand after a send, so the next mail would inherit it',
        );

        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization);
        config(['app.name' => 'Configured Name']);

        MailBrand::forUser($this->bertelsFixtureUser($organization));
        $this->assertSame('Bertels Fixture Org', MailBrand::footerName());

        Event::dispatch(new MessageSending(new Message, []));

        $this->assertSame('Configured Name', MailBrand::footerName());
    }

    /**
     * A locale we ship nothing for must fall back to the default rather than render a
     * language with no files — that prints raw keys in a customer's inbox.
     */
    public function test_an_unsupported_language_falls_back_to_the_default(): void
    {
        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization);

        $user = $this->bertelsFixtureUser($organization, ['language' => 'de']);

        $this->assertNull($user->preferredLocale());
        $this->assertStringStartsWith(
            'Reset your password',
            (new PasswordResetNotification('a-code'))->toMail($user)->subject,
        );
    }

    public function test_the_sign_in_code_notification_carries_the_same_brand(): void
    {
        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization);

        $mail = (new VerifyEmailAuthenticationWithBrand('123456', 5))
            ->toMail($this->bertelsFixtureUser($organization));

        $this->assertSame('Bertels Fixture Org', $mail->from[1]);
        // The parent's body is left alone: this class only adds the sender.
        $this->assertNotEmpty($mail->introLines);
    }

    /**
     * The bug this replaces was company-specific, so the guard has to be too: a
     * pattern that only ever produced "contained no 'Laravel'" would pass just as
     * happily with the two companies swapped.
     */
    public function test_the_two_companies_do_not_share_a_name(): void
    {
        $bertelsOrganization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($bertelsOrganization);

        $bertels = MailBrand::forUser($this->bertelsFixtureUser($bertelsOrganization))['name'];
        $claesen = MailBrand::forUser($this->claesenUser())['name'];

        $this->assertNotSame($claesen, $bertels);
        $this->assertNotSame('Laravel', $bertels);
    }

    public function test_a_user_without_an_organization_does_not_break(): void
    {
        $user = User::factory()->create(['organization_id' => null]);

        config(['mail.from.name' => 'Global Brand']);

        $this->assertSame('Global Brand', MailBrand::forUser($user)['name']);
    }
}
