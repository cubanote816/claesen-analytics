<?php

namespace Modules\Mailing\Services;

use Modules\Mailing\Enums\BounceClassification;

/**
 * Parses NDR (Non-Delivery Report) messages received from Microsoft Graph.
 * Pure parsing logic — no HTTP, no database access.
 *
 * Correlation limitation: outgoing emails do not carry an X-Mailing-Token header,
 * so correlation with mailing_messages is best-effort by email address only.
 * See MAI-029 for adding a correlation header to ProspectCampaignMail.
 */
class BounceParserService
{
    private const EMAIL_PATTERNS = [
        '/Final-Recipient:\s*rfc822;\s*(\S+@\S+)/i',
        '/Original-Recipient:\s*rfc822;\s*(\S+@\S+)/i',
        '/failed.*?to\s+(\S+@[a-z0-9\-\.]+\.[a-z]{2,})/i',
        '/could not be delivered to[:\s]+(\S+@[a-z0-9\-\.]+\.[a-z]{2,})/i',
        '/delivery.*?failed.*?\b([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})\b/i',
        // O365: "Delivery has failed to these recipients or groups:\r\n\r\nemail@example.com"
        '/delivery has (?:failed|not been delivered) to[^\n]*\n+\s*([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i',
        // O365 Dutch: "Aflevering is mislukt voor deze geadresseerden"
        '/aflevering.*?mislukt.*?\n+\s*([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i',
    ];

    private const HARD_BOUNCE_CODES = [
        '5.1.1', '5.1.2', '5.1.3', '5.1.6',
        '5.2.1', '5.4.1',
        '550', '551', '552', '553', '554',
    ];

    private const SOFT_BOUNCE_CODES = [
        '4.2.2', '4.3.1', '4.4.1', '4.4.2',
        '421', '450', '451', '452',
    ];

    private const HARD_BOUNCE_KEYWORDS = [
        'does not exist', 'user unknown', 'no such user',
        'mailbox not found', 'address rejected', 'invalid address',
        'user doesn\'t exist', 'recipient address rejected',
        'account does not exist', 'address not found',
        // Dutch O365
        'bestaat niet', 'onbekende gebruiker', 'ongeldig adres',
        'kan niet worden gevonden',
    ];

    private const SOFT_BOUNCE_KEYWORDS = [
        'mailbox full', 'quota exceeded', 'over quota',
        'temporarily unavailable', 'try again later',
        'service temporarily unavailable', 'insufficient system storage',
        // Dutch
        'mailbox vol', 'tijdelijk niet beschikbaar', 'probeer het later opnieuw',
    ];

    public function extractEmail(string $subject, string $body): ?string
    {
        $fullText = $subject . "\n" . $body;

        foreach (self::EMAIL_PATTERNS as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $email = strtolower(trim($matches[1], " \t\r\n<>;,"));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }

        return null;
    }

    public function classifyBounce(string $body): BounceClassification
    {
        $lower = strtolower($body);

        foreach (self::HARD_BOUNCE_CODES as $code) {
            if (str_contains($lower, strtolower($code))) {
                return BounceClassification::HARD;
            }
        }

        foreach (self::HARD_BOUNCE_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return BounceClassification::HARD;
            }
        }

        foreach (self::SOFT_BOUNCE_CODES as $code) {
            if (str_contains($lower, strtolower($code))) {
                return BounceClassification::SOFT;
            }
        }

        foreach (self::SOFT_BOUNCE_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return BounceClassification::SOFT;
            }
        }

        return BounceClassification::UNKNOWN;
    }
}
