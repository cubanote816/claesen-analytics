<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Modules\Core\Models\User;
use Modules\Core\Notifications\AccountActivationNotification;
use Modules\Core\Notifications\PasswordResetNotification;
use Modules\Core\Notifications\VerifyEmailAuthenticationWithBrand;
use Modules\Core\Support\MailBrand;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CLA-686: every markdown mail renders as the "brand band header" design in the
 * recipient company's colors (Claesen #00aeef, Electro Bertels #EE7203), with the
 * logo embedded as a CID attachment — the backoffice is private-network only, so a
 * URL logo would never load for the recipient.
 */
final class BrandedMailTemplateTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    private const CLAESEN_ACCENT = '#00aeef';

    private const BERTELS_ACCENT = '#EE7203';

    /**
     * @return array<string, array{0: callable(): Notification}>
     */
    public static function notifications(): array
    {
        return [
            'account activation' => [fn () => new AccountActivationNotification('https://example.test/activate')],
            'password reset' => [fn () => new PasswordResetNotification('a-code')],
            'sign-in code' => [fn () => new VerifyEmailAuthenticationWithBrand('123456', 5)],
        ];
    }

    /** Sends synchronously through the array mailer and returns what was actually built. */
    private function send(User $user, Notification $notification): Email
    {
        NotificationFacade::sendNow($user, $notification);

        $sent = collect(Mail::mailer()->getSymfonyTransport()->messages())->last();
        $this->assertInstanceOf(SentMessage::class, $sent);

        $email = $sent->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);

        return $email;
    }

    private function bertelsUser(string $language = 'nl'): User
    {
        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization);

        return $this->bertelsFixtureUser($organization, ['language' => $language]);
    }

    #[DataProvider('notifications')]
    public function test_a_claesen_recipient_gets_the_cyan_band_and_an_embedded_logo(callable $make): void
    {
        $email = $this->send($this->claesenUser(['language' => 'nl']), $make());
        $html = $email->getHtmlBody();

        $this->assertStringContainsString(self::CLAESEN_ACCENT, $html);
        $this->assertStringNotContainsString(self::BERTELS_ACCENT, $html);
        $this->assertStringContainsString('#0b0b0f', $html, 'dark brand band');
        $this->assertMatchesRegularExpression('/<img[^>]+src="cid:[^"]+"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]+src="https?:/i', $html);
        $this->assertCount(1, $email->getAttachments());
        $this->assertSame('image/png', $email->getAttachments()[0]->getMediaType().'/'.$email->getAttachments()[0]->getMediaSubtype());
    }

    #[DataProvider('notifications')]
    public function test_a_bertels_recipient_gets_the_orange_band_and_an_embedded_logo(callable $make): void
    {
        $email = $this->send($this->bertelsUser(), $make());
        $html = $email->getHtmlBody();

        $this->assertStringContainsString(self::BERTELS_ACCENT, $html);
        $this->assertStringNotContainsString(self::CLAESEN_ACCENT, $html);
        $this->assertStringContainsString('#121212', $html, 'dark brand band');
        $this->assertMatchesRegularExpression('/<img[^>]+src="cid:[^"]+"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]+src="https?:/i', $html);
        $this->assertCount(1, $email->getAttachments());
    }

    public function test_the_cid_in_the_html_points_at_the_attached_inline_logo(): void
    {
        $email = $this->send($this->bertelsUser(), new AccountActivationNotification('https://example.test/a'));

        preg_match('/src="cid:([^"]+)"/', $email->getHtmlBody(), $match);
        $attachment = $email->getAttachments()[0];

        $this->assertSame($match[1], $attachment->getContentId());
        $this->assertSame('inline', $attachment->getPreparedHeaders()->getHeaderBody('Content-Disposition'));
    }

    public function test_the_button_label_is_dark_on_the_accent_for_both_brands(): void
    {
        $claesen = $this->send($this->claesenUser(['language' => 'nl']), new AccountActivationNotification('https://example.test/a'))->getHtmlBody();
        $bertels = $this->send($this->bertelsUser(), new AccountActivationNotification('https://example.test/a'))->getHtmlBody();

        $this->assertMatchesRegularExpression('/<a[^>]+href="https:\/\/example\.test\/a"[^>]+style="[^"]*color:\s*#0b0b0f/i', $claesen);
        $this->assertMatchesRegularExpression('/<a[^>]+href="https:\/\/example\.test\/a"[^>]+style="[^"]*color:\s*#121212/i', $bertels);
    }

    public function test_the_footer_is_translated_per_recipient_language(): void
    {
        $nl = $this->send($this->claesenUser(['language' => 'nl']), new AccountActivationNotification('https://example.test/a'));
        $en = $this->send($this->bertelsUser('en'), new AccountActivationNotification('https://example.test/a'));

        $this->assertStringContainsString('Alle rechten voorbehouden.', $nl->getHtmlBody());
        $this->assertStringContainsString('Alle rechten voorbehouden.', $nl->getTextBody());
        $this->assertStringContainsString('All rights reserved.', $en->getHtmlBody());
        $this->assertStringContainsString('All rights reserved.', $en->getTextBody());
        $this->assertStringNotContainsString('Alle rechten', $en->getHtmlBody());
    }

    public function test_a_bertels_mail_names_bertels_in_the_footer(): void
    {
        $html = $this->send($this->bertelsUser(), new AccountActivationNotification('https://example.test/a'))->getHtmlBody();

        $this->assertStringContainsString(date('Y').' Bertels Fixture Org', $html);
    }

    public function test_dark_mode_is_declared(): void
    {
        $html = $this->send($this->claesenUser(['language' => 'nl']), new AccountActivationNotification('https://example.test/a'))->getHtmlBody();

        $this->assertStringContainsString('<meta name="color-scheme" content="light dark">', $html);
        $this->assertStringContainsString('<meta name="supported-color-schemes" content="light dark">', $html);
    }

    public function test_texts_subject_and_plain_text_version_are_unchanged(): void
    {
        $user = $this->claesenUser(['language' => 'nl', 'name' => 'Sanne Verbruggen']);
        $email = $this->send($user, new AccountActivationNotification('https://example.test/a'));
        $notification = new AccountActivationNotification('https://example.test/a');
        $mail = $notification->toMail($user);

        $this->assertSame($mail->subject, $email->getSubject());
        $this->assertStringContainsString(__('core::notifications.account_activation.action'), $email->getHtmlBody());
        $this->assertStringContainsString(__('core::notifications.account_activation.line_network'), $email->getHtmlBody());
        $this->assertStringContainsString(__('core::notifications.account_activation.line_network'), $email->getTextBody());
        $this->assertStringContainsString('https://example.test/a', $email->getTextBody());
        $this->assertStringNotContainsString('<', $email->getTextBody());
    }

    public function test_the_brand_exposes_its_accent_and_logo_and_forgets_them_after_a_send(): void
    {
        $bertels = $this->bertelsUser();
        MailBrand::forUser($bertels);

        $this->assertSame(self::BERTELS_ACCENT, MailBrand::accent());
        $this->assertFileExists(MailBrand::logoPath());

        MailBrand::forget();

        $this->assertSame(self::CLAESEN_ACCENT, MailBrand::accent());
    }
}
