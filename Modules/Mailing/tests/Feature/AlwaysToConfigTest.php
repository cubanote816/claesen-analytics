<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * CLA-532: config('mail.always_to') (from MAIL_TO_ADDRESS) must redirect BOTH
 * the default mailer and the named 'microsoft-graph' mailer — Mail::alwaysTo()
 * alone only affects the default mailer instance. No database is used and no
 * external HTTP is performed.
 */
class AlwaysToConfigTest extends TestCase
{
    /** @var array<string, string> */
    private array $overrides = [
        'MAIL_TO_ADDRESS' => 'qa@example.test',
        'MICROSOFT_GRAPH_CLIENT_ID' => 'fake-client-id',
        'MICROSOFT_GRAPH_TENANT_ID' => 'fake-tenant-id',
        'MICROSOFT_GRAPH_CLIENT_SECRET' => 'fake-client-secret',
    ];

    /** @var array<string, string|false> exact prior process-env values */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        // Set BEFORE the app boots so config/mail.php + both providers see them.
        // MailingServiceProvider::boot() does the real
        // Mail::mailer('microsoft-graph')->alwaysTo(...) wiring at boot time, and
        // MicrosoftGraphService needs non-empty credentials to pass
        // assertConfigured() and reach the (faked) HTTP call. The exact prior
        // value of each var is captured so tearDown restores it verbatim
        // (putenv($name) alone would drop a value that was previously set).
        foreach ($this->overrides as $name => $value) {
            $this->originalEnv[$name] = getenv($name);
            putenv("{$name}={$value}");
        }

        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'graph.microsoft.com/*' => Http::response([], 202),
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->originalEnv as $name => $original) {
            $original === false ? putenv($name) : putenv("{$name}={$original}");
        }
    }

    public function test_config_normalises_the_address_list(): void
    {
        $this->assertSame(['qa@example.test'], config('mail.always_to'));
    }

    public function test_default_mailer_recipients_are_redirected(): void
    {
        Mail::mailer('array')->raw('body', function ($message) {
            $message->to('real-customer@example.com')->subject('Hello');
        });

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertNotEmpty($messages);

        $to = collect($messages->last()->getOriginalMessage()->getTo())
            ->map(fn ($address) => $address->getAddress())
            ->all();

        $this->assertSame(['qa@example.test'], $to);
    }

    /**
     * Exercises the real MailingServiceProvider wiring — the test does NOT call
     * alwaysTo() or re-extend the mailer itself. It sends through the genuine
     * 'microsoft-graph' transport (HTTP faked) and inspects the final Graph
     * payload: the redirect must have replaced the caller's recipient.
     */
    public function test_microsoft_graph_payload_recipients_are_redirected_by_the_provider(): void
    {
        Mail::mailer('microsoft-graph')->send(new class extends Mailable
        {
            public function build()
            {
                return $this->to('real-customer@example.com')->subject('Hi')->html('<p>x</p>');
            }
        });

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/sendMail')) {
                return false;
            }

            $recipients = collect($request->data()['message']['toRecipients'])
                ->pluck('emailAddress.address')
                ->all();

            return $recipients === ['qa@example.test'];
        });

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sendMail')
            && str_contains($request->body(), 'real-customer@example.com'));
    }
}
