<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * CLA-692: the same isolation with MAIL_TO_ADDRESS in the environment.
 *
 * A non-empty mail.always_to makes MailingServiceProvider::boot() resolve the
 * 'microsoft-graph' mailer before any test code runs. The guard must still
 * drop the environment's Graph credentials (inherited test), and a test that
 * sets its own Graph credentials after setUp() must reach its faked Graph API
 * with the alwaysTo redirect intact.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TestSuiteMailIsolationWithAlwaysToTest extends TestSuiteMailIsolationTest
{
    protected function environment(): array
    {
        return parent::environment() + ['MAIL_TO_ADDRESS' => 'qa@example.test'];
    }

    public function test_credentials_set_by_a_test_after_set_up_reach_the_faked_graph_api(): void
    {
        config([
            'mail.mailers.microsoft-graph.client_id' => 'case-client-id',
            'mail.mailers.microsoft-graph.tenant_id' => 'case-tenant-id',
            'mail.mailers.microsoft-graph.client_secret' => 'case-client-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'graph.microsoft.com/*' => Http::response([], 202),
        ]);

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

            return collect($request->data()['message']['toRecipients'])
                ->pluck('emailAddress.address')
                ->all() === ['qa@example.test'];
        });
    }
}
