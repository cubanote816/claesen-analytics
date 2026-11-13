<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The OpenID discovery document (issuer, authorization, token and JWKS endpoints), cached.
 * Only a complete document is cached; a failure is never remembered.
 */
final class ChatGptDiscovery
{
    private const TTL_SECONDS = 86400;

    private const REQUIRED = ['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'];

    public function __construct(private readonly ChatGptSettings $settings) {}

    /** @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string} */
    public function document(): array
    {
        $url = $this->settings->discoveryUrl();
        $key = 'knx:chatgpt:discovery:'.sha1($url);

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::acceptJson()->timeout(10)->get($url);
        } catch (ConnectionException) {
            throw new ChatGptFailed('discovery_failed', 'The OpenID discovery document could not be fetched.');
        }

        $body = $response->successful() ? $response->json() : null;
        $document = [];

        foreach (self::REQUIRED as $field) {
            $value = is_array($body) ? ($body[$field] ?? null) : null;

            if (! is_string($value) || $value === '') {
                throw new ChatGptFailed('discovery_failed', 'The OpenID discovery document is unusable.');
            }

            $document[$field] = $value;
        }

        Cache::put($key, $document, self::TTL_SECONDS);

        return $document;
    }
}
