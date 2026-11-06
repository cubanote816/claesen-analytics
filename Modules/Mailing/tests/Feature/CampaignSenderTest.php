<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Modules\Mailing\Emails\ProspectCampaignMail;
use Modules\Mailing\Mail\Transport\MicrosoftGraphTransport;
use Modules\Mailing\Services\MicrosoftGraphService;
use Modules\Prospects\Models\Prospect;
use Tests\TestCase;

/**
 * CLA-656 — campaign emails use a dedicated sender / Reply-To, and the NDR inbox
 * defaults to that sender.
 */
class CampaignSenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeMailable(): ProspectCampaignMail
    {
        $prospect = Mockery::mock(Prospect::class);
        $prospect->shouldReceive('getUnsubscribeToken')->andReturn('tok');
        $prospect->shouldReceive('getAttribute')->with('id')->andReturn(42);

        return new ProspectCampaignMail(
            prospect: $prospect,
            dynamicSubject: 'Subject',
            htmlBody: '<p>Body</p>',
            unsubscribeUrl: 'https://example.test/afmelden',
        );
    }

    /** Evaluate the mailing config file with the given env overrides (null = unset). */
    private function mailingConfigWithEnv(array $env): array
    {
        $keys = ['MAILING_FROM_ADDRESS', 'MAILING_FROM_NAME', 'MAILING_REPLY_TO', 'MAILING_NDR_INBOX', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'];
        $previous = [];
        foreach ($keys as $key) {
            $previous[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
        }

        try {
            return require base_path('Modules/Mailing/config/config.php');
        } finally {
            foreach ($previous as $key => [$getenv, $envVar, $server]) {
                $getenv === false ? putenv($key) : putenv("{$key}={$getenv}");
                $envVar === null ?: $_ENV[$key] = $envVar;
                $server === null ?: $_SERVER[$key] = $server;
            }
        }
    }

    // S1
    public function test_campaign_mail_uses_mailing_from_address_and_name(): void
    {
        config([
            'mailing.from_address' => 'sport@claesen-verlichting.be',
            'mailing.from_name'    => 'Bert Bertels',
            'mail.from.address'    => 'offerte@claesen-verlichting.be',
            'mail.from.name'       => 'Offerte',
        ]);

        $from = $this->makeMailable()->envelope()->from;

        $this->assertSame('sport@claesen-verlichting.be', $from->address);
        $this->assertSame('Bert Bertels', $from->name);
    }

    // S1
    public function test_campaign_mail_falls_back_to_mail_from_when_mailing_keys_unset(): void
    {
        config([
            'mailing.from_address' => null,
            'mailing.from_name'    => null,
            'mail.from.address'    => 'offerte@claesen-verlichting.be',
            'mail.from.name'       => 'Offerte',
        ]);

        $from = $this->makeMailable()->envelope()->from;

        $this->assertSame('offerte@claesen-verlichting.be', $from->address);
        $this->assertSame('Offerte', $from->name);
    }

    // S1
    public function test_config_from_falls_back_to_mail_from_env(): void
    {
        $config = $this->mailingConfigWithEnv(['MAIL_FROM_ADDRESS' => 'offerte@x.be', 'MAIL_FROM_NAME' => 'Offerte']);
        $this->assertSame('offerte@x.be', $config['from_address']);
        $this->assertSame('Offerte', $config['from_name']);

        $config = $this->mailingConfigWithEnv([
            'MAIL_FROM_ADDRESS' => 'offerte@x.be', 'MAILING_FROM_ADDRESS' => 'sport@x.be', 'MAILING_FROM_NAME' => 'Sport',
        ]);
        $this->assertSame('sport@x.be', $config['from_address']);
        $this->assertSame('Sport', $config['from_name']);
    }

    // S2
    public function test_campaign_mail_has_reply_to_from_mailing_reply_to(): void
    {
        config(['mailing.reply_to' => 'bert@claesen-verlichting.be', 'mail.from.address' => 'offerte@claesen-verlichting.be']);

        $replyTo = $this->makeMailable()->envelope()->replyTo;

        $this->assertCount(1, $replyTo);
        $this->assertSame('bert@claesen-verlichting.be', $replyTo[0]->address);
    }

    // S2
    public function test_campaign_mail_reply_to_falls_back_to_mail_from_address(): void
    {
        config(['mailing.reply_to' => null, 'mail.from.address' => 'offerte@claesen-verlichting.be']);

        $replyTo = $this->makeMailable()->envelope()->replyTo;

        $this->assertCount(1, $replyTo);
        $this->assertSame('offerte@claesen-verlichting.be', $replyTo[0]->address);
    }

    // S3
    public function test_ndr_inbox_falls_back_to_campaign_sender(): void
    {
        $this->assertSame('sport@x.be', $this->mailingConfigWithEnv(['MAILING_FROM_ADDRESS' => 'sport@x.be'])['ndr_inbox']);
        $this->assertSame('offerte@x.be', $this->mailingConfigWithEnv(['MAIL_FROM_ADDRESS' => 'offerte@x.be'])['ndr_inbox']);
        $this->assertSame('explicit@x.be', $this->mailingConfigWithEnv([
            'MAILING_FROM_ADDRESS' => 'sport@x.be', 'MAILING_NDR_INBOX' => 'explicit@x.be',
        ])['ndr_inbox']);
    }

    // S5
    public function test_graph_posts_campaign_from_mailing_sender_with_reply_to(): void
    {
        config([
            'mail.mailers.microsoft-graph.client_id'     => 'test-client-id',
            'mail.mailers.microsoft-graph.tenant_id'     => 'test-tenant-id',
            'mail.mailers.microsoft-graph.client_secret' => 'test-client-secret',
            'mailing.from_address' => 'sport@claesen-verlichting.be',
            'mailing.from_name'    => 'Bert Bertels',
            'mailing.reply_to'     => 'bert@claesen-verlichting.be',
            'mail.from.address'    => 'offerte@claesen-verlichting.be',
        ]);
        Cache::put('microsoft_graph_token', 'fake-token', 60);
        Http::fake(['*' => Http::response('', 202)]);

        Mail::extend('microsoft-graph-test', fn () => new MicrosoftGraphTransport(app(MicrosoftGraphService::class)));
        config(['mail.mailers.graphtest' => ['transport' => 'microsoft-graph-test']]);

        Mail::mailer('graphtest')->to('club@example.com')->send($this->makeMailable());

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/users/sport@claesen-verlichting.be/sendMail')) {
                return false;
            }
            $message = $request->data()['message'];

            return $message['from']['emailAddress']['address'] === 'sport@claesen-verlichting.be'
                && $message['replyTo'][0]['emailAddress']['address'] === 'bert@claesen-verlichting.be';
        });
    }

    // S6
    public function test_other_mailables_keep_default_sender(): void
    {
        config(['mailing.from_address' => 'sport@claesen-verlichting.be', 'mail.from.address' => 'offerte@claesen-verlichting.be']);

        $mailable = new \Illuminate\Mail\Mailable();
        $this->assertSame([], $mailable->from);
        $this->assertSame('offerte@claesen-verlichting.be', config('mail.from.address'));
    }
}
