<?php

declare(strict_types=1);

namespace Modules\Mailing\Tests\Unit;

use Modules\Mailing\Mail\Transport\MicrosoftGraphTransport;
use Modules\Mailing\Services\MicrosoftGraphService;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Tests\TestCase;

/**
 * Un adjunto embebido via $message->embed() (Content-ID + Content-Disposition:
 * inline) que llega a Graph API como #microsoft.graph.fileAttachment plano,
 * sin isInline/contentId, se muestra como archivo suelto descargable en vez
 * de la imagen inline referenciada por <img src="cid:...">  en el HTML —
 * rompiendo el logo en todos los correos que lo usan (CLA-234).
 */
class MicrosoftGraphTransportPayloadTest extends TestCase
{
    private function buildPayload(Email $email): array
    {
        $transport = new MicrosoftGraphTransport($this->createMock(MicrosoftGraphService::class));

        $method = new \ReflectionMethod($transport, 'getPayload');
        $method->setAccessible(true);

        return $method->invoke($transport, $email);
    }

    public function test_embedded_inline_image_gets_is_inline_and_content_id(): void
    {
        $email = (new Email())
            ->from('hostmaster@claesen-verlichting.be')
            ->to('someone@example.com')
            ->subject('Test')
            ->html('<img src="cid:logo">');

        $part = (new DataPart('fake-png-bytes', 'brand-logo-light.png', 'image/png'))->asInline();
        $part->setContentId('logo@claesen-verlichting.be');
        $email->addPart($part);

        $payload = $this->buildPayload($email);

        $attachment = $payload['message']['attachments'][0];
        $this->assertTrue($attachment['isInline']);
        $this->assertSame('logo@claesen-verlichting.be', $attachment['contentId']);
    }

    public function test_regular_attachment_has_no_inline_fields(): void
    {
        $email = (new Email())
            ->from('hostmaster@claesen-verlichting.be')
            ->to('someone@example.com')
            ->subject('Test')
            ->html('<p>Body</p>')
            ->attach('fake-pdf-bytes', 'report.pdf', 'application/pdf');

        $payload = $this->buildPayload($email);

        $attachment = $payload['message']['attachments'][0];
        $this->assertArrayNotHasKey('isInline', $attachment);
        $this->assertArrayNotHasKey('contentId', $attachment);
    }
}
