<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Validates the ID token of a sign-in: signature against the provider's JWKS, issuer,
 * audience, expiry (small clock skew), nonce and subject. A valid ID token proves who
 * signed in; it does NOT authorize plan usage (that is the granted scope).
 */
final class ChatGptIdTokenValidator
{
    private const LEEWAY_SECONDS = 60;

    public function __construct(
        private readonly ChatGptSettings $settings,
        private readonly ChatGptDiscovery $discovery,
    ) {}

    /** @return array{sub: string, email: ?string, name: ?string} */
    public function validate(string $idToken, string $nonce): array
    {
        $document = $this->discovery->document();

        try {
            $jwks = Http::acceptJson()->timeout(10)->get($document['jwks_uri'])->throw()->json();
            $keys = JWK::parseKeySet(is_array($jwks) ? $jwks : [], 'RS256');

            $previousLeeway = JWT::$leeway;
            JWT::$leeway = self::LEEWAY_SECONDS;
            try {
                $claims = (array) JWT::decode($idToken, $keys);
            } finally {
                JWT::$leeway = $previousLeeway;
            }
        } catch (ConnectionException) {
            throw new ChatGptFailed('id_token_invalid', 'The signing keys could not be fetched.');
        } catch (Throwable) {
            throw new ChatGptFailed('id_token_invalid', 'The ID token signature or timing is invalid.');
        }

        if (($claims['iss'] ?? null) !== $document['issuer']) {
            throw new ChatGptFailed('id_token_invalid', 'The ID token issuer is wrong.');
        }

        $audience = $claims['aud'] ?? null;
        if (! in_array($this->settings->clientId(), is_array($audience) ? $audience : [$audience], true)) {
            throw new ChatGptFailed('id_token_invalid', 'The ID token audience is wrong.');
        }

        $tokenNonce = $claims['nonce'] ?? null;
        if (! is_string($tokenNonce) || ! hash_equals($nonce, $tokenNonce)) {
            throw new ChatGptFailed('id_token_invalid', 'The ID token nonce does not match.');
        }

        $subject = $claims['sub'] ?? null;
        if (! is_string($subject) || $subject === '') {
            throw new ChatGptFailed('id_token_invalid', 'The ID token has no subject.');
        }

        return [
            'sub' => $subject,
            'email' => is_string($claims['email'] ?? null) ? $claims['email'] : null,
            'name' => is_string($claims['name'] ?? null) ? $claims['name'] : null,
        ];
    }
}
