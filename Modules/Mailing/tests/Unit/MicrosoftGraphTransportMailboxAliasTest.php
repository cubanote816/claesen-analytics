<?php

declare(strict_types=1);

namespace Modules\Mailing\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Mailing\Mail\Transport\MicrosoftGraphTransport;
use Modules\Mailing\Services\MicrosoftGraphService;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * CLA-699 (S2): Graph sends via /users/{mailbox}/sendMail where mailbox is the
 * configured alias target, while message.from/sender keep the alias address.
 */
class MicrosoftGraphTransportMailboxAliasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.mailers.microsoft-graph.client_id' => 'cid',
            'mail.mailers.microsoft-graph.tenant_id' => 'tid',
            'mail.mailers.microsoft-graph.client_secret' => 'secret',
        ]);
        Cache::put('microsoft_graph_token', 'fake-token', 60);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 202)]);
    }

    /** @return array{0: string, 1: array} url + json body of the sendMail request */
    private function send(string $from): array
    {
        $email = (new Email())
            ->from($from)
            ->to('someone@example.com')
            ->subject('Test')
            ->html('<p>Body</p>');

        $transport = new MicrosoftGraphTransport(new MicrosoftGraphService());
        $transport->send($email);

        $captured = [];
        Http::assertSent(function (Request $request) use (&$captured) {
            if (str_ends_with($request->url(), '/sendMail')) {
                $captured = [$request->url(), $request->data()];
            }

            return true;
        });

        $this->assertNotEmpty($captured, 'sendMail request was not sent');

        return $captured;
    }

    public function test_mapped_alias_posts_to_mailbox_and_keeps_alias_as_from_and_sender(): void
    {
        config(['mail.mailers.microsoft-graph.mailbox_aliases' => [
            'backoffice@claesen-verlichting.be' => 'sport@claesen-verlichting.be',
        ]]);

        [$url, $body] = $this->send('backoffice@claesen-verlichting.be');

        $this->assertStringEndsWith('/users/sport@claesen-verlichting.be/sendMail', $url);
        $this->assertSame('backoffice@claesen-verlichting.be', $body['message']['from']['emailAddress']['address']);
        $this->assertSame('backoffice@claesen-verlichting.be', $body['message']['sender']['emailAddress']['address']);
    }

    public function test_alias_match_is_case_insensitive(): void
    {
        config(['mail.mailers.microsoft-graph.mailbox_aliases' => [
            'backoffice@claesen-verlichting.be' => 'sport@claesen-verlichting.be',
        ]]);

        [$url, $body] = $this->send('BackOffice@Claesen-Verlichting.be');

        $this->assertStringEndsWith('/users/sport@claesen-verlichting.be/sendMail', $url);
        $this->assertSame('BackOffice@Claesen-Verlichting.be', $body['message']['from']['emailAddress']['address']);
    }

    public function test_unmapped_sender_uses_its_own_address(): void
    {
        config(['mail.mailers.microsoft-graph.mailbox_aliases' => [
            'backoffice@claesen-verlichting.be' => 'sport@claesen-verlichting.be',
        ]]);

        [$url, $body] = $this->send('offerte@claesen-verlichting.be');

        $this->assertStringEndsWith('/users/offerte@claesen-verlichting.be/sendMail', $url);
        $this->assertSame('offerte@claesen-verlichting.be', $body['message']['from']['emailAddress']['address']);
        $this->assertSame('offerte@claesen-verlichting.be', $body['message']['sender']['emailAddress']['address']);
    }

    public function test_empty_or_unset_map_behaves_as_before(): void
    {
        config(['mail.mailers.microsoft-graph.mailbox_aliases' => []]);
        [$url] = $this->send('backoffice@claesen-verlichting.be');
        $this->assertStringEndsWith('/users/backoffice@claesen-verlichting.be/sendMail', $url);

        config(['mail.mailers.microsoft-graph.mailbox_aliases' => null]);
        [$url] = $this->send('backoffice@claesen-verlichting.be');
        $this->assertStringEndsWith('/users/backoffice@claesen-verlichting.be/sendMail', $url);
    }

    public function test_env_format_is_parsed_into_lowercased_map_ignoring_malformed_pairs(): void
    {
        putenv('MICROSOFT_GRAPH_MAILBOX_ALIASES= A@X.be=B@X.be, c@x.be=d@x.be,garbage,=nokey,nomailbox=, ,e@x.be=f@x.be=g ');

        try {
            $config = require config_path('mail.php');
        } finally {
            putenv('MICROSOFT_GRAPH_MAILBOX_ALIASES');
        }

        $this->assertSame(
            ['a@x.be' => 'B@X.be', 'c@x.be' => 'd@x.be'],
            $config['mailers']['microsoft-graph']['mailbox_aliases']
        );
    }

    public function test_env_unset_yields_empty_map(): void
    {
        putenv('MICROSOFT_GRAPH_MAILBOX_ALIASES');
        $config = require config_path('mail.php');

        $this->assertSame([], $config['mailers']['microsoft-graph']['mailbox_aliases']);
    }
}
