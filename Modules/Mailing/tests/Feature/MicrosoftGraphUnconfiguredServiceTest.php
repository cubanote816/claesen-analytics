<?php

namespace Modules\Mailing\Tests\Feature;

use Error;
use Illuminate\Support\Facades\Http;
use Modules\Mailing\Exceptions\MailConfigurationException;
use Modules\Mailing\Services\MicrosoftGraphService;
use Tests\TestCase;

/**
 * CLA-532: MicrosoftGraphService must be constructible without credentials
 * (nullable properties, no validation in the constructor) so it never throws a
 * TypeError while a mailer is being resolved. Validation happens at the start
 * of every Graph operation and raises a controlled MailConfigurationException
 * — before any HTTP. No database is used.
 */
class MicrosoftGraphUnconfiguredServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.mailers.microsoft-graph.client_id' => null,
            'mail.mailers.microsoft-graph.tenant_id' => null,
            'mail.mailers.microsoft-graph.client_secret' => null,
        ]);

        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_constructor_does_not_throw_without_credentials(): void
    {
        $service = new MicrosoftGraphService;

        $this->assertInstanceOf(MicrosoftGraphService::class, $service);
    }

    public function test_get_access_token_raises_configuration_exception_not_type_error(): void
    {
        $service = new MicrosoftGraphService;

        try {
            $service->getAccessToken();
            $this->fail('Expected MailConfigurationException.');
        } catch (Error $e) {
            $this->fail('getAccessToken() threw a PHP Error ('.$e::class.'): '.$e->getMessage());
        } catch (MailConfigurationException $e) {
            $this->assertStringContainsString('Microsoft Graph mailer is not configured', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_send_mail_raises_configuration_exception_and_sends_no_http(): void
    {
        $service = new MicrosoftGraphService;

        $this->expectException(MailConfigurationException::class);

        try {
            $service->sendMail('sender@claesen-verlichting.be', ['message' => []]);
        } finally {
            Http::assertNothingSent();
        }
    }
}
