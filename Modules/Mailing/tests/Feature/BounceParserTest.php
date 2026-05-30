<?php

namespace Modules\Mailing\Tests\Feature;

use Modules\Mailing\Enums\BounceClassification;
use Modules\Mailing\Services\BounceParserService;
use Tests\TestCase;

class BounceParserTest extends TestCase
{
    private BounceParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BounceParserService();
    }

    // -------------------------------------------------------------------------
    // extractEmail
    // -------------------------------------------------------------------------

    public function test_extracts_email_from_final_recipient_header(): void
    {
        $body = "Final-Recipient: rfc822; user@example.com\nStatus: 5.1.1";

        $this->assertSame('user@example.com', $this->parser->extractEmail('', $body));
    }

    public function test_extracts_email_from_o365_english_ndr_body(): void
    {
        $body = <<<NDR
        Delivery has failed to these recipients or groups:

        test.contact@sport-club.be
        Your message couldn't be delivered because the recipient's email provider rejected it.
        NDR;

        $this->assertSame('test.contact@sport-club.be', $this->parser->extractEmail('Undeliverable: Offerte', $body));
    }

    public function test_extracts_email_from_o365_dutch_ndr_body(): void
    {
        $body = <<<NDR
        Aflevering is mislukt voor deze geadresseerden:

        voetbalclub@gemeente.be
        Uw bericht kon niet worden afgeleverd.
        NDR;

        $this->assertSame('voetbalclub@gemeente.be', $this->parser->extractEmail('Niet te bezorgen: Nieuwsbrief', $body));
    }

    public function test_extracts_email_from_original_recipient_header(): void
    {
        $body = "Original-Recipient: rfc822; bounce-me@domain.org\nFinal-Recipient: rfc822; bounce-me@domain.org";

        $this->assertSame('bounce-me@domain.org', $this->parser->extractEmail('', $body));
    }

    public function test_returns_null_when_no_email_found(): void
    {
        $this->assertNull($this->parser->extractEmail('Out of office', 'I am on vacation until Monday.'));
    }

    public function test_returns_null_for_empty_body(): void
    {
        $this->assertNull($this->parser->extractEmail('', ''));
    }

    public function test_normalizes_extracted_email_to_lowercase(): void
    {
        $body = "Final-Recipient: rfc822; User.Name@Example.COM";

        $this->assertSame('user.name@example.com', $this->parser->extractEmail('', $body));
    }

    // -------------------------------------------------------------------------
    // classifyBounce
    // -------------------------------------------------------------------------

    public function test_classifies_551_status_as_hard(): void
    {
        $body = "Status: 5.1.1\nDiagnostic-Code: smtp; 550 5.1.1 The email account does not exist.";

        $this->assertSame(BounceClassification::HARD, $this->parser->classifyBounce($body));
    }

    public function test_classifies_user_unknown_keyword_as_hard(): void
    {
        $body = "Reported error: 550 user unknown in virtual mailbox table";

        $this->assertSame(BounceClassification::HARD, $this->parser->classifyBounce($body));
    }

    public function test_classifies_does_not_exist_as_hard(): void
    {
        $body = "The email account that you tried to reach does not exist. Please try double-checking the recipient's email address.";

        $this->assertSame(BounceClassification::HARD, $this->parser->classifyBounce($body));
    }

    public function test_classifies_mailbox_not_found_as_hard(): void
    {
        $body = "550 mailbox not found";

        $this->assertSame(BounceClassification::HARD, $this->parser->classifyBounce($body));
    }

    public function test_classifies_4xx_mailbox_full_as_soft(): void
    {
        $body = "Status: 4.2.2\nDiagnostic-Code: smtp; 452 4.2.2 Mailbox full";

        $this->assertSame(BounceClassification::SOFT, $this->parser->classifyBounce($body));
    }

    public function test_classifies_quota_exceeded_as_soft(): void
    {
        $body = "Reported error: 452 quota exceeded for this mailbox";

        $this->assertSame(BounceClassification::SOFT, $this->parser->classifyBounce($body));
    }

    public function test_classifies_temporarily_unavailable_as_soft(): void
    {
        $body = "The server is temporarily unavailable. Try again later.";

        $this->assertSame(BounceClassification::SOFT, $this->parser->classifyBounce($body));
    }

    public function test_classifies_dutch_tijdelijk_as_soft(): void
    {
        $body = "De server is tijdelijk niet beschikbaar. Probeer het later opnieuw.";

        $this->assertSame(BounceClassification::SOFT, $this->parser->classifyBounce($body));
    }

    public function test_returns_unknown_for_unrecognized_body(): void
    {
        $body = "Something went wrong. No further details available.";

        $this->assertSame(BounceClassification::UNKNOWN, $this->parser->classifyBounce($body));
    }

    public function test_hard_takes_precedence_over_soft_when_both_signals_present(): void
    {
        // 5xx checked before 4xx — hard wins if both codes appear
        $body = "Status: 5.1.1\nAlso found 4.2.2 in the trail.";

        $this->assertSame(BounceClassification::HARD, $this->parser->classifyBounce($body));
    }

    // -------------------------------------------------------------------------
    // extractMailingToken
    // -------------------------------------------------------------------------

    public function test_extracts_mailing_token_from_ndr_original_headers_section(): void
    {
        $token = str_repeat('a', 62) . 'Zz'; // 64 alphanumeric chars
        $body = <<<NDR
        Delivery has failed to these recipients or groups:

        user@example.com

        Original message headers:

        Received: from mail.example.com
        From: sender@claesen-verlichting.be
        X-Mailing-Token: {$token}
        Subject: Offerte
        NDR;

        $this->assertSame($token, $this->parser->extractMailingToken($body));
    }

    public function test_returns_null_when_no_mailing_token_in_ndr(): void
    {
        $body = <<<NDR
        Delivery has failed to these recipients or groups:

        user@example.com

        Original message headers:

        Received: from mail.example.com
        Subject: Offerte
        NDR;

        $this->assertNull($this->parser->extractMailingToken($body));
    }

    public function test_returns_null_for_token_with_wrong_length(): void
    {
        $body = "X-Mailing-Token: tooshort123\nStatus: 5.1.1";

        $this->assertNull($this->parser->extractMailingToken($body));
    }
}
