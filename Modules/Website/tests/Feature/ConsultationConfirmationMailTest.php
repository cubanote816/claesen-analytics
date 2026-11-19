<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Website\Mail\ConsultationConfirmationMail;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\SiteSetting;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * CLA-705: the visitor acknowledgement is localized (nl/fr/en/de), carries a
 * reference number and has an HTML and a plain-text part.
 */
final class ConsultationConfirmationMailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function locales(): array
    {
        return [
            'nl' => ['nl', 'Wij hebben uw bericht ontvangen', 'Beste Jan Peeters'],
            'fr' => ['fr', 'Nous avons bien reçu votre message', 'Bonjour Jan Peeters'],
            'en' => ['en', 'We have received your message', 'Dear Jan Peeters'],
            'de' => ['de', 'Wir haben Ihre Nachricht erhalten', 'Guten Tag Jan Peeters'],
        ];
    }

    #[DataProvider('locales')]
    public function test_it_renders_in_the_locale_of_the_request(string $locale, string $subject, string $greeting): void
    {
        [$site, $consultation] = $this->bertelsRequest(['locale' => $locale]);
        $mail = new ConsultationConfirmationMail($consultation, $site);
        $reference = $consultation->publicReference();

        $this->assertSame($subject.' ('.$reference.')', $mail->envelope()->subject);
        $mail->assertSeeInHtml($greeting);
        $mail->assertSeeInHtml($reference);
        $mail->assertSeeInText($greeting);
        $mail->assertSeeInText($reference);
        $this->assertStringContainsString('lang="'.$locale.'"', $mail->render());
    }

    public function test_the_reference_is_derived_from_the_request_id(): void
    {
        [, $consultation] = $this->bertelsRequest();

        $this->assertSame(sprintf('CR-%06d', $consultation->id), $consultation->publicReference());
    }

    public function test_it_falls_back_to_the_site_default_locale_for_a_missing_or_unsupported_locale(): void
    {
        foreach ([null, 'es'] as $locale) {
            [$site, $consultation] = $this->bertelsRequest(['locale' => $locale], defaultLocale: 'fr');
            $mail = new ConsultationConfirmationMail($consultation, $site);

            $this->assertStringStartsWith('Nous avons bien reçu votre message', $mail->envelope()->subject);
            $mail->assertSeeInText('Bonjour Jan Peeters');
        }
    }

    public function test_it_falls_back_to_english_when_neither_locale_is_supported(): void
    {
        [$site, $consultation] = $this->bertelsRequest(['locale' => null], defaultLocale: 'es');
        $mail = new ConsultationConfirmationMail($consultation, $site);

        $this->assertStringStartsWith('We have received your message', $mail->envelope()->subject);
    }

    public function test_both_parts_quote_the_message_and_the_html_part_escapes_it(): void
    {
        [$site, $consultation] = $this->bertelsRequest(['message' => "Line one\n<b>bold</b> & more"]);
        $mail = new ConsultationConfirmationMail($consultation, $site);

        $mail->assertSeeInText("Line one\n<b>bold</b> & more", false);
        $mail->assertSeeInHtml('&lt;b&gt;bold&lt;/b&gt; &amp; more', false);
        $mail->assertDontSeeInHtml('<b>bold</b>', false);
    }

    public function test_it_offers_the_site_phone_as_the_alternative_when_the_site_has_one(): void
    {
        [$site, $consultation] = $this->bertelsRequest();
        SiteSetting::query()->create(['site_id' => $site->id, 'key' => 'phone_display', 'type' => SiteSetting::TYPE_TEXT, 'value' => '014 81 30 80']);
        SiteSetting::query()->create(['site_id' => $site->id, 'key' => 'phone_tel', 'type' => SiteSetting::TYPE_TEXT, 'value' => 'tel:+3214813080']);
        $mail = new ConsultationConfirmationMail($consultation, $site);

        $mail->assertSeeInHtml('014 81 30 80');
        $mail->assertSeeInHtml('href="tel:+3214813080"', false);
        $mail->assertSeeInText('014 81 30 80');
    }

    public function test_it_leaves_out_the_phone_line_when_the_site_has_no_phone(): void
    {
        [$site, $consultation] = $this->bertelsRequest();
        $mail = new ConsultationConfirmationMail($consultation, $site);

        $mail->assertDontSeeInHtml('tel:', false);
        $mail->assertDontSeeInText('Dringend?');
    }

    public function test_it_keeps_the_site_sender(): void
    {
        [$site, $consultation] = $this->bertelsRequest();
        $from = (new ConsultationConfirmationMail($consultation, $site))->envelope()->from;

        $this->assertSame('info@electrobertels.test', $from->address);
        $this->assertSame('Electro Bertels', $from->name);
    }

    /**
     * CLA-707: the Electro Bertels acknowledgement uses the branded layout
     * (dark band, orange accent, logo by CID), not the generic header.
     */
    public function test_the_bertels_acknowledgement_uses_the_branded_layout(): void
    {
        [$site, $consultation] = $this->bertelsRequest();
        $html = (new ConsultationConfirmationMail($consultation, $site))->render();

        $this->assertStringContainsString('#EE7203', $html);
        $this->assertStringContainsString('#121212', $html);
        $this->assertStringContainsString('src="cid:bertels-logo@mail.brand"', $html);
        $this->assertStringNotContainsString('#1a1a2e', $html);
        $this->assertStringNotContainsString('#00aeef', $html);
    }

    /**
     * The branded header links the logo to config('app.url') — the private
     * backoffice in production. A visitor's mail must not link there: the logo
     * points to the site's public domain, or carries no link at all.
     */
    public function test_the_logo_never_links_to_the_backoffice(): void
    {
        config(['app.url' => 'https://backoffice.internal.test']);
        [$site, $consultation] = $this->bertelsRequest();

        $html = (new ConsultationConfirmationMail($consultation, $site))->render();
        $this->assertStringNotContainsString('backoffice.internal.test', $html);

        $site->update(['domain' => 'www.electrobertels.test']);
        $html = (new ConsultationConfirmationMail($consultation, $site->fresh()))->render();
        $this->assertStringContainsString('href="https://www.electrobertels.test"', $html);
        $this->assertStringNotContainsString('backoffice.internal.test', $html);
    }

    public function test_the_bertels_logo_is_embedded_when_the_mail_is_sent(): void
    {
        [$site, $consultation] = $this->bertelsRequest();

        Mail::mailer('array')->to('visitor@example.test')->send(new ConsultationConfirmationMail($consultation, $site));

        $sent = collect(Mail::mailer('array')->getSymfonyTransport()->messages())->last();
        $this->assertInstanceOf(SentMessage::class, $sent);
        $email = $sent->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);

        $contentIds = collect($email->getAttachments())
            ->filter(fn ($part) => $part->hasContentId())
            ->map(fn ($part) => $part->getContentId())
            ->values()
            ->all();
        $this->assertSame(['bertels-logo@mail.brand'], $contentIds);
        $this->assertStringContainsString('Uw referentienummer is', (string) $email->getTextBody());
    }

    /**
     * CLA-707: the branded layout renders Markdown, so whatever the visitor
     * typed must stay inert text — never a link or formatting in a mail sent
     * from the company's own address.
     */
    public function test_visitor_text_is_never_interpreted_as_markdown(): void
    {
        [$site, $consultation] = $this->bertelsRequest([
            'name' => '*Jan* [Peeters](https://evil.example/name)',
            'message' => "Klik [hier](https://evil.example/msg)\n\nTweede **vet** regel\n\n# kop",
        ]);
        $html = (new ConsultationConfirmationMail($consultation, $site))->render();

        $this->assertStringNotContainsString('evil.example/name"', $html);
        $this->assertStringNotContainsString('evil.example/msg"', $html);
        $this->assertStringNotContainsString('<strong>vet</strong>', $html);
        $this->assertStringNotContainsString('<em>Jan</em>', $html);
        $this->assertStringNotContainsString('<h1>kop</h1>', $html);
        $this->assertStringContainsString('Klik [hier](https://evil.example/msg)', $html);
        $this->assertStringContainsString('Tweede **vet** regel', $html);
    }

    public function test_the_claesen_acknowledgement_keeps_its_current_template(): void
    {
        $site = Site::query()->where('key', Site::CLAESEN_KEY)->firstOrFail();
        $consultation = ConsultationRequest::factory()->create(['site_id' => $site->id, 'locale' => 'nl']);
        $html = (new ConsultationConfirmationMail($consultation, $site))->render();

        $this->assertStringContainsString('#1a1a2e', $html);
        $this->assertStringNotContainsString('#EE7203', $html);
        $this->assertStringNotContainsString('cid:', $html);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{Site, ConsultationRequest}
     */
    private function bertelsRequest(array $attributes = [], string $defaultLocale = 'nl'): array
    {
        $site = Site::factory()->create([
            'organization_id' => Organization::factory()->create(['name' => 'Electro Bertels'])->id,
            'default_locale' => $defaultLocale,
            'mail_from_address' => 'info@electrobertels.test',
            'mail_from_name' => 'Electro Bertels',
        ]);

        $consultation = ConsultationRequest::factory()->create(array_merge([
            'site_id' => $site->id,
            'name' => 'Jan Peeters',
            'locale' => 'nl',
            'message' => 'Graag een offerte voor domotica.',
        ], $attributes));

        return [$site, $consultation];
    }
}
