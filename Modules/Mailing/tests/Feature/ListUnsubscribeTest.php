<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Mailing\Emails\ProspectCampaignMail;
use Modules\Prospects\Models\Prospect;
use Tests\TestCase;

class ListUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private function makeMailable(?string $trackingToken = null): ProspectCampaignMail
    {
        $prospect = Mockery::mock(Prospect::class);
        $prospect->id = 42;
        $prospect->shouldReceive('getUnsubscribeToken')->andReturn('test-token-abc123');
        $prospect->shouldReceive('getAttribute')->with('id')->andReturn(42);

        return new ProspectCampaignMail(
            prospect: $prospect,
            dynamicSubject: 'Test Subject',
            htmlBody: '<p>Test body</p>',
            unsubscribeUrl: 'https://claesen-verlichting.be/afmelden/?p=42&t=test-token-abc123&l=nl',
            trackingToken: $trackingToken,
        );
    }

    public function test_mailable_has_list_unsubscribe_header(): void
    {
        $headers = $this->makeMailable()->headers();

        $textHeaders = $headers->text ?? [];
        $this->assertArrayHasKey('List-Unsubscribe', $textHeaders);
        $this->assertStringContainsString('https://', $textHeaders['List-Unsubscribe']);
        $this->assertStringContainsString('mailto:', $textHeaders['List-Unsubscribe']);
    }

    public function test_mailable_has_list_unsubscribe_post_header(): void
    {
        $headers = $this->makeMailable()->headers();

        $textHeaders = $headers->text ?? [];
        $this->assertArrayHasKey('List-Unsubscribe-Post', $textHeaders);
        $this->assertSame('List-Unsubscribe=One-Click', $textHeaders['List-Unsubscribe-Post']);
    }

    public function test_list_unsubscribe_url_uses_configured_domain(): void
    {
        config(['mailing.unsubscribe_domain' => 'claesen-verlichting.be']);

        $headers     = $this->makeMailable()->headers();
        $textHeaders = $headers->text ?? [];

        $this->assertStringContainsString('claesen-verlichting.be', $textHeaders['List-Unsubscribe']);
    }

    // -------------------------------------------------------------------------
    // X-Mailing-Token header (MAI-029)
    // -------------------------------------------------------------------------

    public function test_mailable_includes_x_mailing_token_when_provided(): void
    {
        $token   = str_repeat('x', 64);
        $headers = $this->makeMailable($token)->headers();

        $this->assertArrayHasKey('X-Mailing-Token', $headers->text ?? []);
        $this->assertSame($token, $headers->text['X-Mailing-Token']);
    }

    public function test_mailable_omits_x_mailing_token_when_null(): void
    {
        $headers = $this->makeMailable(null)->headers();

        $this->assertArrayNotHasKey('X-Mailing-Token', $headers->text ?? []);
    }

    public function test_x_mailing_token_does_not_affect_list_unsubscribe_headers(): void
    {
        $token   = str_repeat('y', 64);
        $headers = $this->makeMailable($token)->headers();

        $this->assertArrayHasKey('List-Unsubscribe', $headers->text);
        $this->assertArrayHasKey('List-Unsubscribe-Post', $headers->text);
    }

    // -------------------------------------------------------------------------
    // One-click POST endpoint (RFC 8058)
    // -------------------------------------------------------------------------

    public function test_one_click_endpoint_accepts_valid_token_and_returns_200(): void
    {
        $prospect = \Modules\Prospects\Models\Prospect::withoutGlobalScopes()
            ->where('id', '>')
            ->firstOr(fn () => null);

        // Skip if no SQL Server prospects available in test env
        if (! $prospect) {
            $this->markTestSkipped('No Prospect available in test environment.');
        }

        $token = $prospect->getUnsubscribeToken();

        $response = $this->postJson("/api/v1/mailing/unsubscribe/{$prospect->id}/{$token}");

        $response->assertStatus(200);
    }

    public function test_one_click_endpoint_rejects_invalid_token(): void
    {
        // We can POST with any prospect ID; invalid token must return 403
        // We don't need a real prospect — any non-existing ID returns 404 from route model binding
        // So we test the invalid-token path with a valid prospect that exists in the DB.

        // Use a generic test: create a mock response scenario
        $this->markTestSkipped(
            'Requires a real Prospect (SQL Server ReadOnly). Covered by integration test.'
        );
    }
}
